<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Domínios legítimos autorizados para o site e reprodutores de vídeo limpos
        $allowedOrigins = [
            "'self'",
            "https://cdn.vidstack.io",
            "https://*.vidstack.io",
            "https://player.autoembed.co",
            "https://*.autoembed.co",
            "https://autoembed.co",
            "https://vsembed.ru",
            "https://*.vsembed.ru",
            "https://mgeb.top",
            "https://*.mgeb.top",
            "https://www.2embed.cc",
            "https://*.2embed.cc",
            "https://2embed.cc",
            "https://nextgencloudfabric.com",
            "https://*.nextgencloudfabric.com",
            "https://*.qzz.io",
            "https://vid7102402.hclod.qzz.io",
            "https://v1.watchplay.shop",
            "https://*.watchplay.shop",
            "https://embedplayer2.xyz",
            "https://*.embedplayer2.xyz",
            "https://embedmovies.org",
            "https://*.embedmovies.org",
            "https://playerflix.ink",
            "https://*.playerflix.ink",
            "https://*.playercdn.xyz",
            "https://hubby.cx",
            "https://*.hubby.cx",
            "https://api.themoviedb.org",
            "https://image.tmdb.org",
            "https://*.themoviedb.org",
            "https://cdn.jsdelivr.net",
            "https://cdn.onesignal.com",
            "https://onesignal.com",
            "https://static.cloudflareinsights.com",
            "https://www.google.com",
            "https://www.gstatic.com",
            "https://www.youtube.com",
            "https://youtube.com",
            "https://sinalprivado.info",
            "https://*.sinalprivado.info",
            "https://cdn.vod-cinevs.com",
            "https://*.vod-cinevs.com",
            "data:",
            "blob:",
        ];

        $allowedList = implode(' ', $allowedOrigins);

        // Content Security Policy:
        // Proíbe conexões, scripts e iframes a servidores de popups/anúncios e captcha
        $cspDirectives = [
            "default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:",
            "connect-src " . $allowedList,
            "script-src 'self' 'unsafe-inline' 'unsafe-eval' " . $allowedList,
            "frame-src " . $allowedList,
            "img-src * data: blob:",
            "media-src * data: blob:",
            "font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.vidstack.io https://*.vidstack.io",
            "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.vidstack.io https://*.vidstack.io",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        $cspHeader = implode('; ', $cspDirectives);

        $response->headers->set('Content-Security-Policy', $cspHeader);
        $response->headers->set('Permissions-Policy', 'notifications=(), push=(), geolocation=(), camera=(), microphone=(), payment=()');

        return $response;
    }
}
