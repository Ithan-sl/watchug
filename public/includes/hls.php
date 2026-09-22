<?php
/**
 * HLS Proxy - Busca conteúdo HLS (m3u8/ts/txt) server-side via curl
 * para contornar proteções CORS/Referer de CDNs protegidas.
 *
 * Uso: /includes/hls.php?url=<encoded_url>
 */

// Desabilita exibição de erros para não interferir com o streaming
error_reporting(0);
ini_set('display_errors', 0);

// --- Configuração ---
$ALLOWED_EXTENSIONS = ['m3u8', 'txt', 'ts', 'key', 'vtt', 'srt', 'mp4', 'aac', 'fmp4', 'm4s', 'm4a', 'mp3', 'ac3', 'webm', 'ogg', 'wav'];
$MAX_CONTENT_SIZE = 50 * 1024 * 1024; // 50MB limite para segmentos .ts
$CURL_TIMEOUT = 30; // segundos

require_once __DIR__ . '/security.php';

// --- Validação do parâmetro URL (deve vir criptografado e assinado) ---
$encryptedUrl = isset($_GET['url']) ? trim($_GET['url']) : '';

if (empty($encryptedUrl)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Parâmetro "url" é obrigatório.']);
    exit;
}

$decrypted = decrypt_proxy_url($encryptedUrl);
if (!$decrypted || empty($decrypted['url']) || empty($decrypted['expiry'])) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Assinatura inválida ou corrompida.']);
    exit;
}

// Verifica se expirou (com tolerância de 2 horas para clock drift e transições suaves de cache)
$gracePeriod = 7200; // 2 horas de tolerância
if (time() > ($decrypted['expiry'] + $gracePeriod)) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Assinatura expirou.']);
    exit;
}

$targetUrl = safe_idn_to_ascii($decrypted['url']);
$expiry = $decrypted['expiry'];

// Valida que é uma URL HTTP/HTTPS válida
if (!filter_var($targetUrl, FILTER_VALIDATE_URL) || !preg_match('#^https?://#i', $targetUrl)) {
    http_response_code(400);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'URL inválida decodificada. Apenas HTTP/HTTPS são permitidos.']);
    exit;
}

// Bloqueia acesso a endereços internos/localhost
$parsedHost = parse_url($targetUrl, PHP_URL_HOST);
if ($parsedHost) {
    $hostLower = strtolower($parsedHost);
    $blockedHosts = ['localhost', '127.0.0.1', '0.0.0.0', '::1', '10.', '192.168.', '172.'];
    foreach ($blockedHosts as $blocked) {
        if (strpos($hostLower, $blocked) === 0 || $hostLower === $blocked) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Acesso a endereços internos não é permitido.']);
            exit;
        }
    }
}

// --- Determina headers CORS para a resposta ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, HEAD, OPTIONS');
header('Access-Control-Allow-Headers: Range, Origin, Content-Type');
header('Access-Control-Expose-Headers: Content-Length, Content-Range, Content-Type');

// Responde a preflight OPTIONS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// --- Determina Content-Type correto antes da requisição para otimizar ---
$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '';
$requestExt = strtolower(pathinfo($requestPath, PATHINFO_EXTENSION));

$mimeMap = [
    'm3u8' => 'application/vnd.apple.mpegurl',
    'txt'  => 'application/vnd.apple.mpegurl', // .txt usados como HLS manifests
    'ts'   => 'video/mp2t',
    'mp4'  => 'video/mp4',
    'fmp4' => 'video/mp4',
    'm4s'  => 'video/iso.segment', // fMP4 segments
    'm4a'  => 'audio/mp4',
    'mp3'  => 'audio/mpeg',
    'aac'  => 'audio/aac',
    'ac3'  => 'audio/ac3',
    'ogg'  => 'audio/ogg',
    'wav'  => 'audio/wav',
    'webm' => 'video/webm',
    'key'  => 'application/octet-stream',
    'vtt'  => 'text/vtt',
    'srt'  => 'text/plain',
];

