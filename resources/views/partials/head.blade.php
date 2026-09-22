<meta http-equiv="Permissions-Policy" content="notifications=(), push=(), geolocation=(), camera=(), microphone=(), payment=()">
<meta http-equiv="Content-Security-Policy" content="default-src 'self' 'unsafe-inline' 'unsafe-eval' data: blob:; connect-src 'self' https://cdn.vidstack.io https://*.vidstack.io https://mgeb.top https://*.mgeb.top https://player.autoembed.co https://*.autoembed.co https://autoembed.co https://vsembed.ru https://*.vsembed.ru https://www.2embed.cc https://*.2embed.cc https://2embed.cc https://nextgencloudfabric.com https://*.nextgencloudfabric.com https://*.qzz.io https://vid7102402.hclod.qzz.io https://v1.watchplay.shop https://*.watchplay.shop https://embedplayer2.xyz https://*.embedplayer2.xyz https://embedmovies.org https://*.embedmovies.org https://playerflix.ink https://*.playerflix.ink https://*.playercdn.xyz https://hubby.cx https://*.hubby.cx https://api.themoviedb.org https://image.tmdb.org https://*.themoviedb.org https://cdn.jsdelivr.net https://cdn.onesignal.com https://onesignal.com https://static.cloudflareinsights.com https://www.google.com https://www.gstatic.com https://www.youtube.com https://youtube.com https://sinalprivado.info https://*.sinalprivado.info https://cdn.vod-cinevs.com https://*.vod-cinevs.com data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.vidstack.io https://*.vidstack.io https://mgeb.top https://*.mgeb.top https://vsembed.ru https://*.vsembed.ru https://cdn.jsdelivr.net https://cdn.onesignal.com https://static.cloudflareinsights.com data: blob:; frame-src 'self' https://mgeb.top https://*.mgeb.top https://player.autoembed.co https://*.autoembed.co https://autoembed.co https://vsembed.ru https://*.vsembed.ru https://www.2embed.cc https://*.2embed.cc https://2embed.cc https://nextgencloudfabric.com https://*.nextgencloudfabric.com https://*.playercdn.xyz https://hubby.cx https://*.hubby.cx https://www.youtube.com https://youtube.com data: blob:; img-src * data: blob:; media-src * data: blob:; font-src 'self' data: https://fonts.gstatic.com https://cdn.jsdelivr.net https://cdn.vidstack.io https://*.vidstack.io; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com https://cdn.jsdelivr.net https://cdn.vidstack.io https://*.vidstack.io; base-uri 'self'; form-action 'self';">
<script>
    if (typeof window !== 'undefined') {
        try {
            if ('Notification' in window) {
                window.Notification.requestPermission = function() { return Promise.resolve('denied'); };
                try {
                    Object.defineProperty(window.Notification, 'permission', { get: function() { return 'denied'; } });
                } catch(e) {}
            }
            if (navigator.serviceWorker) {
                navigator.serviceWorker.register = function() { return Promise.reject(new Error('Blocked')); };
            }
            window.open = function() { return null; };
            window.alert = function() {};
            window.confirm = function() { return false; };
            window.prompt = function() { return null; };
        } catch(e) {}
    }
</script>
<script src="{{ asset('static/js/csp-shield.js') }}"></script>
<link rel="apple-touch-icon" sizes="180x180" href="{{asset('favicon/apple-touch-icon.png')}}">
<link rel="icon" type="image/png" sizes="32x32" href="{{asset('favicon/favicon-32x32.png')}}">
<link rel="icon" type="image/png" sizes="16x16" href="{{asset('favicon/favicon-16x16.png')}}">
<link rel="manifest" href="{{asset('site.webmanifest')}}">
@vite(['resources/scss/app.scss', 'resources/js/app.js'])

<style>
    :root {
    @if(config('settings.palette'))
        @foreach(config('attr.colors.'.config('settings.palette')) as $color => $value)
            {{'--color-gray-'.$color.':'.hexToRgb('#'.$value)}};
        @endforeach
    @else
        @foreach(config('attr.colors.zinc') as $color => $value)
            {{'--color-gray-'.$color.':'.hexToRgb('#'.$value)}};
        @endforeach
    @endif
    --color-primary-500: @if(config('settings.color')){{hexToRgb(config('settings.color'))}}@else{{hexToRgb('#8b5cf6')}}@endif;
    }

    /* Garantir que campos de texto e selects no tema escuro nunca tenham fundo branco por padrão do navegador */
    html.dark input[type="search"],
    html.dark input[type="text"],
    html.dark input[type="email"],
    html.dark input[type="password"],
    html.dark textarea,
    html.dark select {
        color-scheme: dark;
    }

    /* Garantir que barras de rolagem no tema escuro fiquem vermelhas com fundo escuro, nunca brancas */
    html.dark * {
        scrollbar-color: #dc2626 #111827;
    }
    html.dark ::-webkit-scrollbar {
        width: 8px;
        height: 6px;
    }
    html.dark ::-webkit-scrollbar-track {
        background: #111827;
    }
    html.dark ::-webkit-scrollbar-thumb {
        background: #dc2626;
        border-radius: 9999px;
    }
    html.dark ::-webkit-scrollbar-thumb:hover {
        background: #ef4444;
    }
</style>
{!! config('settings.custom_code') !!}
@if(config('settings.onesignal_id'))
    <script src="https://cdn.onesignal.com/sdks/OneSignalSDK.js" defer></script>
    <script>
        window.OneSignal = window.OneSignal || [];
        OneSignal.push(function () {
            OneSignal.init({
                appId: "{{env('ONESIGNAL_APP_ID')}}"
            });
        });

        OneSignal.push(function () {
            OneSignal.showNativePrompt();
        });
    </script>
@endif
