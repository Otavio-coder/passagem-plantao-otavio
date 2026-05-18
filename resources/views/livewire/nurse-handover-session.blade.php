<div>
    {{-- Modal de Bloqueio de Passagem --}}
    @if($showBlockedModal)
    <div
        class="fixed inset-0 z-[99999] flex items-center justify-center p-4"
        x-data
        @keydown.escape.window="$wire.closeBlockedModal()"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="$wire.closeBlockedModal()"></div>

        {{-- Modal --}}
        <div class="relative bg-white rounded-2xl shadow-2xl max-w-md w-full p-6 z-10">
            <div class="flex flex-col items-center text-center">

                @if($blockedType === 'shift_done')
                    <div class="w-16 h-16 rounded-full bg-emerald-100 flex items-center justify-center mb-4">
                        <i class="fas fa-circle-check text-emerald-600 text-3xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Passagem já realizada</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $blockedReason }}</p>
                    <div class="mt-4 px-4 py-2.5 bg-emerald-50 border border-emerald-200 rounded-lg text-xs text-emerald-700 font-medium">
                        <i class="fas fa-clock mr-1"></i>
                        Próximo turno: Manhã 07h · Tarde 13h · Noite 19h
                    </div>

                @elseif($blockedType === 'no_beds')
                    <div class="w-16 h-16 rounded-full bg-amber-100 flex items-center justify-center mb-4">
                        <i class="fas fa-bed text-amber-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Leitos não configurados</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $blockedReason }}</p>

                @elseif($blockedType === 'active_session')
                    <div class="w-16 h-16 rounded-full bg-blue-100 flex items-center justify-center mb-4">
                        <i class="fas fa-play-circle text-blue-600 text-2xl"></i>
                    </div>
                    <h3 class="text-lg font-bold text-gray-900 mb-2">Sessão em andamento</h3>
                    <p class="text-sm text-gray-600 leading-relaxed">{{ $blockedReason }}</p>
                @endif

                @if($blockedType === 'shift_done')
                    <button
                        wire:click="closeBlockedModal"
                        class="mt-6 w-full px-4 py-2.5 bg-emerald-600 text-white text-sm font-semibold rounded-xl hover:bg-emerald-700 transition-colors flex items-center justify-center gap-2"
                    >
                        <i class="fas fa-check text-xs"></i>
                        Certo, já fiz a passagem
                    </button>
                @else
                    <button
                        wire:click="closeBlockedModal"
                        class="mt-6 w-full px-4 py-2.5 bg-[#004D9D] text-white text-sm font-semibold rounded-xl hover:bg-[#003d7a] transition-colors"
                    >
                        Entendido
                    </button>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
