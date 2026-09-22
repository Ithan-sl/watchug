@extends('layouts.embed')
@section('content')
    @php
        $playerAd = \Illuminate\Support\Facades\Cache::remember('ad-5', 3600, function () {
            return \App\Models\Advertisement::where('id', 5)->where('status', 'publish')->first();
        });
        $showPlayerAd = isset($playerAd->body) && !empty(trim($playerAd->body));
        if ($showPlayerAd && $playerAd->user_hide == 'active' && auth()->check() && auth()->user()->plan_recurring_at > now()) {
            $showPlayerAd = false;
        }
    @endphp

    @if($showPlayerAd)
        @php
            $decodedAdBody = html_entity_decode($playerAd->body);
            $hasVisual = preg_match('/<(img|iframe|video|picture|svg|a|div|p|span|h[1-6]|table|center)/i', strip_tags($decodedAdBody, '<img><iframe><video><picture><svg><a><div><p><span><h1><h2><h3><h4><h5><h6><table><center>'));
        @endphp

        @if($hasVisual)
            <div id="embed-ad-overlay"
                 class="absolute inset-0 z-30 flex flex-col items-center justify-center bg-black/85 backdrop-blur-sm p-4 transition-all"
                 style="display: flex;">
                <div class="absolute top-3 right-3 z-40">
                    <button onclick="document.getElementById('embed-ad-overlay').style.display='none';"
                            type="button"
                            class="px-3.5 py-1.5 bg-gray-800 text-white text-xs font-semibold rounded-full border border-gray-600 shadow-xl cursor-pointer">
                        {{ __('Fechar anúncio') }} ✕
                    </button>
                </div>
                <div class="max-w-full max-h-[85%] overflow-auto flex items-center justify-center">
                    {!! $decodedAdBody !!}
                </div>
            </div>
        @else
            {!! $decodedAdBody !!}
        @endif
    @endif

    @if($listing->type == 'embed')
        <script>
            // Escudo Sanitizado de Embed (Camada Anti-Popup Rígida)
            (function() {
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
                    var fakeWindow = {
                        closed: false,
                        name: '',
                        status: '',
                        opener: window,
                        close: function() { this.closed = true; },
                        focus: function() {},
                        blur: function() {},
                        postMessage: function() {},
                        print: function() {},
                        stop: function() {},
                        document: {
                            write: function() {},
                            writeln: function() {},
                            open: function() {},
                            close: function() {},
                            location: { href: 'about:blank' }
                        },
                        location: { href: 'about:blank' }
                    };
                    window.open = function(url, target, features) {
                        console.warn('[Anti-Popup Shield] Fake window.open interceptado para embed:', url);
                        return fakeWindow;
                    };
                    window.alert = function() {};
                    window.confirm = function() { return false; };
                    window.prompt = function() { return null; };
                    window.onbeforeunload = null;
                } catch(err) {}
            })();
        </script>
        <div class="w-full aspect-video relative group" id="embed-player-container">
            <iframe class="w-full h-full"
                src="{{$listing->link}}"
                allowfullscreen="true"
                allow="autoplay; fullscreen; picture-in-picture; encrypted-media; accelerometer; gyroscope"
                allowtransparency
            ></iframe>
        </div>
    @else
    @if(config('settings.player') == 'vidstack' || !in_array(config('settings.player'), ['videojs', 'plyr']))
        @php
            $poster = $listing->postable->post->coverurl ?? $listing->postable->coverurl ?? '';
            $title = $listing->postable->title ?? $listing->postable->name ?? $listing->label ?? '';
            if ($listing->type == 'hls') {
                $mediaSrc = route('stream.manifest', ['t' => \Illuminate\Support\Facades\Crypt::encryptString($listing->link)]);
            } else {
                $mediaSrc = $listing->link;
            }
        @endphp

        <div class="w-full h-full aspect-video bg-black flex items-center justify-center relative overflow-hidden">
            <media-player
                title="{{ $title }}"
                src="{{ $mediaSrc }}"
                poster="{{ $poster }}"
                aspect-ratio="16/9"
                crossorigin
                playsinline
                autoplay
                controls
                class="w-full h-full"
            >
                <media-provider>
                    @if(isset($listing->postable->subtitles))
                        @foreach($listing->postable->subtitles as $subtitle)
                            <track kind="subtitles" label="{{$subtitle->country->name}}" srclang="{{$subtitle->country->code}}" src="{{$subtitle->linkurl}}" />
                        @endforeach
                    @endif
                </media-provider>
                <media-video-layout></media-video-layout>
            </media-player>
        </div>

        @push('style')
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vidstack@^1.12.0/player/styles/default/theme.css" />
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/vidstack@^1.12.0/player/styles/default/layouts/video.css" />
            <style>
                media-player {
                    --media-brand: #e50914;
                    --media-focus-ring: #e50914;
                    width: 100% !important;
                    height: 100% !important;
                }
            </style>
        @endpush

        @push('javascript')
            <script type="module" src="https://cdn.jsdelivr.net/npm/vidstack@^1.12.0/player/+esm"></script>
        @endpush
    @elseif(config('settings.player') == 'videojs')

        @if (strpos($listing->link, 'youtube.com') !== false)
            <div class="w-full aspect-video">
                <video id="player" class="video-js w-full h-full vjs-default-skin" controls
                       data-setup='{ "techOrder": ["youtube"], "sources": [{ "type": "video/youtube", "src": "{{$listing->link}}"}], "youtube": { "customVars": { "wmode": "none" } } }'></video>
            </div>
        @elseif($listing->type == 'mp4')
            <div class="w-full aspect-video">
                <video id="player" class="video-js w-full h-full vjs-default-skin" data-setup="{}" controls
                       preload="auto" poster="{{$listing->postable->post->coverurl ?? $listing->postable->coverurl ?? ''}}">
                    <source src="{{$listing->link}}" type="video/mp4">
                </video>
            </div>
        @elseif($listing->type == 'hls')
            @php
                $proxiedHls = route('stream.manifest', ['t' => \Illuminate\Support\Facades\Crypt::encryptString($listing->link)]);
            @endphp
            <div class="w-full aspect-video">
                <video id="player" class="video-js w-full h-full vjs-default-skin" data-setup="{}" controls
                       preload="auto" poster="{{$listing->postable->post->coverurl ?? $listing->postable->coverurl ?? ''}}">
                    <source src="{{$proxiedHls}}" type="application/x-mpegURL">
                </video>
            </div>
        @endif

        @push('javascript')
            <script src="{{asset('static/js/player/videojs/js/video.min.js')}}"></script>
            <script src="{{asset('static/js/player/videojs/js/youtube.min.js')}}"></script>
        @endpush
    @else

        @if (strpos($listing->link, 'youtube.com') !== false)
            <div class="w-full aspect-video">
                <div class="plyr__video-embed" id="player">
                    <iframe
                        src="{{$listing->link}}"
                        allow="autoplay; fullscreen; picture-in-picture; encrypted-media"
                        allowfullscreen
                        allowtransparency
                    ></iframe>
                </div>
            </div>
        @elseif($listing->type == 'mp4')
            <div class="w-full aspect-video">
                <video id="player" class="video-js w-full h-full vjs-default-skin" data-setup="{}" controls
                       preload="auto" poster="{{$listing->postable->post->coverurl ?? $listing->postable->coverurl ?? ''}}">
                    <source src="{{$listing->link}}" type="video/mp4">
                    @if(isset($listing->postable->subtitles))
                        @foreach($listing->postable->subtitles as $subtitle)
                            <track kind="captions" label="{{$subtitle->country->name}}" srclang="{{$subtitle->country->code}}" src="{{$subtitle->linkurl}}" />
                        @endforeach
                    @endif
                </video>
            </div>
        @elseif($listing->type == 'hls')
            @php
                $proxiedHls = route('stream.manifest', ['t' => \Illuminate\Support\Facades\Crypt::encryptString($listing->link)]);
            @endphp
            <div class="w-full aspect-video">
                <video id="player" class="video-js w-full h-full vjs-default-skin" data-setup="{}" controls
                       preload="auto" poster="{{$listing->postable->post->coverurl ?? $listing->postable->coverurl ?? ''}}">
                    <source src="{{$proxiedHls}}" type="application/x-mpegURL">
                    @if(isset($listing->postable->subtitles))
                        @foreach($listing->postable->subtitles as $subtitle)
                            <track kind="captions" label="{{$subtitle->country->name}}" srclang="{{$subtitle->country->code}}" src="{{$subtitle->linkurl}}" />
                        @endforeach
                    @endif
                </video>
            </div>
        @endif

        @push('javascript')
            <script src="{{asset('static/js/player/plyr/plyr.js')}}"></script>
            <script src="{{asset('static/js/player/plyr/plyr.hls.js')}}"></script>
            <script>
                const player = new Plyr('#player');
                player.on('ready', function(event) {
                    var instance = event.detail.plyr;

                    var hslSource = null;
                    var sources = instance.media.querySelectorAll('source'),
                        i;
                    for (i = 0; i < sources.length; ++i) {
                        if (sources[i].src.indexOf('.m3u8') > -1 || sources[i].src.indexOf('.txt') > -1 || sources[i].src.indexOf('.ts') > -1) {
                            hslSource = sources[i].src;
                        }
                    }

                    if (hslSource !== null && Hls.isSupported()) {
                        var hls = new Hls();
                        hls.loadSource(hslSource);
                        hls.attachMedia(instance.media);
                        hls.on(Hls.Events.MANIFEST_PARSED, function() {
                        });
                    }
                });
            </script>
        @endpush
    @endif
    @endif
@endsection
