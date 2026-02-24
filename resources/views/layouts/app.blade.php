<!DOCTYPE html>
<html class="scroll-smooth" lang="pt-BR">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
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
        /* Loading Overlay Styles */
        .sbar-loading-overlay {
            position: fixed;
            inset: 0;
            z-index: 99999;
            display: none;
            align-items: center;
            justify-content: center;
            background-color: rgba(0, 77, 157, 0.3);
            backdrop-filter: blur(4px);
        }
        .sbar-loading-overlay.active {
            display: flex;
        }
        body.sbar-loading-active {
            overflow: hidden;
        }
    </style>
    <!-- Allow pages to push scripts/styles into head -->
    @stack('head')
    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/patient-modal.js', 'resources/js/chat-component-global.js'])
    <title>{{ env( 'APP_NAME' ) }}</title>
    @laravelPWA
</head>
<body class="flex flex-col h-screen text-gray-800 bg-gray-300 antialiased">
<!-- NAVBAR (inclui menu mobile integrado) -->
<header class="sticky top-0 z-40 w-full bg-white shadow-md">
    @include('partials.navbar')
</header>
<!-- PRINCIPAL -->
<main class="flex-grow bg-gray-50 pt-2">
    <div class="relative py-2 flex justify-center">
        <div class="w-full max-w-full relative px-1 lg:px-2">
            <div class="items-center flex flex-wrap">
                @yield('content')
            </div>
        </div>
    </div>
</main>
@include('partials.footer')

<!-- PWA Install Button -->
<div id="pwa-install-banner"
     class="hidden fixed bottom-4 left-1/2 -translate-x-1/2 z-50
            sm:left-auto sm:translate-x-0 sm:right-4
            w-[calc(100%-2rem)] sm:w-auto sm:min-w-max"
     role="complementary"
     aria-label="Instalar aplicativo">
    <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 overflow-hidden">
        <div class="flex items-center gap-3 px-4 py-3 sm:px-5 sm:py-3.5">
            <div class="flex-shrink-0">
                <img src="/images/icons/icon-72x72.png"
                     alt="Passagem de Plantão"
                     class="w-10 h-10 rounded-xl shadow-sm">
            </div>
            <div class="flex-1 min-w-0">
                <p class="text-sm font-bold text-gray-900 leading-tight whitespace-nowrap">Passagem de Plantão</p>
                <p class="text-xs text-gray-500 leading-tight whitespace-nowrap">Instalar como app no dispositivo</p>
            </div>
            <div class="flex items-center gap-2 flex-shrink-0">
                <button id="pwa-install-btn"
                        type="button"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl
                               bg-[#004D9D] hover:bg-[#003d7a] active:bg-[#002d5a]
                               text-white text-xs font-semibold
                               shadow-sm transition-colors duration-150
                               focus:outline-none focus:ring-2 focus:ring-[#004D9D]/50">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                    </svg>
                    <span>Instalar</span>
                </button>
                <button id="pwa-dismiss-btn"
                        type="button"
                        aria-label="Dispensar"
                        class="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100
                               transition-colors duration-150 focus:outline-none focus:ring-2 focus:ring-gray-300">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>
        </div>
    </div>
</div>

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
        // Global Loading Overlay Controller
        const overlay = document.getElementById('sbar-loading-overlay');
        let loadingCount = 0;
        function showLoading() {
            loadingCount++;
            if (overlay) {
                overlay.classList.add('active');
                document.body.classList.add('sbar-loading-active');
            }
        }
        function hideLoading() {
            loadingCount = Math.max(0, loadingCount - 1);
            if (loadingCount === 0 && overlay) {
                overlay.classList.remove('active');
                document.body.classList.remove('sbar-loading-active');
            }
        }
        // Livewire hooks (Livewire 3)
        document.addEventListener('livewire:navigating', showLoading);
        document.addEventListener('livewire:navigated', hideLoading);
        // Livewire wire:loading events
        document.addEventListener('livewire:init', () => {
            Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                showLoading();
                succeed(() => {
                    hideLoading();
                });
                fail(() => {
                    hideLoading();
                });
            });
        });
        // Fallback: Manual event listeners
        window.addEventListener('sbar:loading:show', showLoading);
        window.addEventListener('sbar:loading:hide', hideLoading);
        // Auto-hide on page load
        window.addEventListener('load', hideLoading);
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

        window.addEventListener('appinstalled', hideBanner);

        if (installBtn) {
            installBtn.addEventListener('click', function() {
                if (!deferredPrompt) return;
                hideBanner();
                deferredPrompt.prompt();
                deferredPrompt.userChoice.then(function(choice) {
                    if (choice.outcome === 'accepted') {
                        console.log('[PWA] Usuário aceitou a instalação');
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
</body>
</html>