if ($requestExt !== 'php' && in_array($requestExt, $ALLOWED_EXTENSIONS)) {
    $extension = $requestExt;
} else {
    $extParam = isset($_GET['ext']) ? strtolower(trim($_GET['ext'], '.')) : '';
    if ($extParam && in_array($extParam, $ALLOWED_EXTENSIONS)) {
        $extension = $extParam;
    } else {
        $parsedPath = parse_url($targetUrl, PHP_URL_PATH) ?: '';
        $extension = strtolower(pathinfo($parsedPath, PATHINFO_EXTENSION));
    }
}

$expectedMime = $mimeMap[$extension] ?? 'application/octet-stream';
// null se a extensão não for conclusiva (ex: .html ou sem extensão)
$isManifest = in_array($extension, ['m3u8', 'txt']) ? true : (in_array($extension, ['ts', 'mp4', 'fmp4', 'm4s', 'm4a', 'mp3', 'aac', 'ac3', 'ogg', 'wav', 'webm']) ? false : null);

// --- Determina Referer e Origin dinamicamente para contornar anti-hotlink ---
$referer = isset($_GET['referer']) ? trim($_GET['referer']) : '';
$parsedTarget = parse_url($targetUrl);
$targetOrigin = '';
$targetHost = isset($parsedTarget['host']) ? strtolower($parsedTarget['host']) : '';

if ($parsedTarget && isset($parsedTarget['scheme']) && $targetHost) {
    if (!empty($referer)) {
        $parsedReferer = parse_url($referer);
        if ($parsedReferer && isset($parsedReferer['scheme']) && isset($parsedReferer['host'])) {
            $targetOrigin = $parsedReferer['scheme'] . '://' . $parsedReferer['host'];
            if (isset($parsedReferer['port'])) {
                $targetOrigin .= ':' . $parsedReferer['port'];
            }
        } else {
            $targetOrigin = $parsedTarget['scheme'] . '://' . $targetHost;
        }
    } else {
        $targetOrigin = $parsedTarget['scheme'] . '://' . $targetHost;
        // Detecção inteligente de domínio principal para contornar proteções rígidas de CDNs.
        // Se o host for um subdomínio (ex: cdn.vids.st, static.vids.st, media.vids.st, video43999.vids.st),
        // extraímos o domínio pai (vids.st) como Referer/Origin padrão.
        $hostParts = explode('.', $targetHost);
        if (count($hostParts) > 2) {
            $lastIndex = count($hostParts) - 1;
            $isThreePartDomain = in_array($hostParts[$lastIndex - 1], ['com', 'net', 'org', 'co', 'edu', 'gov']);
            
            if ($isThreePartDomain && count($hostParts) >= 3) {
                $parentParts = array_slice($hostParts, -3);
            } else {
                $parentParts = array_slice($hostParts, -2);
            }
            
            $parentHost = implode('.', $parentParts);
            $referer = $parsedTarget['scheme'] . '://' . $parentHost . '/';
            $targetOrigin = $parsedTarget['scheme'] . '://' . $parentHost;
        } else {
            $referer = $targetOrigin . '/';
        }
    }
}

// User-Agent dinâmico baseado no navegador real do usuário visitante
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36';

// --- Configuração do cURL ---
$ch = curl_init();

$headers = [
    'Accept: */*',
    'Accept-Language: pt-BR,pt;q=0.9,en-US;q=0.8,en;q=0.7',
    'Cache-Control: no-cache',
    'Pragma: no-cache',
    'Connection: keep-alive',
    'Sec-Fetch-Dest: empty',
    'Sec-Fetch-Mode: cors',
    'Sec-Fetch-Site: cross-site'
];

if ($targetOrigin) {
    // Apenas envia Origin se não for igual ao host do destino para evitar bloqueios de segurança (ex: Cloudflare 500)
    $originHost = parse_url($targetOrigin, PHP_URL_HOST);
    if ($originHost !== $targetHost) {
        $headers[] = 'Origin: ' . $targetOrigin;
    }
}

