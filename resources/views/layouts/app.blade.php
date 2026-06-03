<!DOCTYPE html>
<html class="scroll-smooth" lang="pt-BR">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <meta name="color-scheme" content="light">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @auth
        <meta name="sbar-user-full-name" content="{{ auth()->user()->name }}">
    @endauth
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/noty/noty.css' ) }}"/>
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/noty/themes/nest.css' ) }}"/>
    <link rel="shortcut icon" type="image/x-icon" href="{{ secure_asset( 'images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/fontawesome-free-6.3.0-web/css/all.min.css' ) }}"/>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css"/>
    <!-- Add Montserrat font from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .font-montserrat {
            font-family: 'Montserrat', sans-serif;
        }
        /* Top progress bar */
        #page-progress {
            position: fixed;
            top: 0; left: 0;
            height: 3px;
            width: 0;
            background: #60a5fa;
            z-index: 99999;
            transition: width 0.2s ease, opacity 0.3s ease;
            opacity: 0;
        }
        #page-progress.active {
            opacity: 1;
            animation: progress-indeterminate 1.4s ease infinite;
        }
        @keyframes progress-indeterminate {
            0%   { width: 0;    margin-left: 0; }
            50%  { width: 60%;  margin-left: 20%; }
            100% { width: 0;    margin-left: 100%; }
        }
        [x-cloak] { display: none !important; }
    </style>
    <!-- Allow pages to push scripts/styles into head -->
    @stack('head')
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/patient-modal.js', 'resources/js/chat-component-global.js'])
    <title>{{ env( 'APP_NAME' ) }}</title>
    {{-- PWA manifest — crossorigin="use-credentials" required for auth-protected origins (HTTPS) --}}
    <link rel="manifest" href="/manifest.json" crossorigin="use-credentials">

    {{-- Theme & branding --}}
    <meta name="theme-color" content="#004D9D">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="application-name" content="Plantão">

    {{-- Apple / iOS PWA --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Plantão">
    <link rel="apple-touch-icon" href="/images/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="192x192" href="/images/icons/icon-192x192.png">
    <link rel="icon" type="image/png" sizes="512x512" href="/images/icons/icon-512x512.png">

    {{-- iOS splash screens (displayed while app is loading from home screen) --}}
    <link rel="apple-touch-startup-image" media="screen and (device-width: 430px) and (device-height: 932px) and (-webkit-device-pixel-ratio: 3)" href="/images/icons/splash-1242x2688.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 393px) and (device-height: 852px) and (-webkit-device-pixel-ratio: 3)" href="/images/icons/splash-1242x2688.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-828x1792.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 414px) and (device-height: 896px) and (-webkit-device-pixel-ratio: 3)" href="/images/icons/splash-1242x2688.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 390px) and (device-height: 844px) and (-webkit-device-pixel-ratio: 3)" href="/images/icons/splash-1242x2688.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 375px) and (device-height: 812px) and (-webkit-device-pixel-ratio: 3)" href="/images/icons/splash-1125x2436.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 414px) and (device-height: 736px) and (-webkit-device-pixel-ratio: 3)" href="/images/icons/splash-1242x2208.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 375px) and (device-height: 667px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-750x1334.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 320px) and (device-height: 568px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-640x1136.png">
    {{-- iPad --}}
    <link rel="apple-touch-startup-image" media="screen and (device-width: 768px) and (device-height: 1024px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-1536x2048.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 834px) and (device-height: 1112px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-1668x2224.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 834px) and (device-height: 1194px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-1668x2388.png">
    <link rel="apple-touch-startup-image" media="screen and (device-width: 1024px) and (device-height: 1366px) and (-webkit-device-pixel-ratio: 2)" href="/images/icons/splash-2048x2732.png">

    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('/serviceworker.js', { scope: '/' });
        }
    </script>
</head>
<body class="flex flex-col h-screen overflow-hidden text-gray-800 bg-gray-300 antialiased">
<div id="page-progress"></div>
<!-- NAVBAR (inclui menu mobile integrado) -->
<header class="sticky top-0 z-40 w-full bg-white shadow-md">
    @include('shared.navbar')
</header>
<!-- PRINCIPAL -->
<main class="flex-1 overflow-y-auto bg-gray-50 pt-2 flex flex-col">
    <div class="relative py-2 md:py-4 flex justify-center flex-1">
        <div class="w-full max-w-full relative px-2 md:px-4">
            <div class="w-full">
                @yield('content')
            </div>
        </div>
    </div>
    @include('shared.footer')
</main>

<!-- PWA Install Button -->
<div id="pwa-install-banner"
     class="hidden fixed top-16 right-4 z-50 w-80 sm:w-96"
     role="complementary"
     aria-label="Instalar aplicativo">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-3 py-3">
            <div class="flex-shrink-0">
                <img src="/images/logo-santacasa-app.png"
                     alt="Passagem de Plantão"
                     class="w-10 h-10 rounded-xl shadow-sm">
            </div>
            <div class="flex-1">
                <p class="text-sm font-bold text-gray-900 leading-tight">Passagem de Plantão</p>
                <p class="text-xs text-gray-500 leading-tight mt-0.5">Adicionar à tela inicial como app</p>
            </div>
            <button id="pwa-dismiss-btn"
                    type="button"
                    aria-label="Dispensar"
                    class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100
                           transition-colors duration-150 focus:outline-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-3 pb-3">
            <button id="pwa-install-btn"
                    type="button"
                    class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                           bg-[#004D9D] hover:bg-[#003d7a] active:bg-[#002d5a]
                           text-white text-sm font-semibold
                           shadow-sm transition-colors duration-150
                           focus:outline-none focus:ring-2 focus:ring-[#004D9D]/50">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                </svg>
                Instalar Aplicativo
            </button>
        </div>
    </div>
</div>

@auth
@if(auth()->user()->handoverBeds()->doesntExist())
<!-- Aviso: configurar leitos -->
<div id="beds-notice-banner"
     class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-50 w-[calc(100%-2rem)] max-w-sm"
     role="dialog"
     aria-label="Configurar leitos">
    <div class="bg-white rounded-2xl shadow-2xl border border-blue-100 overflow-hidden">
        <div class="flex items-start gap-3 px-4 py-3">
            <div class="flex-shrink-0 w-9 h-9 rounded-xl bg-[#004D9D]/10 flex items-center justify-center mt-0.5">
                <i class="fas fa-bed text-[#004D9D] text-sm"></i>
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-semibold text-gray-900 leading-snug">Configure seus leitos</p>
                <p class="text-xs text-gray-500 mt-0.5 leading-snug">Configure seus leitos para personalizar a visualização da passagem de plantão.</p>
            </div>
            <button id="beds-notice-dismiss"
                    type="button"
                    aria-label="Dispensar"
                    class="flex-shrink-0 p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors focus:outline-none">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <div class="px-4 pb-3">
            <a href="{{ route('user.preferences.index') }}"
               class="w-full inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl
                      bg-[#004D9D] hover:bg-[#003d7a] active:bg-[#002d5a]
                      text-white text-sm font-semibold
                      shadow-sm transition-colors duration-150
                      focus:outline-none focus:ring-2 focus:ring-[#004D9D]/50">
                <i class="fas fa-sliders text-sm"></i>
                Configurar Leitos
            </a>
        </div>
    </div>
</div>
<script>
(function () {
    const DISMISSED_KEY = 'beds_notice_dismissed';
    const banner = document.getElementById('beds-notice-banner');
    const dismiss = document.getElementById('beds-notice-dismiss');
    if (!banner) return;
    if (!sessionStorage.getItem(DISMISSED_KEY)) {
        setTimeout(() => {
            banner.classList.remove('hidden');
            banner.classList.add('animate-[modal-slide-in_0.3s_ease-out]');
        }, 1500);
    }
    if (dismiss) {
        dismiss.addEventListener('click', function () {
            sessionStorage.setItem(DISMISSED_KEY, '1');
            banner.classList.add('hidden');
        });
    }
})();
</script>
@endif
@endauth

<!-- Scripts -->
<script type="text/javascript" src="{{ asset('js/jquery.js') }}"></script>
<script src="{{ asset('/js/common.js' . preventCache()) }}"></script>
<script src="{{ asset('/vendor/noty/noty.min.js') }}"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>
@stack('scripts')
<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Welcome modal logic
        const welcomeShown = localStorage.getItem('sbar_welcome_shown');
        if (welcomeShown) {
            const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
            if (welcomeElement && welcomeElement.__x) {
                welcomeElement.__x.$data.showWelcome = false;
            }
        }
        // ── Progress bar controller ──
        const bar = document.getElementById('page-progress');
        function showProgress() { if (bar) bar.classList.add('active'); }
        function hideProgress() {
            if (!bar) return;
            bar.classList.remove('active');
        }

        // ── Session expiry: redireciona para login quando a sessão expirar ──
        @auth
        (function() {
            const expiresAt = {{ now()->addMinutes((int) config('session.lifetime'))->timestamp }} * 1000;
            const msUntilExpiry = expiresAt - Date.now();
            if (msUntilExpiry > 0) {
                setTimeout(() => { window.location.href = '{{ route('login') }}'; }, msUntilExpiry);
            }

            // Intercepta fetch para detectar 419 (CSRF/sessão expirada) e 401
            const _fetch = window.fetch;
            window.fetch = function() {
                return _fetch.apply(this, arguments).then(function(response) {
                    if (response.status === 419 || response.status === 401) {
                        window.location.href = '{{ route('login') }}';
                    }
                    return response;
                });
            };
        })();
        @endauth

        // Livewire commits (SBAR e outros componentes)
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ succeed, fail }) => {
                showProgress();
                succeed(() => hideProgress());
                fail(() => hideProgress());
            });
        });

        // Navegação normal (links e formulários)
        document.addEventListener('click', function (e) {
            const a = e.target.closest('a[href]');
            if (!a) return;
            const href = a.getAttribute('href');
            if (!href || href.startsWith('#') || href.startsWith('javascript') || a.target === '_blank') return;
            if (a.hostname && a.hostname !== location.hostname) return;
            showProgress();
        });
        document.addEventListener('submit', function (e) {
            if (e.target.tagName === 'FORM') showProgress();
        });

        // Compatibilidade com eventos manuais existentes
        window.addEventListener('sbar:loading:show', showProgress);
        window.addEventListener('sbar:loading:hide', hideProgress);
        window.addEventListener('load', hideProgress);
    });

    // Notificações Toast
    window.showToast = function(message, type = 'success') {
        const theme = type === 'success' ? 'alert' : (type === 'error' ? 'error' : 'info');
        const bgColor = type === 'success' ? '#28a745' : (type === 'error' ? '#dc3545' : '#004D9D');
        
        new Noty({
            text: message,
            type: theme,
            theme: 'nest',
            layout: 'topRight',
            timeout: 3000,
            progressBar: true,
            closeWith: ['click', 'button'],
            animation: {
                open: 'noty_effects_open',
                close: 'noty_effects_close'
            },
            callbacks: {
                onTemplate: function() {
                    this.barDom.style.backgroundColor = bgColor;
                }
            }
        }).show();
    };

    // Notificações de sessão do Laravel
    @if(session('success'))
        showToast('{!! addslashes(session('success')) !!}', 'success');
    @endif
    @if(session('error'))
        showToast('{!! addslashes(session('error')) !!}', 'error');
    @endif
    @if(session('warning'))
        showToast('{!! addslashes(session('warning')) !!}', 'warning');
    @endif
    @if(session('info'))
        showToast('{!! addslashes(session('info')) !!}', 'info');
    @endif

    // Notificações do Livewire
    document.addEventListener('livewire:init', () => {
        Livewire.on('show-toast', (data) => {
            const event = Array.isArray(data) ? data[0] : data;
            showToast(event.message, event.type || 'success');
        });
    });
    // ─── PWA Install Prompt ───────────────────────────────────────────────────
    (function() {
        let deferredPrompt = null;
        const banner    = document.getElementById('pwa-install-banner');
        const installBtn= document.getElementById('pwa-install-btn');
        const dismissBtn= document.getElementById('pwa-dismiss-btn');
        const DISMISSED_KEY = 'pwa_install_dismissed_at';
        const DISMISS_TTL   = 7 * 24 * 60 * 60 * 1000; // 7 dias

        function showBanner() {
            const dismissed = localStorage.getItem(DISMISSED_KEY);
            if (dismissed && Date.now() - parseInt(dismissed) < DISMISS_TTL) return;
            if (banner) {
                banner.classList.remove('hidden');
                banner.classList.add('animate-[modal-slide-in_0.3s_ease-out]');
            }
        }

        function hideBanner() {
            if (banner) banner.classList.add('hidden');
        }

        window.addEventListener('beforeinstallprompt', function(e) {
            e.preventDefault();
            deferredPrompt = e;
            showBanner();
        });

        window.addEventListener('appinstalled', function() {
            hideBanner();
            showToast('App instalado com sucesso! Acesse pelo ícone na tela inicial.', 'success');
        });

        if (installBtn) {
            installBtn.addEventListener('click', function() {
                if (!deferredPrompt) return;
                hideBanner();
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choice) {
                    if (choice.outcome === 'dismissed') {
                        showToast('Instalação cancelada.', 'info');
                    }
                    deferredPrompt = null;
                });
            });
        }

        if (dismissBtn) {
            dismissBtn.addEventListener('click', function() {
                localStorage.setItem(DISMISSED_KEY, Date.now().toString());
                hideBanner();
            });
        }

        // Service Worker: verificar atualização
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.ready.then(function(reg) {
                reg.addEventListener('updatefound', function() {
                    const newWorker = reg.installing;
                    if (!newWorker) return;
                    newWorker.addEventListener('statechange', function() {
                        if (newWorker.state === 'installed' && navigator.serviceWorker.controller) {
                            console.log('[SW] Nova versão disponível');
                            newWorker.postMessage({ type: 'SKIP_WAITING' });
                        }
                    });
                });
            });
        }
    })();

    // Form submission prevention
    if (typeof $ !== 'undefined') {
        $(document).ready(function(){
            $("form").submit(function(){
                setTimeout(function() {
                    $('input').attr('disabled', 'disabled');
                    $('button').attr('disabled', 'disabled');
                    $('a').attr('disabled', 'disabled');
                }, 50);
            });
        });
    }
