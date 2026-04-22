@props(['patient', 'showHandover' => false])
<div class="flex-shrink-0"
     x-data="{
        show: false,
        tipStyle: '',
        open(el) {
            if (!window.matchMedia('(pointer: fine)').matches) return;
            const r = el.getBoundingClientRect();
            this.tipStyle = 'left:' + (r.left + r.width/2) + 'px;top:' + (r.bottom + 4) + 'px;transform:translateX(-50%)';
            this.show = true;
        }
     }"
     @mouseleave="show = false"
>
    <span
        @if($showHandover) @mouseenter="open($el)" @endif
        class="relative inline-flex items-center bg-white/80 text-gray-800 text-xs sm:text-sm font-bold px-2 py-1 rounded-full shadow-sm cursor-default"
    >
        Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
        @if($showHandover)
            @if(!($patient['handover_done'] ?? false))
                <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500"></span>
                </span>
            @else
                <span class="absolute -top-0.5 -right-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
            @endif
        @endif
    </span>

    @if($showHandover)
    <div
        x-show="show"
        x-cloak
        x-transition:enter="transition ease-out duration-100"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        :style="tipStyle"
        class="fixed z-[9999] w-52 bg-gray-900 text-white text-xs rounded-lg shadow-xl px-3 py-2 pointer-events-none"
        style="display:none"
    >
        @if(!($patient['handover_done'] ?? false))
            <p class="font-semibold text-orange-300 mb-1 flex items-center gap-1">
                <i class="fas fa-triangle-exclamation fa-xs"></i>
                Passagem pendente
            </p>
            <p class="text-gray-300 leading-snug">Nenhuma anotação no turno da {{ $patient['handover_shift_name'] ?? 'turno atual' }}.</p>
        @else
            <p class="font-semibold text-green-300 mb-1 flex items-center gap-1">
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