curl_setopt_array($ch, [
    CURLOPT_URL => $targetUrl,
    CURLOPT_RETURNTRANSFER => false, // MUITO IMPORTANTE: false para streaming direto
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_MAXREDIRS => 5,
    CURLOPT_TIMEOUT => ($isManifest === false ? 0 : $CURL_TIMEOUT),
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_USERAGENT => $userAgent,
    CURLOPT_REFERER => $referer,
    CURLOPT_HTTPHEADER => $headers,
    CURLOPT_HEADER => false,
    CURLOPT_TCP_NODELAY => true,  // Remove latência do algoritmo de Nagle, forçando TCP imediato
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2_0, // Força negociação HTTP/2 para contornar proteções/WAF modernas
]);

// Permite que o cURL negocie e decodifique compressão automaticamente (gzip, deflate) APENAS para arquivos de texto/manifestos
// Se fizermos isso para MP4, ele pode corromper binários e ocultar o Content-Length
if ($isManifest !== false) {
    curl_setopt($ch, CURLOPT_ENCODING, '');
}

// Passa Range header se presente
if (isset($_SERVER['HTTP_RANGE'])) {
    curl_setopt($ch, CURLOPT_RANGE, str_replace('bytes=', '', $_SERVER['HTTP_RANGE']));
}

$responseHeaders = [];
$headerSent = false;
$manifestBuffer = '';

/**
 * Função auxiliar para enviar headers de resposta para segmentos.
 */
function sendResponseHeaders($curl, $responseHeaders, $expectedMime) {
    global $expiry;
    $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
    http_response_code($httpCode);
    
    $mime = $responseHeaders['content-type'] ?? '';
    if ($mime) {
        $mimeClean = strtolower(trim(explode(';', $mime)[0]));
        $obfuscatedTypes = [
            'application/javascript', 
            'application/x-javascript', 
            'text/javascript', 
            'text/css', 
            'text/html', 
            'text/plain',
            'application/octet-stream',
            'font/woff',
            'font/woff2',
            'application/font-woff',
            'application/x-font-woff',
            'application/x-font-truetype',
            'application/x-font-opentype',
            'font/opentype',
            'font/otf',
            'font/ttf',
            'image/svg+xml'
        ];
        if (in_array($mimeClean, $obfuscatedTypes) && $expectedMime && $expectedMime !== 'application/octet-stream') {
            $mime = $expectedMime;
        }
    } else {
        $mime = $expectedMime;
    }
    header('Content-Type: ' . $mime);
    
    if (isset($responseHeaders['content-range'])) {
        header('Content-Range: ' . $responseHeaders['content-range']);
    }
    if (isset($responseHeaders['accept-ranges'])) {
        header('Accept-Ranges: ' . $responseHeaders['accept-ranges']);
    }
    if (isset($responseHeaders['content-length'])) {
        header('Content-Length: ' . $responseHeaders['content-length']);
    }
    if (isset($responseHeaders['last-modified'])) {
        header('Last-Modified: ' . $responseHeaders['last-modified']);
    }
    if (isset($responseHeaders['etag'])) {
        header('ETag: ' . $responseHeaders['etag']);
    }
    
    // Calcula o tempo de cache com base no tempo de expiração do token do proxy
    $cacheTTL = 0;
    if (isset($expiry) && $expiry > time()) {
        $cacheTTL = max(0, (int)$expiry - time());
    }
    
    // Limpa headers herdados/padrão do PHP que impedem cache no Cloudflare/Proxy
    header_remove('Pragma');
    header_remove('Expires');
    
    if ($cacheTTL > 0) {
        // Alinhado ao token do proxy
        header('Cache-Control: public, max-age=' . $cacheTTL . ', s-maxage=' . $cacheTTL);
    } else {
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    }
    
    header('Vary: Accept-Encoding');
}

