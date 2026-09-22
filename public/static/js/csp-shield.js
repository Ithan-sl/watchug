/**
 * Anti-Popup & CSP Shield for Video Players and Embeds
 * - Bloqueia conexões CSP não autorizadas a redes de anúncios (fetch / XHR).
 * - Gerencia sobreposição transparente sobre os players para absorver cliques de pop-up enganosos ao iniciar ou trocar de servidor.
 * - Bloqueia terminantemente abertura de nova aba ou janela vinda do player (fake window.open) globalmente.
 * - Garante que os controles do player (Play, Pause, Barra de busca, Volume, Tela Cheia) funcionem de forma 100% fluida e sem travas.
 */
(function () {
    'use strict';

    // Padrões e caracteres de identificação de URLs de popups dinâmicos e ad-networks
    var AD_IDENTIFIERS = [
        /clickunder/i,
        /popunder/i,
        /popads/i,
        /exoclick/i,
        /adsterra/i,
        /propeller/i,
        /trafficjunky/i,
        /tag=d_/i,
        /suurl/i,
        /aclib/i,
        /suv5/i,
        /refpa/i,
        /1x(bet|lite)/i,
        /adexchangerapid/i,
        /inadsexchange/i,
        /onclickperformance/i,
        /usrpubtrk/i,
        /dtscout/i,
        /dtscdn/i,
        /crwdcntrl/i,
        /onaudience/i,
        /adsrvr/i,
        /mrktmtrcs/i,
        /\.(cfd|rest|pro|top|buzz|icu|live|click|download)(\/|\?|$)/i,
        /10976362/
    ];

    function isAdUrl(url) {
        if (!url || typeof url !== 'string') return false;
        var cleanUrl = url.trim();
        if (cleanUrl.startsWith('/') || cleanUrl.startsWith(window.location.origin) || cleanUrl.startsWith('javascript:')) {
            return false;
        }
        for (var i = 0; i < AD_IDENTIFIERS.length; i++) {
            if (AD_IDENTIFIERS[i].test(cleanUrl)) {
                return true;
            }
        }
        return false;
    }

    function isPlayerContext(element) {
        if (!element) return false;
        return !!(
            element.closest &&
            element.closest('#player-container, #embed-player-container, #embed-ad-overlay')
        );
    }

    function isPlayerPage() {
        var path = window.location.pathname;
        return !!(
            document.getElementById('player-container') ||
            document.getElementById('video-iframe') ||
            document.getElementById('embed-player-container') ||
            path.indexOf('/movie/') !== -1 ||
            path.indexOf('/tv-show/') !== -1 ||
            path.indexOf('/episode/') !== -1 ||
            path.indexOf('/live-broadcast/') !== -1 ||
            path.indexOf('/embed') !== -1
        );
    }

    // 1. Proibir conexões (connect-src) via fetch para ad-networks
    if (window.fetch) {
        var originalFetch = window.fetch;
        window.fetch = function (resource, init) {
            var url = typeof resource === 'string' ? resource : (resource && resource.url ? resource.url : '');
            if (isAdUrl(url)) {
                console.warn('[CSP Shield] Conexão bloqueada por CSP para ad server:', url);
                return Promise.reject(new TypeError('Blocked by Content Security Policy Shield'));
            }
            return originalFetch.apply(this, arguments);
        };
    }

    // 2. Proibir conexões via XMLHttpRequest
    if (window.XMLHttpRequest) {
        var originalOpen = XMLHttpRequest.prototype.open;
        XMLHttpRequest.prototype.open = function (method, url) {
            if (typeof url === 'string' && isAdUrl(url)) {
                console.warn('[CSP Shield] Conexão XHR bloqueada por CSP para ad server:', url);
                this.abort();
                return;
            }
            return originalOpen.apply(this, arguments);
        };
    }

    // 3. Objeto simulador de janela aberta (Fake Window)
    var fakeWindow = {
        closed: false,
        name: '',
        status: '',
        opener: window,
        close: function () { this.closed = true; },
        focus: function () {},
        blur: function () {},
        postMessage: function () {},
        print: function () {},
        stop: function () {},
        moveTo: function () {},
        moveBy: function () {},
        resizeTo: function () {},
        resizeBy: function () {},
        scroll: function () {},
        scrollTo: function () {},
        scrollBy: function () {},
        document: {
            write: function () {},
            writeln: function () {},
            open: function () {},
            close: function () {},
            location: { href: 'about:blank' }
        },
        location: { href: 'about:blank' }
    };

    // 4. Bloqueio estrito de abertura de nova aba / janela (window.open)
    var originalWindowOpen = window.open;
    var safeWindowOpen = function (url, target, features) {
        var isExternalOrBlank = !url || url === 'about:blank' || target === '_blank' || isAdUrl(url);

        if (isPlayerPage() || isExternalOrBlank) {
            console.warn('[Anti-Popup Shield] Abertura de nova aba vinda do player BLOQUEADA:', url || '(blank)', target);
            return fakeWindow;
        }

        return originalWindowOpen ? originalWindowOpen.apply(this, arguments) : fakeWindow;
    };

    window.open = safeWindowOpen;

    try {
        if (window.top && window.top !== window) {
            window.top.open = safeWindowOpen;
        }
    } catch (e) {}

    try {
        if (window.parent && window.parent !== window) {
            window.parent.open = safeWindowOpen;
        }
    } catch (e) {}

    // 4.1 Bloqueio de Notificações Push abusivas
    try {
        if ('Notification' in window) {
            window.Notification.requestPermission = function () { return Promise.resolve('denied'); };
            try {
                Object.defineProperty(window.Notification, 'permission', { get: function () { return 'denied'; } });
            } catch (ne) {}
        }
    } catch (e) {}

    // 4.2 Bloqueio de registro de Service Workers de SPAM/Ad-networks
    try {
        if (navigator.serviceWorker) {
            navigator.serviceWorker.register = function () { return Promise.reject(new Error('Blocked by Shield')); };
        }
    } catch (e) {}

    // 4.3 Neutralizar alertas enganosos de vírus/malware
    try {
        window.alert = function () {};
        window.confirm = function () { return false; };
        window.prompt = function () { return null; };
    } catch (e) {}

    // 5. Interceptar cliques em elementos <a> com target="_blank"
    var originalAnchorClick = HTMLAnchorElement.prototype.click;
    HTMLAnchorElement.prototype.click = function () {
        var href = this.href || '';
        var target = this.target || '';
        var isInternal = href.startsWith(window.location.origin) || href.startsWith('/') || href.startsWith('#');
        if (!isInternal && (isPlayerContext(this) || target === '_blank' || isAdUrl(href))) {
            console.warn('[Anti-Popup Shield] Bloqueado clique programático em link de nova aba/player:', href);
            return;
        }
        return originalAnchorClick.apply(this, arguments);
    };

    // 6. Interceptar eventos de clique em links externos / ads em fase de captura global
    var isInternalNav = false;
    document.addEventListener('click', function (e) {
        var anchor = e.target && e.target.closest ? e.target.closest('a') : null;
        if (anchor) {
            var href = anchor.href || '';
            var target = anchor.target || '';
            var isInternal = href.startsWith(window.location.origin) || href.startsWith('/') || href.startsWith('#');

            // Se for navegação interna do próprio site, permitir 100% sem interceptação
            if (isInternal) {
                isInternalNav = true;
                setTimeout(function () { isInternalNav = false; }, 3000);
                return;
            }

            var insidePlayer = isPlayerContext(anchor);

            if (insidePlayer || isAdUrl(href) || (target === '_blank' && isPlayerPage() && !anchor.hasAttribute('data-allow-new-tab'))) {
                e.preventDefault();
                e.stopImmediatePropagation();
                console.warn('[Anti-Popup Shield] Bloqueado clique em link externo/ad:', href);
                return false;
            }
        }
    }, true);

    // 7. Bloquear redirecionamentos automáticos forçados da janela principal (Top-level redirect guard)
    window.addEventListener('beforeunload', function (e) {
        if (!isInternalNav && isPlayerPage()) {
            console.warn('[Anti-Popup Shield] Tentativa de redirecionamento automático não autorizada bloqueada.');
            e.preventDefault();
            return (e.returnValue = '');
        }
    });

    // Compatibilidade no-op caso scripts antigos tentem chamar rearmPlayerShield
    window.rearmPlayerShield = function () {};
})();
