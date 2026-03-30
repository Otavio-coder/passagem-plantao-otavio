<div id="modal-edit-profile" class="fixed inset-0 z-[9998] hidden" x-data="{ open: false }" x-init="
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
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="$el._hide()"></div>

        {{-- Container do Modal --}}
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-full
                       sm:w-[95vw] sm:h-auto sm:max-h-[90vh] sm:rounded-2xl
                       lg:w-[500px]
                       shadow-2xl"
                @click.stop
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
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
                        </svg>
                        <h2 class="text-base font-bold text-white leading-tight">Editar Perfil</h2>
                    </div>
                    <button @click="$el._hide()" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                        </svg>
                    </button>
                </div>

                {{-- Content --}}
                <form id="form-modal-edit-profile" action="{{ route('profiles.edit') }}" method="post" class="flex-1 overflow-y-auto min-h-0">
                    @csrf
                    <input type="hidden" name="profile" id="profile_id" />

                    <div class="p-4 space-y-4">
                        {{-- Nome --}}
                        <div>
                            <label for="edit_name" class="block text-sm font-medium text-gray-700 mb-1">Nome <span class="text-red-500">*</span></label>
                            <input
                                type="text"
                                name="name"
                                id="edit_name"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D] bg-white"
                            >
                        </div>

                        {{-- Permissões --}}
                        <div>
                            <label for="edit_permissions" class="block text-sm font-medium text-gray-700 mb-1">Permissões <span class="text-red-500">*</span></label>
                            <select
                                multiple
                                name="permissions[]"
                                id="edit_permissions"
                                class="w-full px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D] bg-white min-h-[200px]"
                            >
                                @foreach($permissions as $permission)
                                    <option id="edit-permission-{{ $permission->id }}">{{ $permission->name }}</option>
                                @endforeach
                            </select>
                            <p class="text-xs text-gray-500 mt-1">Segure Ctrl (ou Cmd) para selecionar múltiplas permissões</p>
                        </div>
                    </div>
                </form>

                {{-- Footer --}}
                <div class="px-4 py-3 border-t border-gray-200 bg-gray-100 flex-shrink-0 flex justify-end gap-3">
                    <button
                        type="button"
                        @click="$el._hide()"
                        class="px-4 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors"
                    >
                        Cancelar
                    </button>
                    <button
                        type="submit"
                        form="form-modal-edit-profile"
                        class="px-5 py-2 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] rounded-lg transition-colors shadow"
                    >
                        Salvar Alterações
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