// Callback para processar headers da resposta remota
curl_setopt($ch, CURLOPT_HEADERFUNCTION, function($curl, $header) use (&$responseHeaders, &$headerSent, &$isManifest, $expectedMime) {
    $len = strlen($header);
    $parts = explode(':', $header, 2);
    
    if (count($parts) === 2) {
        $name = strtolower(trim($parts[0]));
        $value = trim($parts[1]);
        $responseHeaders[$name] = $value;
    }
    
    // Quando os headers terminam (linha vazia)
    if (trim($header) === '') {
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($httpCode >= 300 && $httpCode < 400) {
            // É um redirecionamento, limpa os headers acumulados e aguarda o próximo bloco de headers
            $responseHeaders = [];
        } else {
            // Se já sabemos que é segmento, enviamos os headers aqui
            if ($isManifest === false && !$headerSent) {
                sendResponseHeaders($curl, $responseHeaders, $expectedMime);
                $headerSent = true;
            }
        }
    }
    return $len;
});

// Callback para processar o corpo da resposta
curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($curl, $data) use (&$manifestBuffer, &$isManifest, &$headerSent, &$responseHeaders, $expectedMime) {
    if ($isManifest === null) {
        // Se ainda não sabemos se é manifesto (sem extensão clara), checa os primeiros bytes
        $isManifest = (strpos($data, '#EXTM3U') === 0);
        
        if (!$isManifest && !$headerSent) {
            $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
            if ($httpCode < 300 || $httpCode >= 400) {
                sendResponseHeaders($curl, $responseHeaders, $expectedMime);
                $headerSent = true;
            }
        }
    }

    if ($isManifest) {
        $manifestBuffer .= $data;
    } else {
        if (connection_aborted()) {
            return 0; // aborta o curl se o player do usuário cancelar a requisição/seek
        }
        echo $data;
        flush();
    }
    return strlen($data);
});

// Remove buffers de saída do PHP para evitar travamento em arquivos grandes
while (ob_get_level()) {
    ob_end_clean();
}

// Permite execução longa para streaming de vídeos pesados
set_time_limit(0);

// Executa a requisição
curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$effectiveUrl = curl_getinfo($ch, CURLINFO_EFFECTIVE_URL);
$curlError = curl_error($ch);
curl_close($ch);

// --- Tratamento de erros ---
if ($httpCode >= 400 && !$isManifest && !$headerSent) {
    http_response_code($httpCode);
    exit;
}

// --- Processamento de Manifestos ---
if ($isManifest) {
    http_response_code($httpCode);
    $contentType = $responseHeaders['content-type'] ?? '';
    if ($contentType) {
        $mimeClean = strtolower(trim(explode(';', $contentType)[0]));
        $obfuscatedTypes = [
            'application/javascript', 
            'application/x-javascript', 
            'text/javascript', 
            'text/css', 
            'text/html', 
            'text/plain',
            'application/octet-stream',
            'font/woff',
            'font/woff2',
            'application/font-woff',
            'application/x-font-woff',
            'application/x-font-truetype',
            'application/x-font-opentype',
            'font/opentype',
            'font/otf',
            'font/ttf',
            'image/svg+xml'
        ];
        if (in_array($mimeClean, $obfuscatedTypes) && $expectedMime && $expectedMime !== 'application/octet-stream') {
            $contentType = $expectedMime;
        }
    } else {
        $contentType = $expectedMime;
    }
    header('Content-Type: ' . $contentType);

    if (isset($responseHeaders['last-modified'])) {
        header('Last-Modified: ' . $responseHeaders['last-modified']);
    }
    if (isset($responseHeaders['etag'])) {
        header('ETag: ' . $responseHeaders['etag']);
    }

    // Manifestos: cache curtíssimo no edge da CDN (30s) e navegador (10s)
    // para evitar que playlists com assinaturas expiradas fiquem presas em cache,
    // e Vary para separar o cache por domínio de balanceamento (Host) e origem (Origin).
    header('Cache-Control: public, max-age=10, s-maxage=30');
    header('Vary: Accept-Encoding');
    
    if (!empty($manifestBuffer)) {
        $rewritten = rewriteManifestUrls($manifestBuffer, $effectiveUrl ?: $targetUrl, $expiry, $referer);
        echo $rewritten;
    }
}
exit;

// --- Funções auxiliares ---

/**
 * Reescreve URLs dentro de manifestos HLS (.m3u8/.txt) para passarem pelo proxy.
 * URLs relativas são convertidas em absolutas. Segmentos de mídia e chaves são passados
 * pelo proxy apenas se o acesso direto no navegador do cliente for bloqueado por CORS ou erros de rede.
 */
