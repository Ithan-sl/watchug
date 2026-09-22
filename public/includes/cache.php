<?php
/**
 * Shared file-based and memory cache functions.
 * Uses Redis for instant RAM cache if available, falling back to JSON in {project_root}/app/cache/
 * Used by api.php, player_api.php, and iptv_stream.php.
 */

function cache_dir(): string {
    // Always resolve to {project_root}/app/cache regardless of which file includes this
    $dir = dirname(__DIR__) . '/app/cache';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function cache_path(string $key): string {
    $safe = preg_replace('#[^a-zA-Z0-9\-_]#', '_', $key);
    return cache_dir() . '/' . $safe . '.json';
}

/**
 * Singleton simples para conexão Redis
 */
function get_redis_connection() {
    static $redis = null;
    if ($redis !== null) return $redis;
    
    if (class_exists('Redis')) {
        try {
            $redis = new Redis();
            // Tenta conectar no localhost:6379 com timeout de 1 segundo para não travar se o serviço cair
            if (!@$redis->connect('127.0.0.1', 6379, 1.0)) {
                $redis = false;
            }
        } catch (Exception $e) {
            $redis = false;
        }
    } else {
        $redis = false;
    }
    
    return $redis;
}

function cache_get(string $key): ?array {
    // 1. Tenta Memória RAM via Redis - Ultra-rápido (< 0.1ms) e persistente
    $redis = get_redis_connection();
    if ($redis) {
        $cachedStr = $redis->get("megaembed_cache:" . $key);
        if ($cachedStr !== false) {
            $decoded = json_decode($cachedStr, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
    }

    // 2. Fallback para Arquivo (Disco)
    $path = cache_path($key);
    if (!is_file($path)) return null;
    
    $raw = @file_get_contents($path);
    if ($raw === false) return null;
    
    $data = json_decode($raw, true);
    if (!is_array($data) || !isset($data['expires'])) return null;
    
    // Tolerância de SWR (Stale While Revalidate) manual
    if ((int)$data['expires'] < time()) {
        @unlink($path);
        return null;
    }
    
    // Repopula o Redis para os próximos requests se foi encontrado no disco
    if ($redis && isset($data['value'])) {
        $remainingTtl = max(1, (int)$data['expires'] - time());
        $redis->setex("megaembed_cache:" . $key, $remainingTtl, json_encode($data['value'], JSON_UNESCAPED_UNICODE));
    }
    
    return $data['value'] ?? null;
}

function cache_set(string $key, array $value, int $ttlSeconds = 21600): void { // 6 horas padrão
    $ttlSeconds = max(60, $ttlSeconds);
    
    // 1. Salva na Memória RAM (Redis)
    $redis = get_redis_connection();
    if ($redis) {
        $redis->setex("megaembed_cache:" . $key, $ttlSeconds, json_encode($value, JSON_UNESCAPED_UNICODE));
    }

    // 2. Persiste no Disco para sobreviver a reboots e servir de backup
    $path = cache_path($key);
    $payload = [
        'expires' => time() + $ttlSeconds,
        'value' => $value,
    ];
    @file_put_contents($path, json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
}


