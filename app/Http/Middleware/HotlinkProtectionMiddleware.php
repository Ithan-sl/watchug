<?php

namespace App\Http\Middleware;

use Closure;

class HotlinkProtectionMiddleware
{
    public function handle($request, Closure $next)
    {

        // Referer boşsa veya referer youtube.com ise devam et
        $referer = $request->headers->get('referer');
        $allowedDomain = url('/'); // Kendi alan adınızı buraya girin

        // Referer boşsa veya referer kendi alan adınızdaysa devam et
        $refererHost = parse_url($referer, PHP_URL_HOST);
        $requestHost = $request->getHost();
        $serverName = $_SERVER['SERVER_NAME'] ?? '';
        if (empty($referer) || $refererHost === $requestHost || $refererHost === $serverName || (!empty($refererHost) && str_contains($refererHost, $requestHost))) {
            return $next($request);
        }

        // Hotlink tespit edildiğinde buraya ulaşılır
        return response('Hotlink protection', 403);
    }
}
