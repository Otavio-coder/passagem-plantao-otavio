/**
 * Sistema de Proteção de Conteúdo - SBAR v2.1
 * Proteção de Dados Sensíveis de Pacientes (LGPD)
 * Versão equilibrada - menos sensível
 */
(function() {
    'use strict';

    const config = {
        blurStrength: 25,
        logoPath: '/images/logo-santacasa-app.png',

        // ⚙️ CONFIGURAÇÕES DE DETECÇÃO - Ative/desative o que precisar
        detection: {
            // 🖥Desktop
            printScreen: true,          // Print Screen, Insert
            macScreenshot: true,        // Cmd+Shift+3/4/5
            snippingTool: true,         // Win+Shift+S
            devTools: false,            // F12, Ctrl+Shift+I (pode incomodar devs)

            //  Mobile/Tablet
            mobileGesture: true,        // Gesto de 3 dedos
            iosScreenshot: true,        // Power + Volume (iOS)
            androidScreenshot: true,    // Power + Volume Down

            tabChange: false,           // Trocar de aba (visibilitychange)
            windowBlur: false,          // Perder foco da janela
            orientationChange: false,   // Rotação de tela

            //  Gravação e impressão
            screenRecording: true,      // Gravação de tela
            printing: true,             // Ctrl+P, beforeprint
        },

        // Delays e thresholds
        visibilityDelay: 500,          // ms entre detecções (aumentado)
        devToolsCheckInterval: 2000,   // ms (verificar devtools a cada 2s, não 500ms)
    };

    let isProtectionActive = false;
    let lastVisibilityChange = 0;

    function getUserName() {
        const userName = document.querySelector('meta[name="sbar-user-name"]');
        return userName ? userName.getAttribute('content') : 'Usuário';
    }

    function createProtectionOverlay() {
        if (document.getElementById('sbar-protection-overlay')) return;

        const userName = getUserName();
        const overlay = document.createElement('div');
        overlay.id = 'sbar-protection-overlay';
        overlay.innerHTML = `
            <div class="sbar-protection-wrapper">
                <img src="${config.logoPath}" alt="Logo Santa Casa" class="sbar-protection-logo">
                <div class="sbar-protection-text">
                    <h6 class="sbar-protection-title">
                        <span class="sbar-highlight">Atenção ${userName},</span> conteúdo protegido
                    </h6>
                    <p class="sbar-protection-subtitle">
                        Dados sensíveis de pacientes protegidos pela LGPD.
                    </p>
                    <p class="sbar-protection-legal">
                        Art. 11 da Lei nº 13.709/2018 - Lei Geral de Proteção de Dados
                    </p>
                    <p class="sbar-protection-info"></p>
                    <button id="sbar-close-btn" class="sbar-close-button">Entendi</button>
                </div>
            </div>
        `;

        const style = document.createElement('style');
        style.textContent = `
            @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700&display=swap');

            * {
                -webkit-user-select: none !important;
                -moz-user-select: none !important;
                user-select: none !important;
            }

            input, textarea, [contenteditable="true"] {
                -webkit-user-select: text !important;
                -moz-user-select: text !important;
                user-select: text !important;
            }

            #sbar-protection-overlay {
                position: fixed;
                inset: 0;
                background: linear-gradient(135deg, #0c4a6e 0%, #075985 100%);
                z-index: 999999;
                display: flex;
                align-items: center;
                justify-content: center;
                font-family: 'Montserrat', sans-serif;
                opacity: 0;
                pointer-events: none;
                transition: opacity 0.3s ease;
            }

            #sbar-protection-overlay.show {
                opacity: 1;
                pointer-events: all;
            }

            .sbar-protection-wrapper {
                text-align: center;
                padding: 2rem;
                max-width: 600px;
            }

            .sbar-protection-logo {
                max-width: 200px;
                max-height: 100px;
                height: auto;
                margin: 0 auto 1.5rem;
                border-radius: 0.5rem;
                display: block;
                object-fit: contain;
            }

            .sbar-protection-text {
                margin-top: 1rem;
            }

            .sbar-protection-title {
                font-size: 1.5rem;
                font-weight: 700;
                text-align: center;
                color: #f3f4f6;
                margin: 0 0 1rem 0;
            }

            .sbar-highlight {
                color: #0c4a6e;
                background: white;
                padding: 0 0.5rem;
                border-radius: 0.25rem;
            }

            .sbar-protection-subtitle {
                text-align: center;
                color: #e5e7eb;
                font-size: 1rem;
                margin: 0 0 1rem 0;
                font-weight: 500;
            }

            .sbar-protection-legal {
                text-align: center;
                color: #cbd5e1;
                font-size: 0.85rem;
                margin: 0 0 1.5rem 0;
                padding: 0.75rem;
                background: rgba(255, 255, 255, 0.1);
                border-radius: 0.5rem;
                border: 1px solid rgba(255, 255, 255, 0.2);
                font-weight: 500;
            }

            .sbar-protection-info {
                text-align: center;
                color: #d1d5db;
                font-size: 0.875rem;
                margin: 0 0 1.5rem 0;
                opacity: 0.8;
            }

            .sbar-close-button {
                background: white;
                color: #0c4a6e;
                border: none;
                padding: 0.75rem 2rem;
                font-size: 1rem;
                font-weight: 600;
                border-radius: 0.5rem;
                cursor: pointer;
                transition: all 0.2s ease;
                font-family: 'Montserrat', sans-serif;
                box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
            }

            .sbar-close-button:hover {
                background: #f1f5f9;
                transform: translateY(-2px);
                box-shadow: 0 6px 8px rgba(0, 0, 0, 0.3);
            }

            body.blur-content main,
            body.blur-content header,
            body.blur-content footer {
                filter: blur(${config.blurStrength}px) !important;
                transition: filter 0.3s ease;
            }

            @media (min-width: 768px) {
                .sbar-protection-title {
                    font-size: 2.25rem;
                }
                .sbar-protection-subtitle {
                    font-size: 1.25rem;
                }
                .sbar-protection-logo {
                    max-width: 250px;
                    max-height: 120px;
                }
            }
        `;

        document.head.appendChild(style);
        document.body.appendChild(overlay);

        document.getElementById('sbar-close-btn').onclick = function() {
            hide();
        };
    }

    // Mostrar overlay com proteção contra spam
    function show(reason = '') {
        if (isProtectionActive) return;

        const now = Date.now();
        if (now - lastVisibilityChange < config.visibilityDelay) return;

        lastVisibilityChange = now;
        isProtectionActive = true;

        const el = document.getElementById('sbar-protection-overlay');
        if (el) {
            document.body.classList.add('blur-content');
            el.classList.add('show');

            const info = el.querySelector('.sbar-protection-info');
            if (info) {
                info.textContent = new Date().toLocaleString('pt-BR');
            }

            // Log opcional para auditoria
            if (window.console) {
                console.warn('[SBAR] Tentativa detectada:', reason);
            }
        }
    }

    function hide() {
        const el = document.getElementById('sbar-protection-overlay');
        if (el) {
            el.classList.remove('show');
            document.body.classList.remove('blur-content');
        }
        isProtectionActive = false;
    }

    // ==================== DETECÇÃO DESKTOP ====================

    document.onkeydown = function(e) {
        e = e || window.event;

        // Print Screen / Insert
        if (config.detection.printScreen) {
            if (e.keyCode === 44 || e.keyCode === 45 ||
                e.key === 'PrintScreen' || e.key === 'Insert') {
                show('Print Screen');
                return false;
            }
        }

        // Mac Screenshots
        if (config.detection.macScreenshot) {
            if (e.metaKey && e.shiftKey && ['3', '4', '5'].includes(e.key)) {
                show('Screenshot Mac');
                return false;
            }
        }

        // Windows Snipping Tool
        if (config.detection.snippingTool) {
            if (e.shiftKey && e.key === 's' && (e.metaKey || e.keyCode === 91)) {
                show('Snipping Tool');
                return false;
            }
        }

        // Bloquear Copiar/Colar/Selecionar
        if ((e.ctrlKey || e.metaKey) && ['c','x','v','a','s','p','u'].indexOf(e.key.toLowerCase()) !== -1) {
            e.preventDefault();
            return false;
        }

        // DevTools (opcional)
        if (config.detection.devTools) {
            if (e.keyCode === 123 ||
                (e.ctrlKey && e.shiftKey && ['i','j','c'].indexOf(e.key.toLowerCase()) !== -1)) {
                e.preventDefault();
                return false;
            }
        }
    };

    document.onkeyup = function(e) {
        e = e || window.event;
        if (config.detection.printScreen) {
            if (e.keyCode === 44 || e.keyCode === 45 ||
                e.key === 'PrintScreen' || e.key === 'Insert') {
                show('Print Screen');
                return false;
            }
        }
    };

    // ==================== DETECÇÃO MOBILE/TABLET ====================

    // Gesto de 3 dedos
    if (config.detection.mobileGesture) {
        document.addEventListener('touchstart', function(e) {
            if (e.touches.length >= 3) {
                show('Gesto de screenshot');
            }
        }, { passive: true });
    }

    // iOS Screenshot detection
    if (config.detection.iosScreenshot && /iPhone|iPad|iPod/.test(navigator.userAgent)) {
        let lastTimestamp = Date.now();
        setInterval(() => {
            const now = Date.now();
            const diff = now - lastTimestamp;

            // Detectar "freeze" típico do iOS ao fazer screenshot
            if (diff > 150 && diff < 500 && !document.hidden) {
                show('Screenshot iOS');
            }

            lastTimestamp = now;
        }, 100);
    }

    // Android Screenshot detection (quando disponível)
    if (config.detection.androidScreenshot) {
        let volumeDownPressed = false;

        window.addEventListener('volumedownbutton', function() {
            volumeDownPressed = true;
            setTimeout(() => { volumeDownPressed = false; }, 1000);
        }, false);
    }

    // ==================== MUDANÇA DE CONTEXTO (OPCIONAL) ====================

    // Page Visibility API - trocar de aba
    if (config.detection.tabChange) {
        document.addEventListener('visibilitychange', function() {
            if (document.hidden) {
                show('Troca de aba');
            }
        });
    }

    // Blur - perder foco
    if (config.detection.windowBlur) {
        window.addEventListener('blur', function() {
            show('Perda de foco');
        });
    }

    // Mudança de orientação
    if (config.detection.orientationChange) {
        window.addEventListener('orientationchange', function() {
            show('Rotação de tela');
        });
    }

    // ==================== GRAVAÇÃO E IMPRESSÃO ====================

    // Detectar gravação de tela
    if (config.detection.screenRecording && navigator.mediaDevices) {
        const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
        if (originalGetDisplayMedia) {
            navigator.mediaDevices.getDisplayMedia = function() {
                show('Gravação de tela');
                return Promise.reject(new Error('Screen recording blocked'));
            };
        }
    }

    // Detectar impressão
    if (config.detection.printing) {
        window.addEventListener('beforeprint', function() {
            show('Impressão');
        });
    }

    // ==================== DEVTOOLS (OPCIONAL) ====================

    if (config.detection.devTools) {
        (function detectDevTools() {
            const threshold = 160;
            const devtools = { isOpen: false };

            const check = function() {
                const widthThreshold = window.outerWidth - window.innerWidth > threshold;
                const heightThreshold = window.outerHeight - window.innerHeight > threshold;

                if (widthThreshold || heightThreshold) {
                    if (!devtools.isOpen) {
                        show('DevTools');
                    }
                    devtools.isOpen = true;
                } else {
                    devtools.isOpen = false;
                }
            };

            setInterval(check, config.devToolsCheckInterval);
        })();
    }

    // ==================== PROTEÇÕES BÁSICAS ====================

    document.onselectstart = function() { return false; };
    document.oncopy = function() { return false; };
    document.oncut = function() { return false; };
    document.onpaste = function() { return false; };
    document.ondragstart = function() { return false; };
    document.oncontextmenu = function() { return false; };

    // ==================== INICIALIZAÇÃO ====================

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', createProtectionOverlay);
    } else {
        createProtectionOverlay();
    }

    // Expor controles
    window.SBARProtection = {
        show: show,
        hide: hide,
        isActive: () => isProtectionActive,
        config: config // Permite ajustar em runtime
    };

})();
