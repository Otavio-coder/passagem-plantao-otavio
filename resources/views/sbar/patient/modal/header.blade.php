@props([
    'currentHospitalName' => null,
    'currentPatient' => null,
    'patientDetails' => null,
    'modalPatients' => [],
    'currentPatientIndex' => null,
    'canGoPrevious' => false,
    'canGoNext' => false,
    'activeAlertsCount' => 0,
])

<div class="bg-[#004D9D] px-3 sm:px-6 py-3 sm:py-4 relative">
    {{-- Close Button --}}
    <button 
        @click="showModal = false; $wire.closeModal();"
        class="absolute top-3 right-3 sm:top-1/2 sm:-translate-y-1/2 sm:right-4 z-10 flex items-center justify-center w-9 h-9 sm:w-8 sm:h-8 text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 rounded-full focus:outline-none focus:ring-2 focus:ring-white/50"
        aria-label="Fechar modal"
    >
        <x-heroicon-o-x-mark class="w-5 h-5" />
    </button>
    
    {{-- Header Content --}}
    <div class="pr-14 sm:pr-10">
        {{-- Logo e Título --}}
        <div class="flex flex-col sm:flex-row sm:items-center gap-3 sm:gap-4 mb-3">
            <img src="{{ asset('images/santacasa-horizontal-branco.svg') }}" 
                 alt="Santa Casa" 
                 class="h-6 sm:h-7 w-auto opacity-90">
            
            <div class="sm:border-l sm:border-white/30 sm:pl-4">
                <h3 class="text-xs sm:text-sm text-blue-100 font-medium leading-tight">
                    Modelo de Comunicação SBAR para Passagem de Plantão
                </h3>
            </div>
        </div>
        
        {{-- Informações em Grid (mobile 3 colunas) --}}
        <div class="sm:hidden px-1">
            <div class="grid grid-cols-3 gap-2 text-[11px] text-blue-100">
                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                    <span class="opacity-80 leading-tight">Hospital</span>
                    <span class="text-white font-semibold leading-tight mt-0.5 truncate">{{ $currentHospitalName ?? 'Carregando...' }}</span>
                </div>

                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                    <span class="opacity-80 leading-tight">Setor</span>
                    <span class="text-white font-semibold leading-tight mt-0.5 truncate">{{ $patientDetails->ds_prescricao ?? $patientDetails->ds_setor_atendimento ?? $patientDetails->cd_unidade_basica ?? 'Não informado' }}</span>
                </div>

                <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                    <span class="opacity-80 leading-tight">Data</span>
                    <span class="text-white font-semibold font-mono leading-tight mt-0.5">{{ date('d/m/Y') }}</span>
                </div>

                @if($currentPatient && $currentPatient['has_patient'] && $patientDetails)
                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                        <span class="opacity-80 leading-tight">Prontuário</span>
                        <span class="text-white font-semibold font-mono leading-tight mt-0.5 truncate">{{ $patientDetails->nr_prontuario ?? 'N/A' }}</span>
                    </div>

                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                        <span class="opacity-80 leading-tight">Atendimento</span>
                        <span class="text-white font-semibold font-mono leading-tight mt-0.5 truncate">{{ $currentPatient['nr_atendimento'] }}</span>
                    </div>

                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col items-center justify-center">
                        @if($activeAlertsCount > 0)
                            <button
                                type="button"
                                wire:click="openAlertsModal"
                                class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[10px] font-semibold bg-white text-[#004D9D] border border-blue-200 hover:bg-blue-50 transition-colors"
                            >
                                <span>Alertas</span>
                                <span class="inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full bg-[#004D9D] text-white text-[10px]">{{ $activeAlertsCount }}</span>
                            </button>
                        @else
                            <span class="opacity-80 leading-tight">Alertas</span>
                            <span class="text-white font-semibold leading-tight mt-0.5">0 ativos</span>
                        @endif
                    </div>
                @else
                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                        <span class="opacity-80 leading-tight">Prontuário</span>
                        <span class="text-white font-semibold font-mono leading-tight mt-0.5">N/A</span>
                    </div>
                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                        <span class="opacity-80 leading-tight">Atendimento</span>
                        <span class="text-white font-semibold font-mono leading-tight mt-0.5">N/A</span>
                    </div>
                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5 text-center min-h-[56px] flex flex-col justify-center">
                        <span class="opacity-80 leading-tight">Alertas</span>
                        <span class="text-white font-semibold leading-tight mt-0.5">0 ativos</span>
                    </div>
                @endif
            </div>
        </div>

        {{-- Informações em Grid (tablet/desktop) --}}
        <div class="hidden sm:grid sm:grid-cols-2 lg:grid-cols-6 gap-x-3 gap-y-1.5 px-1 sm:px-2 text-xs sm:text-sm text-blue-100">
            <div class="flex flex-col sm:flex-row sm:items-center gap-0.5 sm:gap-1.5 min-w-0">
                <span class="opacity-80 whitespace-nowrap">Hospital:</span>
                <span class="text-white font-medium truncate">{{ $currentHospitalName ?? 'Carregando...' }}</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-0.5 sm:gap-1.5 min-w-0">
                <span class="opacity-80 whitespace-nowrap">Setor:</span>
                <span class="text-white font-medium truncate">{{ $patientDetails->ds_prescricao ?? $patientDetails->ds_setor_atendimento ?? $patientDetails->cd_unidade_basica ?? 'Não informado' }}</span>
            </div>

            <div class="flex flex-col sm:flex-row sm:items-center gap-0.5 sm:gap-1.5 min-w-0">
                <span class="opacity-80 whitespace-nowrap">Data:</span>
                <span class="text-white font-medium font-mono">{{ date('d/m/Y') }}</span>
            </div>

            @if($currentPatient && $currentPatient['has_patient'] && $patientDetails)
                <div class="flex flex-col sm:flex-row sm:items-center gap-0.5 sm:gap-1.5 min-w-0">
                    <span class="opacity-80 whitespace-nowrap">Prontuário:</span>
                    <span class="text-white font-medium font-mono">{{ $patientDetails->nr_prontuario ?? 'N/A' }}</span>
                </div>

                <div class="flex flex-col sm:flex-row sm:items-center gap-0.5 sm:gap-1.5 min-w-0">
                    <span class="opacity-80 whitespace-nowrap">Atendimento:</span>
                    <span class="text-white font-medium font-mono">{{ $currentPatient['nr_atendimento'] }}</span>
                </div>

                <div class="flex items-center min-w-0">
                    @if($activeAlertsCount > 0)
                        <button
                            type="button"
                            wire:click="openAlertsModal"
                            class="inline-flex items-center gap-1 px-2 py-1 rounded-full text-[11px] font-semibold bg-white text-[#004D9D] border border-blue-200 hover:bg-blue-50 transition-colors"
                        >
                            <span>Alertas Ativos</span>
                            <span class="inline-flex items-center justify-center min-w-4 h-4 px-1 rounded-full bg-[#004D9D] text-white text-[10px]">{{ $activeAlertsCount }}</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>

        @if(!empty($modalPatients))
        <div class="mt-2 pt-2 border-t border-white/20">
            <div class="flex flex-col items-center sm:items-start gap-2 sm:gap-3">
                <span class="text-[11px] sm:text-xs text-blue-100/90 font-medium text-center sm:text-left whitespace-nowrap">Trocar atendimento:</span>

                <div class="w-full max-w-xs sm:max-w-none flex items-stretch sm:items-center justify-center sm:justify-start gap-2 min-w-0 sm:flex-1">
                    <button
                        type="button"
                        wire:click="goToPreviousPatient"
                        @disabled(! $canGoPrevious)
                        class="inline-flex items-center justify-center w-10 h-10 sm:w-8 sm:h-8 rounded-md bg-white/15 text-white hover:bg-white/25 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50 disabled:opacity-40 disabled:cursor-not-allowed"
                        aria-label="Atendimento anterior"
                    >
                        <x-heroicon-o-chevron-up class="w-4 h-4" />
                    </button>

                    <div class="relative min-w-0 flex-1">
                        <select
                            wire:change="goToPatientByAttendance($event.target.value)"
                            class="w-full bg-white/10 border border-white/25 text-white text-xs sm:text-sm rounded-md px-3 py-2.5 sm:py-2 pr-9 focus:outline-none focus:ring-2 focus:ring-white/40"
                        >
                            @foreach($modalPatients as $modalPatient)
                                <option
                                    value="{{ $modalPatient['nr_atendimento'] }}"
                                    @selected(($currentPatient['nr_atendimento'] ?? null) == $modalPatient['nr_atendimento'])
                                    class="text-gray-900"
                                >
                                    {{ $modalPatient['label'] }}
                                </option>
                            @endforeach
                        </select>
                        <div class="pointer-events-none absolute inset-y-0 right-2 flex items-center text-white/80">
                            <x-heroicon-o-chevron-down class="w-4 h-4" />
                        </div>
                    </div>

                    <button
                        type="button"
                        wire:click="goToNextPatient"
                        @disabled(! $canGoNext)
                        class="inline-flex items-center justify-center w-10 h-10 sm:w-8 sm:h-8 rounded-md bg-white/15 text-white hover:bg-white/25 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50 disabled:opacity-40 disabled:cursor-not-allowed"
                        aria-label="Próximo atendimento"
                    >
                        <x-heroicon-o-chevron-down class="w-4 h-4" />
                    </button>
                </div>

                @if($currentPatientIndex !== null)
                    <span class="text-[10px] sm:text-xs text-blue-100/80 text-center sm:text-left whitespace-nowrap">
                        {{ $currentPatientIndex + 1 }} de {{ count($modalPatients) }}
                    </span>
                @endif
            </div>
        </div>
        @endif

        @php
            $dischargeInfo = $currentPatient['discharge_info'] ?? null;
            $dischargeType = (string) data_get($dischargeInfo, 'tipo', '');
            $dischargeBadges = [];

            if ($dischargeType === 'alta') {
                $dischargeBadges[] = ['label' => 'Alta', 'class' => 'bg-emerald-500/20 text-emerald-100 border border-emerald-200/40'];
            }

            if ($dischargeType === 'alta_medica') {
                $dischargeBadges[] = ['label' => 'Alta Médica', 'class' => 'bg-sky-500/20 text-sky-100 border border-sky-200/40'];
            }

            if (in_array($dischargeType, ['previsao_alta', 'alta_medica'], true) && !empty(data_get($dischargeInfo, 'dt_previsto_alta_formatted'))) {
                $dischargeBadges[] = ['label' => 'Previsão de Alta', 'class' => 'bg-amber-500/20 text-amber-100 border border-amber-200/40'];
            }

            $motivoAlta = mb_strtolower((string) data_get($dischargeInfo, 'ds_motivo_alta', ''));
            if ($motivoAlta !== '' && str_contains($motivoAlta, 'óbito')) {
                $dischargeBadges[] = ['label' => 'Óbito', 'class' => 'bg-rose-700/30 text-rose-100 border border-rose-200/40'];
            }
        @endphp

        @if(!empty($dischargeBadges))
            <div class="mt-2 pt-2 border-t border-white/20 flex flex-wrap items-center gap-2">
                @foreach($dischargeBadges as $badge)
                    <span class="inline-flex items-center px-2 py-1 rounded-full text-[11px] font-semibold {{ $badge['class'] }}">
                        {{ $badge['label'] }}
                    </span>
                @endforeach
            </div>
        @endif

    </div>
</div>