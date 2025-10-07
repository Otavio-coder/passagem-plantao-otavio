{{-- resources/views/sbar-loader.blade.php --}}
@extends('layouts.app')

@section('content')
    @push('head')
        <link rel="preload" href="{{ asset('css/app.css') }}" as="style">
        <link rel="prefetch" href="{{ route('sbar.report') }}" as="document">
        <meta name="viewport" content="width=device-width, initial-scale=1.0, user-scalable=no">
        <meta name="description" content="Sistema SBAR - Passagem de Plantão Digital">
        <meta name="format-detection" content="telephone=no">
        
        <style>
            @media (min-width: 1920px) {
                .font-montserrat { font-size: 1.1rem; }
            }
            .no-zoom { touch-action: pan-x pan-y; -ms-touch-action: pan-x pan-y; }
            .patient-card { will-change: transform; }
            .loading-optimized { contain: layout; transform: translateZ(0); }
            
            /* Estilos específicos para dashboard mode */
            .dashboard-mode { cursor: none; user-select: none; -webkit-user-select: none; -moz-user-select: none; -ms-user-select: none; }
            .dashboard-mode .patient-card:hover { transform: scale(1.02); }
            .dashboard-mode * { 
                -webkit-touch-callout: none; 
                -webkit-user-select: none; 
                -khtml-user-select: none; 
                -moz-user-select: none; 
                -ms-user-select: none; 
                user-select: none; 
            }
            
            @media (min-width: 1920px) {
                .dashboard-mode { --tw-text-opacity: 1; color: rgb(0 0 0 / var(--tw-text-opacity)); }
                .dashboard-mode .bg-white { background-color: rgb(255 255 255); box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1); }
            }
        </style>
    @endpush

    <div class="w-full my-2 text-[#004D9D] relative no-zoom"
         x-data="sbarDashboard()"
         x-init="init()">

        {{-- Livewire component: mantém o key para forçar reload quando necessário --}}
        @livewire('sbar-report', [], key('sbar-report-' . now()->timestamp))

    </div>

    <script>
    window.sbarDashboard = function() {
        return {
            autoRefreshEnabled: false,
            nextRefreshIn: 600,
            autoRefreshInterval: null,
            countdownInterval: null,
            modalOpen: false,
            
            init() {
                // Inicialização simples
                this.autoRefreshEnabled = localStorage.getItem('sbar_autorefresh') === 'true';
                if (this.autoRefreshEnabled) {
                    this.startAutoRefresh();
                }
            },
            
            toggleAutoRefresh() {
                this.autoRefreshEnabled = !this.autoRefreshEnabled;
                localStorage.setItem('sbar_autorefresh', this.autoRefreshEnabled);
                
                if (this.autoRefreshEnabled) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            },
            
            startAutoRefresh() {
                this.stopAutoRefresh(); // Limpa intervalos anteriores
                this.nextRefreshIn = 600; // 10 minutos
                
                // Countdown
                this.countdownInterval = setInterval(() => {
                    if (this.modalOpen) return;
                    this.nextRefreshIn--;
                    if (this.nextRefreshIn <= 0) {
                        this.nextRefreshIn = 600;
                    }
                }, 1000);
                
                // Auto-refresh
                this.autoRefreshInterval = setInterval(() => {
                    if (!this.modalOpen) {
                        this.nextRefreshIn = 600;
                        Livewire.dispatch('refreshData');
                    }
                }, 600000); // 10 minutos
            },
            
            stopAutoRefresh() {
                if (this.autoRefreshInterval) {
                    clearInterval(this.autoRefreshInterval);
                    this.autoRefreshInterval = null;
                }
                if (this.countdownInterval) {
                    clearInterval(this.countdownInterval);
                    this.countdownInterval = null;
                }
            },
            
            handleAutoRefresh() {
                this.nextRefreshIn = 600;
            }
        };
    };
    </script>
    <script>
        /**
         * SBAR Auto-Scroll Final - Versão Produção
         * Versão: 6.0 - Sem logs, scroll limitado 5%-95%, indicador flutuante
         */

        (function() {
            'use strict';

            // ===========================================
            // CONFIGURAÇÕES GLOBAIS
            // ===========================================
            const SBAR_SYSTEM = {
                // Dashboard
                isDashboardMode: false,
                
                // Auto-scroll (configurações fixas)
                autoScrollActive: false,
                autoScrollInterval: null,
                scrollDelay: 30,
                scrollStep: 1,
                currentScrollPosition: 0,
                maxScrollPosition: 0,
                minScrollPosition: 0,     // 5% do scroll total
                maxScrollLimit: 0,        // 95% do scroll total
                isScrollingUp: false,
                
                // Auto-refresh
                autoRefreshEnabled: false,
                autoRefreshInterval: null,
                nextRefreshIn: 600,
                countdownInterval: null,
                modalOpen: false
            };

            // ===========================================
            // FUNÇÕES DO DASHBOARD
            // ===========================================
            function detectDashboardMode() {
                try {
                    const urlParams = new URLSearchParams(window.location.search);
                    const isDashboard = urlParams.get('dashboard') === 'true';
                    const isAutoRefresh = urlParams.get('autorefresh') === 'true';
                    const isFullscreen = urlParams.get('fullscreen') === 'true';
                    
                    SBAR_SYSTEM.isDashboardMode = isDashboard || isAutoRefresh || isFullscreen;
                    
                    if (SBAR_SYSTEM.isDashboardMode) {
                        document.body.classList.add('dashboard-mode');
                        showDashboardIndicator();
                    }
                } catch (e) {}
            }

            function optimizeForDashboard() {
                if (!SBAR_SYSTEM.isDashboardMode) return;
                
                // Wake lock
                if ('wakeLock' in navigator) {
                    navigator.wakeLock.request('screen').catch(() => {});
                }
                
                // Previne sleep
                setInterval(() => {
                    if (SBAR_SYSTEM.isDashboardMode) {
                        document.body.style.transform = 'translateX(0.1px)';
                        setTimeout(() => document.body.style.transform = 'translateX(0px)', 100);
                    }
                }, 30000);
                
                // Força altura mínima para garantir scroll
                forceScrollableHeight();
            }

            function toggleDashboardMode() {
                if (SBAR_SYSTEM.isDashboardMode) {
                    SBAR_SYSTEM.isDashboardMode = false;
                    document.body.classList.remove('dashboard-mode');
                    stopAutoRefresh();
                    stopAutoScroll();
                    hideDashboardIndicator();
                    
                    // Remove altura forçada
                    document.body.style.minHeight = '';
                    
                    if (document.fullscreenElement) {
                        try {
                            document.exitFullscreen();
                        } catch (e) {}
                    }
                } else {
                    SBAR_SYSTEM.isDashboardMode = true;
                    document.body.classList.add('dashboard-mode');
                    optimizeForDashboard();
                    enableAutoRefresh();
                    showDashboardIndicator();
                    
                    // Inicia auto-scroll automaticamente após um delay
                    setTimeout(() => {
                        startAutoScrollAutomatically();
                    }, 2000);
                }
            }

            function enableAutoRefresh() {
                if (SBAR_SYSTEM.autoRefreshEnabled) return;
                SBAR_SYSTEM.autoRefreshEnabled = true;
                
                try {
                    localStorage.setItem('sbar_auto_refresh', 'true');
                } catch (e) {}
                
                SBAR_SYSTEM.nextRefreshIn = 600;
                
                SBAR_SYSTEM.autoRefreshInterval = setInterval(() => {
                    if (SBAR_SYSTEM.modalOpen) return;
                    
                    try {
                        if (window.Livewire && typeof window.Livewire.emit === 'function') {
                            window.Livewire.emit('autoRefresh');
                        }
                    } catch (err) {}
                    
                    SBAR_SYSTEM.nextRefreshIn = 600;
                }, 600000);
                
                SBAR_SYSTEM.countdownInterval = setInterval(() => {
                    if (SBAR_SYSTEM.nextRefreshIn > 0) {
                        SBAR_SYSTEM.nextRefreshIn--;
                    }
                }, 1000);
            }

            function stopAutoRefresh() {
                SBAR_SYSTEM.autoRefreshEnabled = false;
                
                try {
                    localStorage.setItem('sbar_auto_refresh', 'false');
                } catch (e) {}
                
                if (SBAR_SYSTEM.autoRefreshInterval) {
                    clearInterval(SBAR_SYSTEM.autoRefreshInterval);
                    SBAR_SYSTEM.autoRefreshInterval = null;
                }
                
                if (SBAR_SYSTEM.countdownInterval) {
                    clearInterval(SBAR_SYSTEM.countdownInterval);
                    SBAR_SYSTEM.countdownInterval = null;
                }
            }

            function toggleAutoRefresh() {
                if (SBAR_SYSTEM.autoRefreshEnabled) {
                    stopAutoRefresh();
                } else {
                    enableAutoRefresh();
                }
            }

            // ===========================================
            // INDICADOR FLUTUANTE DO DASHBOARD
            // ===========================================
            function createDashboardIndicator() {
                if (document.getElementById('sbar-dashboard-indicator')) return;
                
                const indicator = document.createElement('div');
                indicator.id = 'sbar-dashboard-indicator';
                indicator.innerHTML = `
                    <div class="flex items-center space-x-2 bg-green-500 text-white px-4 py-2 rounded-lg shadow-lg">
                        <div class="w-3 h-3 bg-green-200 rounded-full animate-pulse"></div>
                        <span class="text-sm font-medium">Dashboard Ativo - Auto-scroll em execução</span>
                    </div>
                `;
                indicator.style.cssText = `
                    position: fixed;
                    top: 20px;
                    right: 20px;
                    z-index: 9999;
                    display: none;
                    transition: all 0.3s ease;
                `;
                
                document.body.appendChild(indicator);
            }

            function showDashboardIndicator() {
                createDashboardIndicator();
                const indicator = document.getElementById('sbar-dashboard-indicator');
                if (indicator) {
                    indicator.style.display = 'block';
                    setTimeout(() => {
                        indicator.style.opacity = '1';
                        indicator.style.transform = 'translateY(0)';
                    }, 100);
                }
            }

            function hideDashboardIndicator() {
                const indicator = document.getElementById('sbar-dashboard-indicator');
                if (indicator) {
                    indicator.style.opacity = '0';
                    indicator.style.transform = 'translateY(-20px)';
                    setTimeout(() => {
                        indicator.style.display = 'none';
                    }, 300);
                }
            }

            // ===========================================
            // FUNÇÕES DO AUTO-SCROLL COM LIMITES 5%-95%
            // ===========================================
            function forceScrollableHeight() {
                const viewportHeight = window.innerHeight;
                const minHeight = viewportHeight * 1.5;
                
                document.body.style.minHeight = minHeight + 'px';
                
                // Adiciona conteúdo extra invisível se necessário
                let spacer = document.getElementById('sbar-scroll-spacer');
                if (!spacer) {
                    spacer = document.createElement('div');
                    spacer.id = 'sbar-scroll-spacer';
                    spacer.style.cssText = 'height: 50vh; width: 1px; opacity: 0; pointer-events: none; position: relative; z-index: -1;';
                    document.body.appendChild(spacer);
                }
            }

            function updateScrollPosition() {
                const scrollHeight = Math.max(
                    document.body.scrollHeight,
                    document.documentElement.scrollHeight
                );
                const clientHeight = window.innerHeight;
                
                SBAR_SYSTEM.maxScrollPosition = Math.max(0, scrollHeight - clientHeight);
                SBAR_SYSTEM.currentScrollPosition = window.pageYOffset || document.documentElement.scrollTop;
                
                // Calcula limites de 5% e 95%
                SBAR_SYSTEM.minScrollPosition = SBAR_SYSTEM.maxScrollPosition * 0.05;
                SBAR_SYSTEM.maxScrollLimit = SBAR_SYSTEM.maxScrollPosition * 0.95;
                
                // Se a posição atual está fora dos limites, ajusta
                if (SBAR_SYSTEM.currentScrollPosition < SBAR_SYSTEM.minScrollPosition) {
                    SBAR_SYSTEM.currentScrollPosition = SBAR_SYSTEM.minScrollPosition;
                } else if (SBAR_SYSTEM.currentScrollPosition > SBAR_SYSTEM.maxScrollLimit) {
                    SBAR_SYSTEM.currentScrollPosition = SBAR_SYSTEM.maxScrollLimit;
                }
            }

            function performScroll() {
                if (!SBAR_SYSTEM.autoScrollActive || !SBAR_SYSTEM.isDashboardMode) return;
                
                updateScrollPosition();
                
                if (SBAR_SYSTEM.maxScrollPosition <= 0) {
                    forceScrollableHeight();
                    setTimeout(updateScrollPosition, 500);
                    return;
                }
                
                let newPosition = SBAR_SYSTEM.currentScrollPosition;
                
                // Movimento limitado entre 5% e 95%
                if (!SBAR_SYSTEM.isScrollingUp) {
                    newPosition += SBAR_SYSTEM.scrollStep;
                    if (newPosition >= SBAR_SYSTEM.maxScrollLimit) {
                        SBAR_SYSTEM.isScrollingUp = true;
                        newPosition = SBAR_SYSTEM.maxScrollLimit;
                    }
                } else {
                    newPosition -= SBAR_SYSTEM.scrollStep;
                    if (newPosition <= SBAR_SYSTEM.minScrollPosition) {
                        SBAR_SYSTEM.isScrollingUp = false;
                        newPosition = SBAR_SYSTEM.minScrollPosition;
                    }
                }
                
                SBAR_SYSTEM.currentScrollPosition = newPosition;
                window.scrollTo({ top: newPosition, behavior: 'auto' });
            }

            function startAutoScrollAutomatically() {
                if (!SBAR_SYSTEM.isDashboardMode || SBAR_SYSTEM.autoScrollActive) return;
                
                // Força scroll disponível primeiro
                forceScrollableHeight();
                
                setTimeout(() => {
                    updateScrollPosition();
                    
                    if (SBAR_SYSTEM.maxScrollPosition <= 0) {
                        document.body.style.minHeight = (window.innerHeight * 2) + 'px';
                        
                        setTimeout(() => {
                            updateScrollPosition();
                            if (SBAR_SYSTEM.maxScrollPosition > 0) {
                                // Inicia na posição 5%
                                SBAR_SYSTEM.currentScrollPosition = SBAR_SYSTEM.minScrollPosition;
                                window.scrollTo({ top: SBAR_SYSTEM.minScrollPosition, behavior: 'auto' });
                                
                                SBAR_SYSTEM.autoScrollActive = true;
                                SBAR_SYSTEM.autoScrollInterval = setInterval(performScroll, SBAR_SYSTEM.scrollDelay);
                            }
                        }, 1000);
                    } else {
                        // Inicia na posição 5%
                        SBAR_SYSTEM.currentScrollPosition = SBAR_SYSTEM.minScrollPosition;
                        window.scrollTo({ top: SBAR_SYSTEM.minScrollPosition, behavior: 'auto' });
                        
                        SBAR_SYSTEM.autoScrollActive = true;
                        SBAR_SYSTEM.autoScrollInterval = setInterval(performScroll, SBAR_SYSTEM.scrollDelay);
                    }
                }, 1000);
            }

            function stopAutoScroll() {
                if (!SBAR_SYSTEM.autoScrollActive) return;
                
                SBAR_SYSTEM.autoScrollActive = false;
                
                if (SBAR_SYSTEM.autoScrollInterval) {
                    clearInterval(SBAR_SYSTEM.autoScrollInterval);
                    SBAR_SYSTEM.autoScrollInterval = null;
                }
                
                // Remove altura forçada
                document.body.style.minHeight = '';
                
                // Remove spacer
                const spacer = document.getElementById('sbar-scroll-spacer');
                if (spacer) {
                    spacer.remove();
                }
                
                // Volta para o topo
                window.scrollTo({ top: 0, behavior: 'smooth' });
            }

            // ===========================================
            // EVENT LISTENERS SIMPLIFICADOS
            // ===========================================
            function setupEventListeners() {
                document.addEventListener('keydown', (e) => {
                    if (e.key === 'Escape' && document.fullscreenElement) {
                        document.exitFullscreen();
                    }
                });

                window.addEventListener('resize', function() {
                    if (SBAR_SYSTEM.isDashboardMode) {
                        forceScrollableHeight();
                        setTimeout(updateScrollPosition, 100);
                    }
                });

                if (window.Livewire) {
                    document.addEventListener('livewire:updated', function() {
                        if (SBAR_SYSTEM.isDashboardMode) {
                            // Para temporariamente
                            if (SBAR_SYSTEM.autoScrollActive) {
                                stopAutoScroll();
                                
                                // Reinicia após delay
                                setTimeout(() => {
                                    startAutoScrollAutomatically();
                                }, 1500);
                            }
                        }
                    });
                }
            }

            // ===========================================
            // ALPINE.JS INTEGRATION
            // ===========================================
            function updateAlpineComponent() {
                window.sbarDashboard = function() {
                    return {
                        isDashboardMode: SBAR_SYSTEM.isDashboardMode,
                        modalOpen: SBAR_SYSTEM.modalOpen,
                        autoRefreshEnabled: SBAR_SYSTEM.autoRefreshEnabled,
                        nextRefreshIn: SBAR_SYSTEM.nextRefreshIn,

                        init() {
                            this.isDashboardMode = SBAR_SYSTEM.isDashboardMode;
                            this.autoRefreshEnabled = SBAR_SYSTEM.autoRefreshEnabled;
                            this.nextRefreshIn = SBAR_SYSTEM.nextRefreshIn;
                            
                            // Auto-refresh se estava habilitado
                            const urlParams = new URLSearchParams(window.location.search);
                            const autoRefreshFromUrl = urlParams.get('autorefresh') === 'true';
                            let autoRefreshFromStorage = false;
                            try {
                                autoRefreshFromStorage = localStorage.getItem('sbar_auto_refresh') === 'true';
                            } catch (e) {}
                            
                            if (autoRefreshFromUrl || autoRefreshFromStorage) {
                                this.toggleAutoRefresh();
                            }
                            
                            // Se dashboard mode está ativo, inicia auto-scroll
                            if (SBAR_SYSTEM.isDashboardMode) {
                                setTimeout(() => {
                                    startAutoScrollAutomatically();
                                }, 3000);
                            }
                        },

                        toggleDashboardMode() {
                            toggleDashboardMode();
                            this.isDashboardMode = SBAR_SYSTEM.isDashboardMode;
                        },

                        toggleAutoRefresh() {
                            toggleAutoRefresh();
                            this.autoRefreshEnabled = SBAR_SYSTEM.autoRefreshEnabled;
                            this.nextRefreshIn = SBAR_SYSTEM.nextRefreshIn;
                        },

                        handleAutoRefresh() {
                            SBAR_SYSTEM.nextRefreshIn = 600;
                            this.nextRefreshIn = 600;
                        }
                    };
                };
            }

            // ===========================================
            // INICIALIZAÇÃO
            // ===========================================
            function initialize() {
                detectDashboardMode();
                setupEventListeners();
                updateAlpineComponent();
                
                // Se já está em dashboard mode, otimiza e inicia auto-scroll
                if (SBAR_SYSTEM.isDashboardMode) {
                    optimizeForDashboard();
                    
                    // Aguarda carregamento completo antes de iniciar auto-scroll
                    setTimeout(() => {
                        startAutoScrollAutomatically();
                    }, 4000);
                }
            }

            // ===========================================
            // API PÚBLICA SIMPLIFICADA
            // ===========================================
            window.SBAR_SIMPLE = {
                isDashboardMode: () => SBAR_SYSTEM.isDashboardMode,
                isScrollActive: () => SBAR_SYSTEM.autoScrollActive,
                toggleDashboard: toggleDashboardMode,
                forceStartScroll: startAutoScrollAutomatically,
                stopScroll: stopAutoScroll,
                getStatus: () => ({
                    dashboard: SBAR_SYSTEM.isDashboardMode,
                    autoScroll: SBAR_SYSTEM.autoScrollActive,
                    autoRefresh: SBAR_SYSTEM.autoRefreshEnabled,
                    maxScroll: SBAR_SYSTEM.maxScrollPosition,
                    scrollLimits: {
                        min: SBAR_SYSTEM.minScrollPosition,
                        max: SBAR_SYSTEM.maxScrollLimit
                    }
                })
            };

            // ===========================================
            // INICIALIZAÇÃO AUTOMÁTICA
            // ===========================================
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', initialize);
            } else {
                initialize();
            }

        })();
    </script>
@endsection