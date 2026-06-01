document.addEventListener('alpine:init', () => {
    Alpine.data('connectionStatus', () => ({
        status: 'good',
        expanded: false,
        pingMs: null,      // latência de rede (heartbeat POST — pouco trabalho no servidor)
        serverMs: null,    // última resposta Livewire completa (inclui Oracle, DOM morphing)

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

        // Ping threshold: heartbeat route faz trabalho mínimo — valor acima
        // indica latência de rede real, não tempo de processamento do servidor.
        classifyPing(ms) {
            if (ms > 800) return 'slow';
            return 'good';
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
                if (next === 'good') this.expanded = false;
            })
            .catch(() => {
                clearTimeout(tid);
                this.status = navigator.onLine ? 'slow' : 'offline';
            });
        },

        hookLivewire() {
            if (typeof Livewire === 'undefined') return;

            // Livewire commit = rede + PHP + Oracle + DOM morphing.
            // Usado apenas para exibição no painel expandido, NÃO para classificação de status.
            // Falha de request indica problema real de conectividade.
            Livewire.hook('commit', ({ succeed, fail }) => {
                const t = performance.now();
                succeed(() => { this.serverMs = Math.round(performance.now() - t); });
                fail(() => { this.status = navigator.onLine ? 'slow' : 'offline'; });
            });
        },

        init() {
            window.addEventListener('offline', () => { this.status = 'offline'; });
            window.addEventListener('online',  () => {
                this.status = 'good';
                setTimeout(() => this.ping(), 500);
            });

            if (!navigator.onLine) { this.status = 'offline'; }

            // Ping inicial após a página carregar, depois a cada 20s
            setTimeout(() => this.ping(), 2000);
            setInterval(() => {
                if (document.visibilityState === 'visible') this.ping();
            }, 20_000);

            this.hookLivewire();
        },
    }));
});