</script>

@auth
<script>
(function () {
    // Session heartbeat — pings the server every 10 minutes to keep the
    // Redis session alive on shared/idle tablets during a 6-hour shift.
    const INTERVAL_MS  = 10 * 60 * 1000; // 10 minutes
    const WARN_BEFORE  = 15 * 60 * 1000; // warn 15 min before expiry
    const SESSION_TTL  = {{ config('session.lifetime') * 60 * 1000 }}; // ms
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    let lastActivity = Date.now();

    function ping() {
        fetch('{{ route('session.heartbeat') }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        }).catch(() => {});
    }

    // Track user activity to avoid unnecessary pings when truly idle
    ['click','keydown','touchstart','scroll'].forEach(ev => {
        document.addEventListener(ev, () => { lastActivity = Date.now(); }, { passive: true });
    });

    setInterval(() => {
        // Only ping if there was activity in the last interval
        if (Date.now() - lastActivity < INTERVAL_MS + 5000) {
            ping();
        }
    }, INTERVAL_MS);

    // Session expiry warning toast
    const warnAt = SESSION_TTL - WARN_BEFORE;
    if (warnAt > 0) {
        setTimeout(() => {
            if (typeof noty !== 'undefined') {
                noty({
                    text: '<strong>Sessão expirando em 15 minutos.</strong><br>Se desejar continuar, clique em qualquer área da página.',
                    type: 'warning',
                    layout: 'bottomRight',
                    timeout: 10000,
                });
            }
        }, warnAt);
    }
})();
</script>
@endauth

@auth
<x-ui.connection-status />
@endauth
</body>
</html>
