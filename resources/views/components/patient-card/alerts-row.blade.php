@props(['patient'])
<div class="flex items-center justify-center gap-1.5 flex-1 min-w-0">

    {{-- ALERGIA --}}
    @if($patient['has_allergy'] ?? false)
        <x-patient-card.alert-button
            button-class="w-11 h-11 flex-shrink-0 flex items-center justify-center rounded-full bg-red-500 text-white shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
            aria-label="Ver alergias"
            tooltip-class="w-52 bg-red-500 text-white"
            tooltip-title="Alergias Registradas"
            modal-gradient="bg-gradient-to-r from-red-500 to-red-600"
            modal-title="Alergias Registradas"
        >
            <x-slot:buttonIcon>
                <svg xmlns="http://www.w3.org/2000/svg" class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </x-slot:buttonIcon>
            <x-slot:tooltipContent>
                @if(empty($patient['allergy_items'] ?? []))
                    <div class="text-white/80">Nenhuma alergia registrada</div>
                @else
                    @foreach(array_slice($patient['allergy_items'] ?? [], 0, 3) as $a)
                        <div class="py-0.5">
                            @if(isset($a['med']))
                                <span class="font-medium">{{ $a['med'] }}</span>
                                <span class="text-white/70 ml-1">{{ $a['grav'] }}</span>
                            @else
                                <span>{{ $a['text'] }}</span>
                            @endif
                        </div>
                    @endforeach
                    @if(count($patient['allergy_items'] ?? []) > 3)
                        <div class="text-white/70 text-[10px] mt-1">+{{ count($patient['allergy_items'] ?? []) - 3 }} mais · clique para ver</div>
                    @endif
                @endif
            </x-slot:tooltipContent>
            <x-slot:modalHeaderIcon>
                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
            </x-slot:modalHeaderIcon>
            @if(empty($patient['allergy_items'] ?? []))
                <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                    <x-heroicon-o-check-circle class="w-12 h-12 text-gray-300 mb-2" />
                    <p class="text-sm">Nenhuma alergia registrada</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($patient['allergy_items'] ?? [] as $a)
                        <div class="p-3 rounded-lg bg-white border border-gray-200 shadow-sm">
                            @if(isset($a['med']))
                                <div class="flex justify-between gap-2 items-start">
                                    <div class="font-medium text-gray-900 text-sm">{{ $a['med'] }}</div>
                                    <div class="{{ $a['severity_text_class'] ?? 'text-gray-500' }} text-xs px-2 py-0.5 rounded-full {{ $a['severity_bg_class'] ?? 'bg-gray-100' }} flex-shrink-0">{{ $a['grav'] }}</div>
                                </div>
                            @else
                                <div class="text-gray-800 text-sm">{{ $a['text'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-patient-card.alert-button>
    @endif

    {{-- ISOLAMENTO --}}
    @if($patient['has_isolation'] ?? false)
        <x-patient-card.alert-button
            button-class="w-11 h-11 flex-shrink-0 flex items-center justify-center rounded-full bg-yellow-400 text-black shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
            aria-label="Ver isolamento"
            tooltip-class="w-52 bg-yellow-400 text-black"
            tooltip-title="Precauções de Isolamento"
            tooltip-title-border="border-black/10"
            modal-gradient="bg-gradient-to-r from-yellow-500 to-amber-500"
            modal-title="Precauções de Isolamento"
        >
            <x-slot:buttonIcon>
                <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="w-6 h-6" alt="Isolamento" />
            </x-slot:buttonIcon>
            <x-slot:tooltipContent>
                @if(empty($patient['isolation_items'] ?? []))
                    <div class="text-black/70">Motivo não especificado</div>
                @else
                    @foreach(array_slice($patient['isolation_items'] ?? [], 0, 3) as $iso)
                        <div class="py-0.5">
                            @if(isset($iso['label']))
                                <span class="font-medium">{{ $iso['label'] }}:</span>
                                <span class="text-black/80 ml-1">{{ $iso['value'] }}</span>
                            @else
                                <span>{{ $iso['text'] }}</span>
                            @endif
                        </div>
                    @endforeach
                    @if(count($patient['isolation_items'] ?? []) > 3)
                        <div class="text-black/60 text-[10px] mt-1">+{{ count($patient['isolation_items'] ?? []) - 3 }} mais · clique para ver</div>
                    @endif
                @endif
            </x-slot:tooltipContent>
            <x-slot:modalHeaderIcon>
                <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="h-5 w-5 flex-shrink-0" alt="Isolamento" />
            </x-slot:modalHeaderIcon>
            @if(empty($patient['isolation_items'] ?? []))
                <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                    <x-heroicon-o-check-circle class="w-12 h-12 text-gray-300 mb-2" />
                    <p class="text-sm">Motivo não especificado</p>
                </div>
            @else
                <div class="space-y-2">
                    @foreach($patient['isolation_items'] ?? [] as $iso)
                        <div class="p-3 rounded-lg bg-white border border-gray-200 shadow-sm">
                            @if(isset($iso['label']))
                                <div class="text-sm">
                                    <span class="font-semibold text-gray-800">{{ $iso['label'] }}:</span>
                                    <span class="text-gray-600">{{ $iso['value'] }}</span>
                                </div>
                            @else
                                <div class="text-sm text-gray-800">{{ $iso['text'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </x-patient-card.alert-button>
    @endif

    {{-- CIRURGIA --}}
    @if($patient['has_surgery'] ?? false)
        <x-patient-card.alert-button
            button-class="w-11 h-11 flex-shrink-0 flex items-center justify-center rounded-full bg-[#7712C7] text-white shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 hover:bg-[#7712C7]/70 focus:outline-none focus:ring-2 focus:ring-[#7712C7]/50 focus:ring-offset-2"
            aria-label="Ver cirurgia"
            tooltip-class="w-52 bg-[#7712C7] text-white"
            tooltip-title="Agenda de Cirurgia"
            modal-gradient="bg-gradient-to-r from-[#7712C7] to-[#7712C7]/80"
            modal-title="Agendas de Cirurgia Recente"
        >
            <x-slot:buttonIcon>
                <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="w-7 h-7 filter brightness-0 invert" />
            </x-slot:buttonIcon>
            <x-slot:tooltipContent>
                @if(!empty($patient['procedimentos_cirurgicos'][0] ?? null))
                    <div class="py-0.5">
                        <span class="font-medium">{{ $patient['procedimentos_cirurgicos'][0]['data_agenda'] ?? 'N/A' }}</span>
                        @if(!empty($patient['procedimentos_cirurgicos'][0]['hora_agenda']))
                            <span class="text-white/70 ml-1">às {{ $patient['procedimentos_cirurgicos'][0]['hora_agenda'] }}</span>
                        @endif
                    </div>
                    <div class="py-0.5 text-white/90">{{ $patient['procedimentos_cirurgicos'][0]['display_description'] ?? 'Procedimento' }}</div>
                    @if(!empty($patient['procedimentos_cirurgicos'][0]['carater_cirurgia']))
                        <div class="text-white/80 text-[10px] mt-0.5">{{ $patient['procedimentos_cirurgicos'][0]['carater_cirurgia'] }}</div>
                    @endif
                    @if(!empty($patient['procedimentos_cirurgicos'][0]['status']))
                        <div class="text-white/80 text-[10px] mt-0.5">{{ $patient['procedimentos_cirurgicos'][0]['status'] }}</div>
                    @endif
                    @if(!empty($patient['procedimentos_cirurgicos'][0]['setor_execucao']))
                        <div class="text-white/80 text-[10px] mt-0.5">{{ $patient['procedimentos_cirurgicos'][0]['setor_execucao'] }}</div>
                    @endif
                    @if(!empty($patient['procedimentos_cirurgicos'][0]['observacoes']))
                        <div class="text-white/80 text-[10px] mt-0.5">Obs: {{ $patient['procedimentos_cirurgicos'][0]['observacoes'] }}</div>
                    @endif
                    @if(count($patient['procedimentos_cirurgicos']) > 1)
                        <div class="text-white/70 text-[10px] mt-1">+{{ count($patient['procedimentos_cirurgicos']) - 1 }} mais · clique para ver</div>
                    @endif
                @else
                    <div class="text-white/80">Ver detalhes</div>
                @endif
            </x-slot:tooltipContent>
            <x-slot:modalHeaderIcon>
                <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="h-5 w-5 flex-shrink-0" />
            </x-slot:modalHeaderIcon>
            @if(!empty($patient['procedimentos_cirurgicos']) && is_array($patient['procedimentos_cirurgicos']))
                <div class="space-y-2">
                    @foreach($patient['procedimentos_cirurgicos'] as $c)
                        <div class="p-3 rounded-lg bg-white border border-gray-200 shadow-sm">
                            <div class="text-sm font-semibold text-gray-900">
                                {{ $c['data_agenda'] ?? 'N/A' }} @if(!empty($c['hora_agenda'])) às {{ $c['hora_agenda'] }}@endif
                            </div>
                            <div class="text-sm text-gray-700 mt-1">{{ $c['display_description'] ?? ($c['descricao_padronizada'] ?? $c['procedimento'] ?? $c['carater_cirurgia'] ?? 'Procedimento') }}</div>
                            @if(!empty($c['carater_cirurgia']) || !empty($c['status']) || !empty($c['setor_execucao']))
                                <div class="mt-1 flex flex-wrap items-center gap-1.5">
                                    @if(!empty($c['carater_cirurgia']))
                                        <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#7712C7] bg-[#7712C7]/10 border border-[#7712C7]/20 px-1.5 py-0.5 rounded">
                                            <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="h-3 w-3 opacity-80" alt="" />
                                            <span>{{ $c['carater_cirurgia'] }}</span>
                                        </div>
                                    @endif
                                    @if(!empty($c['status']))
                                        <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded">
                                            <span>{{ $c['status'] }}</span>
                                        </div>
                                    @endif
                                    @if(!empty($c['setor_execucao']))
                                        <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                            <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                            <span>{{ $c['setor_execucao'] }}</span>
                                        </div>
                                    @endif
                                </div>
                            @endif
                            @if(!empty($c['observacoes']))
                                <div class="text-xs text-gray-500 italic mt-1">Obs: {{ $c['observacoes'] }}</div>
                            @endif
                        </div>
                    @endforeach
                </div>
            @else
                <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                    <x-heroicon-o-check-circle class="w-12 h-12 text-gray-300 mb-2" />
                    <p class="text-sm">Verificar detalhes</p>
                </div>
            @endif
        </x-patient-card.alert-button>
    @endif

    {{-- ALTA --}}
    @if($patient['discharge_display']['show'] ?? false)
        <x-patient-card.alert-button
            :button-class="'w-11 h-11 flex-shrink-0 flex items-center justify-center rounded-full ' . ($patient['discharge_display']['bg'] ?? 'bg-gray-100') . ' shadow-md transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2'"
            :aria-label="$patient['discharge_display']['label'] ?? 'Alta'"
            tooltip-class="w-48 bg-white border border-gray-200 text-gray-800"
            :tooltip-title="$patient['discharge_display']['label'] ?? 'Alta'"
            tooltip-title-border="border-gray-200"
            modal-gradient="bg-gradient-to-r from-gray-600 to-gray-700"
            :modal-title="$patient['discharge_display']['label'] ?? 'Alta'"
            modal-width="sm:w-[400px]"
            outer-class="relative"
        >
            <x-slot:buttonIcon>
                <img src="{{ asset('images/icons/patient-card/' . ($patient['discharge_display']['icon'] ?? 'alta.svg')) }}" class="w-6 h-6" alt="{{ $patient['discharge_display']['label'] ?? 'Alta' }}" />
            </x-slot:buttonIcon>
            <x-slot:tooltipContent>
                @if(($patient['discharge_display']['type'] ?? '') === 'alta')
                    <div><span class="text-gray-500">Data:</span> {{ $patient['discharge_info']['dt_alta_formatted'] ?? '-' }}</div>
                    @if(!empty($patient['discharge_info']['ds_motivo_alta']))
                        <div class="mt-1"><span class="text-gray-500">Motivo:</span> {{ $patient['discharge_info']['ds_motivo_alta'] }}</div>
                    @endif
                @elseif(($patient['discharge_display']['type'] ?? '') === 'alta_medica')
                    <div><span class="text-gray-500">Alta Médica:</span> {{ $patient['discharge_info']['dt_alta_medico_formatted'] ?? '-' }}</div>
                    @if(!empty($patient['discharge_info']['dt_previsto_alta_formatted']))
                        <div class="mt-1"><span class="text-gray-500">Prev. Alta:</span> {{ $patient['discharge_info']['dt_previsto_alta_formatted'] }}</div>
                    @endif
                @endif
            </x-slot:tooltipContent>
            <x-slot:modalHeaderIcon>
                <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                    <img src="{{ asset('images/icons/patient-card/' . ($patient['discharge_display']['icon'] ?? 'alta.svg')) }}" class="w-4 h-4" alt="{{ $patient['discharge_display']['label'] ?? 'Alta' }}" />
                </span>
            </x-slot:modalHeaderIcon>
            <div class="space-y-2">
                @if(($patient['discharge_display']['type'] ?? '') === 'alta')
                    <div class="flex justify-between items-center bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                        <span class="text-gray-500 text-sm">Data da Alta</span>
                        <span class="font-semibold text-gray-900">{{ $patient['discharge_info']['dt_alta_formatted'] ?? '-' }}</span>
                    </div>
                    @if(!empty($patient['discharge_info']['ds_motivo_alta']))
                        <div class="bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                            <span class="text-gray-500 text-sm block mb-1">Motivo</span>
                            <span class="text-gray-800">{{ $patient['discharge_info']['ds_motivo_alta'] }}</span>
                        </div>
                    @endif
                @elseif(($patient['discharge_display']['type'] ?? '') === 'alta_medica')
                    <div class="flex justify-between items-center bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                        <span class="text-gray-500 text-sm">Alta Médica</span>
                        <span class="font-semibold text-gray-900">{{ $patient['discharge_info']['dt_alta_medico_formatted'] ?? '-' }}</span>
                    </div>
                    @if(!empty($patient['discharge_info']['dt_previsto_alta_formatted']))
                        <div class="flex justify-between items-center bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                            <span class="text-gray-500 text-sm">Previsão de Alta</span>
                            <span class="font-semibold text-gray-900">{{ $patient['discharge_info']['dt_previsto_alta_formatted'] }}</span>
                        </div>
                    @endif
                @endif
            </div>
        </x-patient-card.alert-button>
    @endif

</div>
