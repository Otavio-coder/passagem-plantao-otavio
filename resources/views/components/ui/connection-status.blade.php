{{-- Indicador de conexão — JS em resources/js/connection-status.js --}}
{{--
    Mobile: posicionado no canto inferior direito (evita zona de swipe esquerda).
    Desktop: canto inferior esquerdo.
    Pill auto-compacta (só dot) após 4s em status "good".
--}}
<div
    x-data="connectionStatus"
    data-heartbeat-url="{{ route('session.heartbeat') }}"
    class="fixed bottom-3 right-3 sm:bottom-3 sm:left-3 sm:right-auto z-[99999] flex flex-col items-end sm:items-start gap-1.5"
    role="status"
    aria-live="assertive"
    @click.outside="expanded = false"
>
    {{-- Pill --}}
    <button
        @click="expanded = !expanded; if (expanded) scheduleAutoClose()"
        type="button"
        class="flex items-center gap-1.5 rounded-full border shadow transition-all focus:outline-none focus-visible:ring-1 focus-visible:ring-white/60 touch-manipulation"
        :class="[pillClass, compact && status === 'good' ? 'px-1.5 py-1.5' : 'px-2 py-1 text-[11px] font-medium']"
        :aria-label="label"
    >
        <span class="relative flex h-2 w-2 sm:h-1.5 sm:w-1.5 flex-shrink-0">
            <span x-show="status !== 'good'" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-60" :class="dotPingClass"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 sm:h-1.5 sm:w-1.5" :class="dotPingClass"></span>
        </span>
        <span x-show="!compact || status !== 'good'" x-text="label" class="text-[11px] font-medium"></span>
        <span x-show="(!compact || status !== 'good') && pingMs !== null" class="opacity-60 text-[11px]" x-text="pingMs + 'ms'" x-cloak></span>
    </button>

    {{-- Detalhe expandido --}}
    <div
        x-show="expanded"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 translate-y-1"
        x-transition:enter-end="opacity-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="w-64 rounded-lg border border-gray-200 bg-white shadow-lg px-3 py-2.5 text-xs text-gray-600 leading-relaxed"
    >
        <p class="font-medium text-gray-800 mb-1" x-text="label"></p>
        <p x-text="message"></p>
        <div class="mt-2 space-y-0.5 font-mono text-gray-400">
            <p x-show="pingMs !== null" x-cloak x-text="'Rede: ' + pingMs + ' ms'"></p>
            <p x-show="serverMs !== null" x-cloak x-text="'Servidor: ' + serverMs + ' ms'"></p>
        </div>
    </div>
</div>
