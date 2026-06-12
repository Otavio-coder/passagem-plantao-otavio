document.addEventListener('alpine:init', () => {
    Alpine.data('connectionStatus', () => ({
        status: 'good',
        expanded: false,
        compact: false,       // true = só dot visível (bom status em mobile)
        pingMs: null,
        serverMs: null,
        _compactTimer: null,
        _autoCloseTimer: null,

        get message() {
            if (this.status === 'offline') return 'Sem conexão com a rede. Verifique o Wi-Fi ou solicite suporte de TI.';
            if (this.status === 'slow')    return 'Conexão lenta detectada. Os dados podem demorar a carregar. Se persistir, contate o suporte de TI.';
            return 'Sistema operando normalmente.';
        },

        get pillClass() {
            if (this.status === 'offline') return 'bg-red-600 text-white border-red-700';
            if (this.status === 'slow')    return 'bg-amber-500 text-white border-amber-600';
            return 'bg-white/90 text-gray-600 border-gray-200 backdrop-blur-sm';
        },

        get label() {
            if (this.status === 'offline') return 'Sem conexão';
            if (this.status === 'slow')    return 'Conexão lenta';
            return 'Conexão estável';
        },

        get dotPingClass() {
            if (this.status === 'offline' || this.status === 'slow') return 'bg-white';
            return 'bg-emerald-500';
        },

        isMobile() {
            return window.matchMedia('(max-width: 639px)').matches;
        },

        // Após N segundos em "good", recolhe pill para só o dot (mobile)
        scheduleCompact(ms = 4000) {
            clearTimeout(this._compactTimer);
            if (this.status !== 'good') return;
            this._compactTimer = setTimeout(() => {
                if (this.status === 'good' && !this.expanded) this.compact = true;
            }, ms);
        },

        // Fecha painel expandido automaticamente (mobile)
        scheduleAutoClose(ms = 4000) {
            clearTimeout(this._autoCloseTimer);
            if (!this.isMobile()) return;
            this._autoCloseTimer = setTimeout(() => { this.expanded = false; }, ms);
        },

        // Ping threshold: heartbeat faz trabalho mínimo — latência acima é de rede.
        classifyPing(ms) {
            if (ms > 800) return 'slow';
            return 'good';
        },

        // Intervalo de ping adaptado ao tipo de rede (economia bateria em celular)
        pingInterval() {
            const conn = navigator.connection || navigator.mozConnection || navigator.webkitConnection;
            if (conn && (conn.type === 'cellular' || conn.saveData)) return 45_000;
            return 20_000;
        },

        ping() {
            if (!navigator.onLine) { this.status = 'offline'; return; }

            const url  = this.$el.dataset.heartbeatUrl;
            const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
            const t    = performance.now();
            const ctrl = new AbortController();
            const tid  = setTimeout(() => ctrl.abort(), 6000);

            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
                signal: ctrl.signal,
            })
            .then(r => {
                clearTimeout(tid);
                if (!r.ok) throw new Error();
                const ms = Math.round(performance.now() - t);
                this.pingMs = ms;
                const next = this.classifyPing(ms);
                this.status = next;
                if (next === 'good') {
                    this.expanded = false;
                    this.scheduleCompact();
                } else {
                    this.compact = false;
                }
            })
            .catch(() => {
                clearTimeout(tid);
                this.compact = false;
                this.status = navigator.onLine ? 'slow' : 'offline';
            });
        },

        hookLivewire() {
            if (typeof Livewire === 'undefined') return;
            Livewire.hook('commit', ({ succeed, fail }) => {
                const t = performance.now();
                succeed(() => { this.serverMs = Math.round(performance.now() - t); });
                fail(() => {
                    this.compact = false;
                    this.status = navigator.onLine ? 'slow' : 'offline';
                });
            });
        },

        init() {
            window.addEventListener('offline', () => {
                this.compact = false;
                this.status = 'offline';
            });
            window.addEventListener('online', () => {
                this.status = 'good';
                setTimeout(() => this.ping(), 500);
            });

            if (!navigator.onLine) { this.status = 'offline'; }

            // Ping inicial; intervalo adaptativo depois
            setTimeout(() => this.ping(), 2000);

            const schedulePing = () => {
                setTimeout(() => {
                    if (document.visibilityState === 'visible') this.ping();
                    schedulePing();
                }, this.pingInterval());
            };
            schedulePing();

            // Retoma ping ao voltar à aba/tela
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    setTimeout(() => this.ping(), 500);
                }
            });

            // No mobile, começa compacto após 4s se status for good
            if (this.isMobile()) this.scheduleCompact(4000);

            this.hookLivewire();
        },
    }));
});
