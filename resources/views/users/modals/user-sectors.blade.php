<div id="modal-user-sectors" class="fixed inset-0 z-[9998] hidden" x-data="{ open: false }" x-init="
    $el._show = () => { open = true; document.body.style.overflow = 'hidden'; };
    $el._hide = () => { open = false; document.body.style.overflow = ''; };
">
    <div
        x-show="open"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="absolute inset-0"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal('modal-user-sectors')"></div>

        {{-- Container --}}
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-auto
                       sm:w-[95vw] sm:max-w-[420px] sm:rounded-2xl
                       shadow-2xl"
                onclick="event.stopPropagation()"
                x-show="open"
                x-transition:enter="transition ease-out duration-300"
                x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200"
                x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4"
            >
                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                    <div class="flex items-center gap-3 min-w-0">
                        <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                        </svg>
                        <h2 class="text-base font-bold text-white leading-tight truncate" id="sectors-modal-title">Setores Configurados</h2>
                    </div>
                    <button onclick="closeModal('modal-user-sectors')" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Content --}}
                <div class="overflow-y-auto max-h-[60vh] p-4" id="sectors-modal-content">
                    <p class="text-gray-400 text-sm text-center py-4">Nenhum setor configurado.</p>
                </div>
            </div>
        </div>
    </div>
</div>
