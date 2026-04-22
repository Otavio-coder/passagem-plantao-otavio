<div x-data="{
    showModal: @entangle('showModal'),
    init() {
        this.$watch('showModal', (value) => {
            if (value) {
                document.body.style.overflow = 'hidden';
                document.body.classList.add('modal-active');

                if (window.innerWidth < 640) {
                    document.documentElement.style.position = 'fixed';
                    document.documentElement.style.width = '100%';
                    document.documentElement.style.height = '100%';
                    document.documentElement.style.top = '0';
                }
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('modal-active');
                document.documentElement.style.position = '';
                document.documentElement.style.width = '';
                document.documentElement.style.height = '';
                document.documentElement.style.top = '';
            }
        });
    }
}">
    <div
        x-show="showModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9998]"
        style="display: none;"
    >
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showModal = false; $wire.closeModal();"></div>

        {{-- Container --}}
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-full
                       sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                       lg:w-[70vw] lg:h-[80vh] lg:max-w-[900px]
                       shadow-2xl"
                @click.stop
            >
                {{-- Header --}}
                <div class="flex-shrink-0 bg-[#004D9D] px-3 sm:px-6 py-3 sm:py-4 relative">
                    {{-- Close --}}
                    <button
                        @click="showModal = false; $wire.closeModal();"
                        class="absolute top-3 right-3 sm:top-1/2 sm:-translate-y-1/2 sm:right-4 z-10 flex items-center justify-center w-9 h-9 sm:w-8 sm:h-8 text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 rounded-full focus:outline-none focus:ring-2 focus:ring-white/50"
                        aria-label="Fechar modal"
                    >
                        <x-heroicon-o-x-mark class="w-5 h-5" />
                    </button>

                    <div class="pr-14 sm:pr-10">
                        {{-- Title --}}
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2 sm:gap-4 mb-3">
                            <h3 class="text-base sm:text-lg font-bold text-white leading-tight">
                                Huddle de Alta
                            </h3>
                            <div class="sm:border-l sm:border-white/30 sm:pl-4">
                                <p class="text-xs sm:text-sm text-blue-100 font-medium leading-tight truncate">
                                    {{ $currentPatient['nm_pessoa_fisica'] ?? 'Paciente' }}
                                </p>
                            </div>
                        </div>

                        {{-- Info grid --}}
                        @php
                            $sexo = $currentPatient['sexo'] ?? null;
                            $sexoPt = match($sexo) { 'F' => 'Feminino', 'M' => 'Masculino', default => '—' };
                        @endphp

                        {{-- Mobile: 3 cols --}}
                        <div class="sm:hidden px-1">
                            <div class="grid grid-cols-3 gap-2 text-[11px] text-blue-100">
                                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[52px] flex flex-col justify-center">
                                    <span class="opacity-80 leading-tight">Hospital</span>
                                    <span class="text-white font-semibold leading-tight mt-0.5 truncate">{{ $hospitalName ?: '—' }}</span>
                                </div>
                                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[52px] flex flex-col justify-center">
                                    <span class="opacity-80 leading-tight">Leito</span>
                                    <span class="text-white font-semibold font-mono leading-tight mt-0.5">{{ $currentPatient['cd_unidade_basica'] ?? '—' }}</span>
                                </div>
                                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[52px] flex flex-col justify-center">
                                    <span class="opacity-80 leading-tight">Atendimento</span>
                                    <span class="text-white font-semibold font-mono leading-tight mt-0.5 truncate">{{ $currentPatient['nr_atendimento'] ?? '—' }}</span>
                                </div>
                                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[52px] flex flex-col justify-center">
                                    <span class="opacity-80 leading-tight">Idade</span>
                                    <span class="text-white font-semibold leading-tight mt-0.5">{{ isset($currentPatient['age']) ? $currentPatient['age'] . 'a' : '—' }}</span>
                                </div>
                                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[52px] flex flex-col justify-center">
                                    <span class="opacity-80 leading-tight">Sexo</span>
                                    <span class="text-white font-semibold leading-tight mt-0.5">{{ $sexoPt }}</span>
                                </div>
                                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[52px] flex flex-col justify-center">
                                    <span class="opacity-80 leading-tight">Internação</span>
                                    <span class="text-white font-semibold leading-tight mt-0.5">
                                        @if($currentPatient['is_new_patient'] ?? false)
                                            Hoje
                                        @elseif(isset($currentPatient['internment_days']))
                                            {{ $currentPatient['internment_days'] }}d
                                        @else
                                            —
                                        @endif
                                    </span>
                                </div>
                            </div>
                        </div>

                        {{-- Desktop --}}
                        <div class="hidden sm:flex items-center flex-wrap gap-x-6 gap-y-1 px-1 text-xs sm:text-sm text-blue-100">
                            <div class="flex items-center gap-1.5">
                                <span class="opacity-80">Hospital:</span>
                                <span class="text-white font-medium">{{ $hospitalName ?: '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="opacity-80">Leito:</span>
                                <span class="text-white font-medium font-mono">{{ $currentPatient['cd_unidade_basica'] ?? '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="opacity-80">Atendimento:</span>
                                <span class="text-white font-medium font-mono">{{ $currentPatient['nr_atendimento'] ?? '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="opacity-80">Idade:</span>
                                <span class="text-white font-medium">{{ isset($currentPatient['age']) ? $currentPatient['age'] . 'a' : '—' }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="opacity-80">Sexo:</span>
                                <span class="text-white font-medium">{{ $sexoPt }}</span>
                            </div>
                            <div class="flex items-center gap-1.5">
                                <span class="opacity-80">Internação:</span>
                                <span class="text-white font-medium">
                                    @if($currentPatient['is_new_patient'] ?? false)
                                        Hoje
                                    @elseif(isset($currentPatient['internment_days']))
                                        {{ $currentPatient['internment_days'] }}d
                                    @else
                                        —
                                    @endif
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto bg-gray-50 p-4 sm:p-6">
                    <div class="max-w-2xl mx-auto space-y-4">
                        {{-- Patient Card --}}
                        <div class="bg-white rounded-xl shadow-sm border border-gray-100 p-4 sm:p-5">
                            <div class="flex items-start gap-3 mb-4">
                                @if(($currentPatient['sexo'] ?? '') === 'F')
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-pink-100 flex items-center justify-center">
                                        <x-iconoir-female class="text-pink-600 h-5 w-5" />
                                    </div>
                                @elseif(($currentPatient['sexo'] ?? '') === 'M')
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-blue-100 flex items-center justify-center">
                                        <x-iconoir-male class="text-blue-600 h-5 w-5" />
                                    </div>
                                @else
                                    <div class="flex-shrink-0 w-10 h-10 rounded-full bg-gray-100 flex items-center justify-center">
                                        <x-heroicon-o-user class="text-gray-400 h-5 w-5" />
                                    </div>
                                @endif
                                <div class="min-w-0">
                                    <h4 class="text-lg font-bold text-gray-900 leading-tight">
                                        {{ $currentPatient['nm_pessoa_fisica'] ?? 'Paciente' }}
                                    </h4>
                                    <p class="text-sm text-gray-500 mt-0.5">
                                        {{ $sexoPt }} · {{ isset($currentPatient['age']) ? $currentPatient['age'] . ' anos' : '—' }}
                                        @if($currentPatient['birth_date'] ?? null)
                                            · {{ $currentPatient['birth_date'] }}
                                        @endif
                                    </p>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-sm">
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">Leito</p>
                                    <p class="font-semibold text-gray-900 font-mono">{{ $currentPatient['cd_unidade_basica'] ?? '—' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">Atendimento</p>
                                    <p class="font-semibold text-gray-900 font-mono">{{ $currentPatient['nr_atendimento'] ?? '—' }}</p>
                                </div>
                                @if($currentPatient['nr_prontuario'] ?? null)
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">Prontuário</p>
                                    <p class="font-semibold text-gray-900 font-mono">{{ $currentPatient['nr_prontuario'] }}</p>
                                </div>
                                @endif
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">Tempo de internação</p>
                                    <p class="font-semibold text-gray-900">
                                        @if($currentPatient['is_new_patient'] ?? false)
                                            <span class="text-green-700">Hoje</span>
                                        @elseif(isset($currentPatient['internment_days']))
                                            {{ $currentPatient['internment_days'] }} dia{{ $currentPatient['internment_days'] !== 1 ? 's' : '' }}
                                        @else
                                            —
                                        @endif
                                    </p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">Hospital</p>
                                    <p class="font-semibold text-gray-900 truncate">{{ $hospitalName ?: '—' }}</p>
                                </div>
                                <div class="bg-gray-50 rounded-lg p-3">
                                    <p class="text-xs text-gray-500 mb-0.5">Data</p>
                                    <p class="font-semibold text-gray-900 font-mono">{{ date('d/m/Y') }}</p>
                                </div>
                            </div>
                        </div>

                        {{-- Discharge info if available --}}
                        @php
                            $dischargeInfo = $currentPatient['discharge_info'] ?? null;
                            $dischargeType = data_get($dischargeInfo, 'tipo', '');
                        @endphp
                        @if($dischargeInfo && in_array($dischargeType, ['alta', 'alta_medica', 'previsao_alta']))
                            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 flex items-start gap-3">
                                <x-heroicon-o-arrow-right-circle class="w-5 h-5 text-amber-600 flex-shrink-0 mt-0.5" />
                                <div>
                                    <p class="font-semibold text-amber-800 text-sm">
                                        @if($dischargeType === 'alta') Alta
                                        @elseif($dischargeType === 'alta_medica') Alta Médica
                                        @else Previsão de Alta
                                        @endif
                                    </p>
                                    @if($currentPatient['discharge_display']['formatted_date'] ?? null)
                                        <p class="text-xs text-amber-700 mt-0.5">{{ $currentPatient['discharge_display']['formatted_date'] }}</p>
                                    @endif
                                </div>
                            </div>
                        @endif
                    </div>
                </div>

            </div>
        </div>
    </div>

    <style>
        body.modal-active {
            overflow: hidden !important;
            position: fixed;
            width: 100%;
            height: 100%;
        }

        @media screen and (orientation: landscape) and (max-height: 550px) {
            body.modal-active { position: static; }
        }
    </style>
</div>
