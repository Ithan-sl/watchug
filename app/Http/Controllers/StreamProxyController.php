<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class StreamProxyController extends Controller
{
    const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36';

    protected function decodeToken(?string $token): ?string
    {
        if (!$token) return null;
        try {
            return Crypt::decryptString($token);
        } catch (\Exception $e) {
            // Fallback para base64 seguro se não for string criptografada do Laravel
            try {
                $decoded = base64_decode(strtr($token, '-_', '+/'));
                if ($decoded && filter_var($decoded, FILTER_VALIDATE_URL)) {
                    return $decoded;
                }
            } catch (\Exception $e2) {}
            return null;
        }
    }

    protected function encodeToken(string $url): string
    {
        return Crypt::encryptString($url);
    }

    protected function getUpstreamReferer(string $targetUrl): string
    {
        if (str_contains($targetUrl, 'qzz.io') || str_contains($targetUrl, 'watchplay.shop')) {
            return 'https://v1.watchplay.shop/';
        }
        if (str_contains($targetUrl, 'sinalprivado')) {
            return 'https://sinalprivado.info/';
        }
        if (str_contains($targetUrl, 'playercdn') || str_contains($targetUrl, 'embedplayer')) {
            return 'https://embedplayer2.xyz/';
        }
        if (str_contains($targetUrl, 'redeflix')) {
            return 'https://redeflixapi.store/';
        }
        try {
            $parsed = parse_url($targetUrl);
            return ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? '') . '/';
        } catch (\Exception $e) {
            return 'https://google.com/';
        }
    }

    public function manifest(Request $request)
    {
        $token = $request->query('t');
        $targetUrl = $this->decodeToken($token);

        if (!$targetUrl || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return response('URL de stream inválida', 400);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(15)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Referer' => $this->getUpstreamReferer($targetUrl),
                ])
                ->get($targetUrl);

            if (!$response->successful()) {
                return response('Erro ao buscar manifesto upstream: ' . $response->status(), 502);
            }

            $content = $response->body();
            $baseUrl = $targetUrl;
            $parsedBase = parse_url($baseUrl);
            $baseDomain = ($parsedBase['scheme'] ?? 'https') . '://' . ($parsedBase['host'] ?? '');
            $basePath = isset($parsedBase['path']) ? dirname($parsedBase['path']) : '';

            $lines = explode("\n", $content);
            $rewritten = [];

            foreach ($lines as $line) {
                $trimmed = trim($line);
                if (empty($trimmed)) {
                    $rewritten[] = $line;
                    continue;
                }

                // Linha com URI nos atributos (ex: #EXT-X-MEDIA, #EXT-X-I-FRAME-STREAM-INF, #EXT-X-MAP)
                if (str_starts_with($trimmed, '#EXT-X-MEDIA') || str_starts_with($trimmed, '#EXT-X-I-FRAME-STREAM-INF') || str_starts_with($trimmed, '#EXT-X-MAP')) {
                    $newLine = preg_replace_callback('/URI=["\']([^"\']+)["\']/i', function ($m) use ($baseDomain, $basePath, $baseUrl) {
                        $rawUri = $m[1];
                        $fullUri = $this->resolveFullUrl($rawUri, $baseDomain, $basePath, $baseUrl);
                        $enc = urlencode($this->encodeToken($fullUri));
                        if (str_contains($fullUri, '.m3u8')) {
                            return 'URI="' . route('stream.manifest', [], false) . '?t=' . $enc . '"';
                        } else {
                            return 'URI="' . route('stream.segment', [], false) . '?t=' . $enc . '"';
                        }
                    }, $line);
                    $rewritten[] = $newLine;
                    continue;
                }

                // Linha de comentário/tag
                if (str_starts_with($trimmed, '#')) {
                    $rewritten[] = $line;
                    continue;
                }

                // Linha de URL de mídia ou sub-manifesto
                $fullUri = $this->resolveFullUrl($trimmed, $baseDomain, $basePath, $baseUrl);
                $enc = urlencode($this->encodeToken($fullUri));

                if (str_contains($fullUri, '.m3u8') || str_contains($fullUri, '/hls/') || str_contains($fullUri, '/md/')) {
                    $rewritten[] = route('stream.manifest', [], false) . '?t=' . $enc;
                } else {
                    $rewritten[] = route('stream.segment', [], false) . '?t=' . $enc;
                }
            }

            return response(implode("\n", $rewritten))
                ->header('Content-Type', 'application/vnd.apple.mpegurl; charset=utf-8')
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                ->header('Permissions-Policy', 'notifications=(), push=(), geolocation=(), camera=(), microphone=(), payment=()');

        } catch (\Exception $e) {
            return response('Falha no proxy de streaming: ' . $e->getMessage(), 500);
        }
    }

    public function segment(Request $request)
    {
        $token = $request->query('t');
        $targetUrl = $this->decodeToken($token);

        if (!$targetUrl || !filter_var($targetUrl, FILTER_VALIDATE_URL)) {
            return response('Segmento inválido', 400);
        }

        try {
            $response = Http::withoutVerifying()
                ->timeout(30)
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Referer' => $this->getUpstreamReferer($targetUrl),
                ])
                ->get($targetUrl);

            if (!$response->successful()) {
                return response('Segmento indisponível: ' . $response->status(), 502);
            }

            $contentType = 'video/mp2t';
            if (str_contains($targetUrl, '.vtt') || str_contains($targetUrl, '/Subtitle/')) {
                $contentType = 'text/vtt; charset=utf-8';
            }

            return response($response->body())
                ->header('Content-Type', $contentType)
                ->header('Access-Control-Allow-Origin', '*')
                ->header('Cache-Control', 'public, max-age=86400')
                ->header('Permissions-Policy', 'notifications=(), push=(), geolocation=(), camera=(), microphone=(), payment=()');

        } catch (\Exception $e) {
            return response('Erro no download do segmento: ' . $e->getMessage(), 500);
        }
    }

    protected function resolveFullUrl(string $url, string $baseDomain, string $basePath, string $fullBase): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }
        if (str_starts_with($url, '//')) {
            return 'https:' . $url;
        }
        if (str_starts_with($url, '/')) {
            return rtrim($baseDomain, '/') . $url;
        }
        return rtrim($baseDomain, '/') . '/' . trim($basePath, '/') . '/' . $url;
    }
}
