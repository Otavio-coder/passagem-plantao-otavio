{{-- Indicador de conexão — JS em resources/js/connection-status.js --}}
<div
    x-data="connectionStatus"
    data-heartbeat-url="{{ route('session.heartbeat') }}"
    class="fixed bottom-3 left-3 z-[900] flex flex-col items-start gap-1.5"
    role="status"
    aria-live="assertive"
>
    {{-- Pill compacto --}}
    <button
        @click="expanded = !expanded"
        type="button"
        class="flex items-center gap-1.5 px-2 py-1 rounded-full border text-[11px] font-medium shadow transition-all focus:outline-none focus-visible:ring-1 focus-visible:ring-white/60"
        :class="pillClass"
        :aria-label="label"
    >
        <span class="relative flex h-1.5 w-1.5 flex-shrink-0">
            <span x-show="status !== 'good'" class="animate-ping absolute inline-flex h-full w-full rounded-full opacity-60" :class="dotPingClass"></span>
            <span class="relative inline-flex rounded-full h-1.5 w-1.5" :class="dotPingClass"></span>
        </span>
        <span x-text="label"></span>
        <span x-show="pingMs !== null" class="opacity-60" x-text="pingMs + 'ms'" x-cloak></span>
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
