@extends('layouts.app')

@section('content')
    @push('head')
        <link rel="preload" href="{{ asset('css/app.css') }}" as="style">
        <link rel="prefetch" href="{{ route('sbar.report') }}" as="document">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <meta name="description" content="Sistema SBAR - Passagem de Plantão Digital">
        <meta name="format-detection" content="telephone=no">

        <script>
            window.sbarDashboard = function() {
                return {
                    autoRefreshEnabled: false,
                    nextRefreshIn: 60,
                    interval: 60,
                    isRefreshing: false,
                    countdownTimer: null,

                    init() {
                        // Restaura estado do localStorage
                        const saved = localStorage.getItem('sbar_auto_refresh_enabled');
                        if (saved !== null) {
                            this.autoRefreshEnabled = saved === 'true';
                        }

                        if (this.autoRefreshEnabled) {
                            this.startCountdown();
                        }

                        // Listener para quando os pacientes são carregados
                        window.addEventListener('sbar:patients-loaded', () => {
                            this.isRefreshing = false;
                            if (this.autoRefreshEnabled) {
                                this.nextRefreshIn = this.interval;
                                this.startCountdown();
                            }
                        });
                    },

                    startCountdown() {
                        this.stopCountdown();

                        this.countdownTimer = setInterval(() => {
                            this.nextRefreshIn--;

                            if (this.nextRefreshIn <= 0) {
                                this.triggerRefresh();
                            }
                        }, 1000);
                    },

                    stopCountdown() {
                        if (this.countdownTimer) {
                            clearInterval(this.countdownTimer);
                            this.countdownTimer = null;
                        }
                    },

                    triggerRefresh() {
                        this.stopCountdown();
                        this.isRefreshing = true;

                        // Dispara o evento Livewire
                        Livewire.dispatch('autoRefresh');
                    },

                    toggleAutoRefresh() {
                        this.autoRefreshEnabled = !this.autoRefreshEnabled;

                        // Salva no localStorage
                        localStorage.setItem('sbar_auto_refresh_enabled', this.autoRefreshEnabled);

                        if (this.autoRefreshEnabled) {
                            this.nextRefreshIn = this.interval;
                            this.startCountdown();
                        } else {
                            this.stopCountdown();
                            this.nextRefreshIn = this.interval;
                        }
                    },

                    handleAutoRefresh() {
                        this.isRefreshing = false;
                        if (this.autoRefreshEnabled) {
                            this.nextRefreshIn = this.interval;
                            this.startCountdown();
                        }
                    }
                };
            };
        </script>
    @endpush

    <div id="sbar-loading-overlay" class="sbar-loading-overlay">
        <div class="bg-white rounded-xl shadow-2xl p-8 flex flex-col items-center space-y-4">
            <!-- Spinner -->
            <div class="relative w-16 h-16">
                <div class="absolute inset-0 border-4 border-gray-200 rounded-full"></div>
                <div class="absolute inset-0 border-4 border-t-[#004D9D] border-transparent rounded-full animate-spin"></div>
            </div>
            <p class="text-gray-700 font-medium">Carregando...</p>
        </div>
    </div>

    <style>
        /* optional: lock page while overlay active */
        body.sbar-loading-active { overflow: hidden; }
    </style>

    <div class="w-full my-2 text-[#004D9D] no-zoom">
        @livewire('sbar-report', [], key('sbar-report-' . now()->timestamp))
    </div>
    <script>
        (function () {
            const overlay = document.getElementById('modal-global-loading');

            function showGlobalLoading(show = true) {
                if (!overlay) return;
                overlay.classList.toggle('hidden', !show);
                overlay.classList.toggle('flex', show);
                document.body.classList.toggle('sbar-loading-active', show);
                overlay.setAttribute('aria-hidden', show ? 'false' : 'true');
            }

            // Livewire lifecycle events (native)
            document.addEventListener('livewire:loading', () => showGlobalLoading(true));
            document.addEventListener('livewire:finished', () => showGlobalLoading(false));
            document.addEventListener('livewire:load', () => showGlobalLoading(false));

            // Custom browser event fallback (server-side dispatchBrowserEvent or manual JS)
            window.addEventListener('sbar:loading', (ev) => {
                showGlobalLoading(Boolean(ev?.detail?.show));
            });

            // Fallback for older Livewire versions: listen to emitted events via Livewire.on
            if (window.Livewire && typeof window.Livewire.on === 'function') {
                try {
                    window.Livewire.on('sbar:loading', (payload = {}) => {
                        showGlobalLoading(Boolean(payload.show));
                    });
                    window.Livewire.on('sbar:patients-loaded', () => {
                        showGlobalLoading(false);
                    });
                } catch (e) {
                    // silent fallback
                }
            }

            // ensure overlay hidden on initial page ready
            if (document.readyState === 'complete') {
                showGlobalLoading(false);
            } else {
                window.addEventListener('load', () => showGlobalLoading(false));
            }
        })();
    </script>
@endsection


