<div id="modal-block-user" class="fixed inset-0 z-[9998] hidden" x-data="{ open: false }" x-init="
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
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal('modal-block-user')"></div>

        {{-- Container do Modal --}}
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        <h2 class="text-base font-bold text-white leading-tight" id="block-modal-title">Bloquear Usuário</h2>
                    </div>
                    <button onclick="closeModal('modal-block-user')" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Content --}}
                <div class="p-5 text-center">
                    <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-4" id="block-icon-wrap">
                        <svg id="block-icon-ban" class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                        </svg>
                        <svg id="block-icon-check" class="w-8 h-8 text-green-500 hidden" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                        </svg>
                    </div>
                    <p class="text-gray-600 text-sm">
                        Você tem certeza que deseja <span id="block-action-verb" class="font-medium">bloquear</span> o acesso de
                        <span class="font-semibold text-[#004D9D] block mt-1 text-base" id="block-user-name"></span>?
                    </p>
                    <p class="text-xs text-gray-400 mt-2" id="block-modal-subtext">O usuário não conseguirá acessar o sistema enquanto estiver bloqueado.</p>
                </div>

                {{-- Form oculto --}}
                <form id="form-modal-block-user" action="{{ route('users.block') }}" method="post" class="hidden">
                    @csrf
                    <input type="hidden" name="user_id" id="block-user-id" />
                </form>

                {{-- Footer --}}
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-50 flex-shrink-0 flex justify-end gap-3">
                    <button
                        type="button"
                        onclick="closeModal('modal-block-user')"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        form="form-modal-block-user"
                        id="block-modal-confirm-btn"
                        class="px-5 py-2 text-sm font-semibold text-white bg-red-600 hover:bg-red-700 rounded-lg transition-colors shadow"
                    >
                        Bloquear
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
