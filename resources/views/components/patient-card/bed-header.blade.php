@props(['patient', 'showHandover' => false])
<div class="relative"
     x-data="{
        show: false,
        tipStyle: '',
        _timer: null,
        calculatePosition(badgeEl) {
            const r = badgeEl.getBoundingClientRect();
            if (r.bottom < 0 || r.top > window.innerHeight) return;

            const tipW = 208;
            let left = r.left + r.width / 2;
            let top  = r.bottom + 8;

            if (left - tipW / 2 < 8)                          left = tipW / 2 + 8;
            if (left + tipW / 2 > window.innerWidth - 8)      left = window.innerWidth - tipW / 2 - 8;

            const tipH = 110;
            if (top + tipH > window.innerHeight - 8)          top = r.top - tipH - 8;

            this.tipStyle = 'left:' + left + 'px;top:' + top + 'px;transform:translateX(-50%)';
        },
        open(el) {
            // Close any other bed-header tooltip that is currently open
            window.dispatchEvent(new CustomEvent('close-bed-tooltip'));

            const badge = el.querySelector('span') || el;
            this.calculatePosition(badge);
            this.show = true;

            clearTimeout(this._timer);
            this._timer = setTimeout(() => { this.show = false; }, 4000);
        },
        close() {
            clearTimeout(this._timer);
            this.show = false;
        },
        toggle(el) {
            if (window.matchMedia('(pointer: fine)').matches) return;
            this.show ? this.close() : this.open(el);
        },
        init() {
            window.addEventListener('close-bed-tooltip', () => this.close());
            window.addEventListener('scroll', () => this.close(), { passive: true, capture: true });
            window.addEventListener('touchmove', () => this.close(), { passive: true, capture: true });
        }
     }"
     @if($showHandover) @click="toggle($el)" @endif
     @if($showHandover) class="cursor-pointer" @endif
>
    <span
        @if($showHandover) @mouseenter="open($el)" @mouseleave="close()" @endif
        class="relative inline-flex items-center bg-white/80 text-gray-800 text-sm sm:text-base md:text-lg lg:text-base font-bold px-3 py-1.5 md:px-4 md:py-2 lg:px-3 lg:py-1.5 rounded-full shadow-sm cursor-default"
    >
        Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
        @if($showHandover)
            @if(!($patient['handover_done'] ?? false))
                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500"></span>
                </span>
            @else
                <span class="absolute -top-0.5 -right-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-santacasa-100"></span>
            @endif
        @endif
    </span>

    @if($showHandover)
    <div
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        :style="tipStyle"
        class="fixed z-[9999] w-52 bg-gray-900 text-white text-xs rounded-lg shadow-xl px-3 py-2.5 pointer-events-none"
        style="display:none"
    >
        @if(!($patient['handover_done'] ?? false))
            <p class="font-semibold text-orange-300 mb-1 flex items-center gap-1">
                <i class="fas fa-triangle-exclamation fa-xs"></i>
                Passagem pendente
            </p>
            <p class="text-gray-300 leading-snug">Nenhuma anotação no turno da {{ $patient['handover_shift_name'] ?? 'turno atual' }}.</p>
        @else
            <p class="font-semibold text-santacasa-100 mb-1 flex items-center gap-1">
                <i class="fas fa-check-circle fa-xs"></i>
                Passagem realizada
            </p>
            <p class="text-gray-300 leading-snug">
                {{ $patient['handover_msg_count'] ?? 0 }} {{ ($patient['handover_msg_count'] ?? 0) === 1 ? 'anotação' : 'anotações' }} no turno da {{ $patient['handover_shift_name'] ?? 'turno atual' }}. Última às {{ $patient['handover_last_time'] ?? '--:--' }}.
            </p>
        @endif
    </div>
    @endif
</div>