function rewriteManifestUrls(string $manifest, string $manifestUrl, int $expiry, string $referer = ''): string {
    // Se for um manifesto master (contém sub-playlists / resoluções), ordena as qualidades de forma decrescente (maior resolução primeiro)
    if (str_contains($manifest, '#EXT-X-STREAM-INF')) {
        $lines = explode("\n", $manifest);
        $headerLines = [];
        $blocks = [];
        $currentBlockHeader = null;
        
        foreach ($lines as $line) {
            $trimmed = trim($line);
            if ($trimmed === '') continue;
            
            if (strpos($trimmed, '#EXT-X-STREAM-INF') === 0) {
                $currentBlockHeader = $trimmed;
            } elseif ($currentBlockHeader !== null && strpos($trimmed, '#') !== 0) {
                $blocks[] = [
                    'header' => $currentBlockHeader,
                    'url' => $trimmed
                ];
                $currentBlockHeader = null;
            } else {
                if (strpos($trimmed, '#EXT') === 0) {
                    $headerLines[] = $trimmed;
                }
            }
        }
        
        if (!empty($blocks)) {
            usort($blocks, function($a, $b) {
                $aRes = 0;
                $bRes = 0;
                if (preg_match('/RESOLUTION=(\d+)x(\d+)/i', $a['header'], $m)) {
                    $aRes = (int)$m[1] * (int)$m[2];
                }
                if (preg_match('/RESOLUTION=(\d+)x(\d+)/i', $b['header'], $m)) {
                    $bRes = (int)$m[1] * (int)$m[2];
                }
                
                if ($aRes === $bRes) {
                    $aBandwidth = 0;
                    $bBandwidth = 0;
                    if (preg_match('/BANDWIDTH=(\d+)/i', $a['header'], $m)) {
                        $aBandwidth = (int)$m[1];
                    }
                    if (preg_match('/BANDWIDTH=(\d+)/i', $b['header'], $m)) {
                        $bBandwidth = (int)$m[1];
                    }
                    return $aBandwidth <=> $bBandwidth;
                }
                
                return $aRes <=> $bRes;
            });
            
            $newManifestLines = $headerLines;
            foreach ($blocks as $block) {
                $newManifestLines[] = $block['header'];
                $newManifestLines[] = $block['url'];
            }
            $manifest = implode("\n", $newManifestLines);
        }
    }

    $baseUrl = getBaseUrl($manifestUrl);
    $proxyBase = getProxyBaseUrl();
    $lines = explode("\n", $manifest);
    $result = [];
    $isSegmentContext = false;

    // Loop de reescrita do manifesto
    foreach ($lines as $line) {
        $trimmed = trim($line);

        if ($trimmed === '') {
            $result[] = $line;
            continue;
        }

        if (preg_match('/^#EXT.*URI="([^"]+)"/', $trimmed, $matches)) {
            $uri = $matches[1];
            $absoluteUri = resolveUrl($uri, $baseUrl, $manifestUrl);
            
            $basename = basename(parse_url($absoluteUri, PHP_URL_PATH) ?: '');
            $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
            
            // Determina a extensão de destino com base no tipo de tag
            $targetExt = 'm3u8';
            if (strpos($trimmed, '#EXT-X-KEY') === 0) {
                $targetExt = 'key';
            } elseif (strpos($trimmed, '#EXT-X-MAP') === 0) {
                $targetExt = 'ts';
                if (in_array($ext, ['mp4', 'fmp4'])) {
                    $targetExt = $ext;
                }
            } else {
                if (in_array($ext, ['ts', 'mp4', 'fmp4', 'aac', 'key', 'vtt', 'srt', 'm4s', 'm4a', 'mp3', 'ac3', 'webm', 'ogg', 'wav'])) {
                    $targetExt = $ext;
                }
            }
            
            if (empty($basename)) {
                $basename = 'stream.' . $targetExt;
            } else {
                $pos = strrpos($basename, '.');
                $nameWithoutExt = ($pos !== false) ? substr($basename, 0, $pos) : $basename;
                $nameWithoutExt = preg_replace('/[^a-zA-Z0-9_-]/', '', $nameWithoutExt);
                if (strlen($nameWithoutExt) > 50) {
                    $nameWithoutExt = substr($nameWithoutExt, 0, 30) . '_' . substr(md5($nameWithoutExt), 0, 10);
                }
                $basename = $nameWithoutExt . '.' . $targetExt;
            }

            $proxiedUri = $proxyBase . '/' . $basename . '?url=' . urlencode(encrypt_proxy_url($absoluteUri, $expiry));
            if ($referer) {
                $proxiedUri .= '&referer=' . urlencode($referer);
            }
            $line = str_replace('URI="' . $uri . '"', 'URI="' . $proxiedUri . '"', $line);
            
            $result[] = $line;
            continue;
        }

        if (strpos($trimmed, '#') === 0) {
            if (strpos($trimmed, '#EXTINF') === 0) {
                $isSegmentContext = true;
            } elseif (strpos($trimmed, '#EXT-X-STREAM-INF') === 0) {
                $isSegmentContext = false;
            }
            $result[] = $line;
            continue;
        }

        $absoluteUrl = resolveUrl($trimmed, $baseUrl, $manifestUrl);
        
        $basename = basename(parse_url($absoluteUrl, PHP_URL_PATH) ?: '');
        $ext = strtolower(pathinfo($basename, PATHINFO_EXTENSION));
        
        if ($isSegmentContext) {
            $targetExt = 'ts';
            if (in_array($ext, ['ts', 'mp4', 'fmp4', 'aac', 'key', 'vtt', 'srt', 'm4s', 'm4a', 'mp3', 'ac3', 'webm', 'ogg', 'wav'])) {
                $targetExt = $ext;
            }
        } else {
            $targetExt = 'm3u8';
            if (in_array($ext, ['ts', 'mp4', 'fmp4', 'aac', 'key', 'vtt', 'srt'])) {
                $targetExt = $ext;
            }
        }
        
        if (empty($basename)) {
            $basename = 'stream.' . $targetExt;
        } else {
            $pos = strrpos($basename, '.');
            $nameWithoutExt = ($pos !== false) ? substr($basename, 0, $pos) : $basename;
            $nameWithoutExt = preg_replace('/[^a-zA-Z0-9_-]/', '', $nameWithoutExt);
            if (strlen($nameWithoutExt) > 50) {
                $nameWithoutExt = substr($nameWithoutExt, 0, 30) . '_' . substr(md5($nameWithoutExt), 0, 10);
            }
            $basename = $nameWithoutExt . '.' . $targetExt;
        }
        
        $proxiedUrl = $proxyBase . '/' . $basename . '?url=' . urlencode(encrypt_proxy_url($absoluteUrl, $expiry));
        if ($referer) {
            $proxiedUrl .= '&referer=' . urlencode($referer);
        }
        $result[] = $proxiedUrl;
        
        $isSegmentContext = false;
    }

    return implode("\n", $result);
}

function resolveUrl(string $url, string $baseUrl, string $manifestUrl): string {
    if (preg_match('#^https?://#i', $url)) return $url;
    if (strpos($url, '//') === 0) {
        $scheme = parse_url($manifestUrl, PHP_URL_SCHEME) ?: 'https';
        return $scheme . ':' . $url;
    }
    if (strpos($url, '/') === 0) {
        $parsed = parse_url($manifestUrl);
        $host = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '');
        if (isset($parsed['port'])) $host .= ':' . $parsed['port'];
        return $host . $url;
    }
    return $baseUrl . $url;
}

function getBaseUrl(string $url): string {
    $lastSlash = strrpos($url, '/');
    if ($lastSlash !== false) return substr($url, 0, $lastSlash + 1);
    return $url . '/';
}

function getProxyBaseUrl(): string {
    $scheme = (isset($_SERVER['HTTPS']) && ($_SERVER['HTTPS'] === 'on' || $_SERVER['HTTPS'] === '1') || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')) ? 'https' : 'http';
    $host = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host . '/includes/hls.php';
}
