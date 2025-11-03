{{-- resources/views/livewire/partials/patient-card.blade.php --}}

<div class="relative patient-card w-full">
    <div
        class="card-inner patient-card-fixed flex flex-col rounded-xl shadow-lg overflow-hidden h-[400px] max-h-[400px]
        {{ ($patient['has_patient'] ?? false) ? ($patient['border_class'] ?? '') . ' ' . ($patient['text_color_class'] ?? '') : '' }}"
        style="{{ ($patient['gradient_style'] ?? '') }}"
    >
        @if(!($patient['has_patient'] ?? false))

            {{-- Empty Bed Card --}}
            <div class="h-full w-full flex flex-col min-h-0">
                <div class="flex-1 flex items-center justify-center min-w-0">
                    <div class="w-full h-full flex flex-col bg-gradient-to-br from-gray-200 to-gray-300 p-3 sm:p-4 rounded-xl overflow-hidden min-h-0">
                        <div class="flex justify-between items-center mb-3 flex-shrink-0 min-w-0">
                            <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                                Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="flex-grow flex items-center justify-center">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p class="text-gray-500 text-sm font-medium">Leito Vago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Occupied Bed Card --}}
            <div class="flex flex-col h-full overflow-hidden">
                {{-- Header Section --}}
                <div class="flex-shrink-0 p-2 sm:p-2.5 lg:p-3 flex flex-col gap-1.5">
                    {{-- Row 1: Bed + Alerts + MEWS --}}
                    <div class="flex justify-between items-center gap-2">
                        <span class="bg-white/80 text-gray-800 text-xs font-bold px-2 py-0.5 rounded-full shadow-sm flex-shrink-0">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>
                        {{-- Clinical Alerts --}}
                        <div class="flex items-center justify-center gap-1.5 flex-1 min-w-0">
                            @if($patient['has_allergy'] ?? false)
                                @php
                                    $alergias_raw = $patient['alergias_detalhadas'] ?? '';
                                    $alergias_raw = trim(strip_tags((string)$alergias_raw));
                                    if (empty($alergias_raw) || $alergias_raw === 'Sem alergias registradas') {
                                        $alergias = [];
                                    } else {
                                        $items = preg_split('/[;\r\n]+/', $alergias_raw);
                                        $alergias = [];
                                        foreach ($items as $it) {
                                            $it = trim($it);
                                            if ($it === '') continue;
                                            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $it, $m)) {
                                                $alergias[] = ['med' => trim($m[1]), 'grav' => trim($m[2])];
                                            } else {
                                                $alergias[] = ['text' => $it];
                                            }
                                        }
                                    }
                                @endphp
                                <div x-data="{ showAllergyModal: false }">
                                    <button
                                        type="button"
                                        @click="showAllergyModal = true; document.body.style.overflow = 'hidden'"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-red-500 text-white shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                                        aria-label="Ver alergias"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </button>

                                    {{-- Allergy Modal --}}
                                    <div x-show="showAllergyModal"
                                         x-cloak
                                         @click.self="showAllergyModal = false; document.body.style.overflow = ''"
                                         @keydown.escape.window="showAllergyModal = false; document.body.style.overflow = ''"
                                         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto modal-overlay"
                                         style="margin: 0 !important;"
                                    >
                                        <div class="bg-red-500 rounded-xl p-4 max-w-[90vw] w-[400px] shadow-2xl animate-[modal-slide-in_0.2s_ease-out]" @click.stop>
                                            <div class="flex items-start gap-2 mb-3">
                                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                </svg>
                                                <h3 class="text-sm font-bold text-white flex-1">Alergias Registradas</h3>
                                                <button @click="showAllergyModal = false; document.body.style.overflow = ''" class="text-white/80 hover:text-white transition-colors flex-shrink-0">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar">
                                                @if(empty($alergias))
                                                    <div class="text-white/80 text-xs p-2 bg-white/10 rounded-md">Nenhuma alergia registrada</div>
                                                @else
                                                    @foreach($alergias as $a)
                                                        <div class="p-2 rounded-md bg-white/10 transition-colors duration-150 hover:bg-white/20">
                                                            @if(isset($a['med']))
                                                                @php
                                                                    $grav = $a['grav'] ?? '';
                                                                    $gravidadeClass = 'text-white/70';
                                                                    if (stripos($grav, 'grave') !== false || stripos($grav, 'severa') !== false) {
                                                                        $gravidadeClass = 'font-bold text-yellow-200';
                                                                    } elseif (stripos($grav, 'moderada') !== false) {
                                                                        $gravidadeClass = 'text-yellow-100';
                                                                    }
                                                                @endphp
                                                                <div class="flex justify-between gap-2 items-start">
                                                                    <div class="font-medium text-white text-xs">{{ $a['med'] }}</div>
                                                                    <div class="{{ $gravidadeClass }} text-[10px] flex-shrink-0">{{ $a['grav'] }}</div>
                                                                </div>
                                                            @else
                                                                <div class="text-white text-xs">{{ $a['text'] }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if($patient['has_isolation'] ?? false)
                                @php
                                    $iso_raw = $patient['motivos_isolamento'] ?? '';
                                    $iso_raw = trim(strip_tags((string)$iso_raw));
                                    if (empty($iso_raw) || mb_strtolower($iso_raw) === 'não') {
                                        $isolamentos = [];
                                    } else {
                                        $items = preg_split('/[;\|\r\n]+/', $iso_raw);
                                        $isolamentos = [];
                                        foreach ($items as $it) {
                                            $it = trim($it);
                                            if ($it === '') continue;
                                            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $it, $m)) {
                                                $isolamentos[] = ['label' => trim($m[1]), 'value' => trim($m[2])];
                                            } else {
                                                $isolamentos[] = ['text' => $it];
                                            }
                                        }
                                    }
                                @endphp
                                <div x-data="{ showIsolationModal: false }">
                                    <button
                                        type="button"
                                        @click="showIsolationModal = true; document.body.style.overflow = 'hidden'"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-yellow-400 text-black shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                                        aria-label="Ver isolamento"
                                    >
                                        <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="w-5 h-5" alt="Isolamento" />
                                    </button>

                                    {{-- Isolation Modal --}}
                                    <div x-show="showIsolationModal"
                                         x-cloak
                                         @click.self="showIsolationModal = false; document.body.style.overflow = ''"
                                         @keydown.escape.window="showIsolationModal = false; document.body.style.overflow = ''"
                                         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto modal-overlay"
                                         style="margin: 0 !important;"
                                    >
                                        <div class="bg-yellow-400 rounded-xl p-4 max-w-[90vw] w-[400px] shadow-2xl animate-[modal-slide-in_0.2s_ease-out]" @click.stop>
                                            <div class="flex items-start gap-2 mb-3">
                                                <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="h-5 w-5 flex-shrink-0 mt-0.5" alt="Isolamento" />
                                                <h3 class="text-sm font-bold text-black flex-1">Precauções de Isolamento</h3>
                                                <button @click="showIsolationModal = false; document.body.style.overflow = ''" class="text-black/70 hover:text-black transition-colors flex-shrink-0">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar">
                                                @if(empty($isolamentos))
                                                    <div class="text-black/70 text-xs p-2 bg-black/5 rounded-md">Motivo não especificado</div>
                                                @else
                                                    @foreach($isolamentos as $iso)
                                                        <div class="p-2 rounded-md bg-black/5 transition-colors duration-150 hover:bg-black/10">
                                                            @if(isset($iso['label']))
                                                                <div class="text-xs font-medium text-black">
                                                                    {{ $iso['label'] }}:
                                                                    <span class="text-xs text-black/80 font-normal"> {{ $iso['value'] }}</span>
                                                                </div>
                                                            @else
                                                                <div class="text-xs text-black">{{ $iso['text'] }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                            @if($patient['has_surgery'] ?? false)
                                <div x-data="{ showSurgeryModal: false }">
                                    <button
                                        type="button"
                                        @click="showSurgeryModal = true; document.body.style.overflow = 'hidden'"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-purple-600 text-white shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 hover:bg-purple-300 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                                        aria-label="Ver cirurgia"
                                    >
                                        <img src="{{ asset('images/icons/patient-card/surgery-procedure.svg') }}" class="w-6 h-6" />
                                    </button>

                                    {{-- Surgery Modal --}}
                                    <div x-show="showSurgeryModal"
                                         x-cloak
                                         @click.self="showSurgeryModal = false; document.body.style.overflow = ''"
                                         @keydown.escape.window="showSurgeryModal = false; document.body.style.overflow = ''"
                                         class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto modal-overlay"
                                         style="margin: 0 !important;"
                                    >
                                        <div class="bg-white rounded-xl p-4 max-w-[90vw] w-[400px] shadow-2xl animate-[modal-slide-in_0.2s_ease-out]" @click.stop>
                                            <div class="flex items-start gap-2 mb-3">
                                                <img src="{{ asset('images/icons/patient-card/surgery-procedure.svg') }}" class="h-5 w-5 flex-shrink-0 mt-0.5" />
                                                <h3 class="text-sm font-bold text-purple-800 flex-1">Cirurgia Agendada</h3>
                                                <button @click="showSurgeryModal = false; document.body.style.overflow = ''" class="text-gray-500 hover:text-gray-700 transition-colors flex-shrink-0">
                                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            <div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar">
                                                @if(!empty($patient['procedimentos_cirurgicos']) && is_array($patient['procedimentos_cirurgicos']))
                                                    @foreach($patient['procedimentos_cirurgicos'] as $c)
                                                        <div class="p-2 rounded-md bg-purple-50 transition-colors duration-150 hover:bg-purple-100">
                                                            <div class="text-xs font-medium text-purple-900">
                                                                {{ $c['data_agenda'] ?? 'N/A' }} @if(!empty($c['hora_agenda'])) às {{ $c['hora_agenda'] }}@endif
                                                            </div>
                                                            @if(!empty($c['local']))
                                                                <div class="text-[10px] text-purple-700 mt-0.5">{{ $c['local'] }}</div>
                                                            @endif
                                                            <div class="text-xs text-purple-800 mt-1">{{ $c['procedimento'] ?? $c['carater_cirurgia'] ?? 'Procedimento' }}</div>
                                                            @if(!empty($c['observacoes']))
                                                                <div class="text-[10px] text-purple-600 italic mt-1">Obs: {{ $c['observacoes'] }}</div>
                                                            @endif
                                                        </div>
                                                    @endforeach
                                                @else
                                                    <div class="text-xs text-purple-700 p-2 bg-purple-50 rounded-md">Verificar detalhes no modal</div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                        {{-- MEWS Badge --}}
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold shadow-sm whitespace-nowrap relative border
                                {{ $patient['mews_styling']['bg'] ?? 'bg-white/90' }}
                                {{ $patient['mews_styling']['text'] ?? 'text-gray-700' }}
                                {{ $patient['mews_styling']['border'] ?? 'border-gray-300' }}
                                {{ ($patient['mews_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                <strong>MEWS:</strong>
                                <span class="ml-1">{{ $patient['mews_score'] ?? '-' }}</span>
                                @if($patient['mews_shift'] ?? null)
                                    <span class="ml-0.5 text-[10px] font-normal">({{ $patient['mews_shift'] }})</span>
                                @endif
                                @if(($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                @endif
                            </span>
                        </div>
                    </div>
                    {{-- Row 2: Patient Name + Gender + Age --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
                        <div class="flex items-center gap-2">
                            @if(($patient['sexo'] ?? '') === 'F')
                                <x-iconoir-female class="text-pink-600 h-4 w-4 flex-shrink-0" />
                            @elseif(($patient['sexo'] ?? '') === 'M')
                                <x-iconoir-male class="text-blue-600 h-4 w-4 flex-shrink-0" />
                            @endif
                            <p class="text-gray-900 text-sm font-bold truncate flex-1 min-w-0">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
                            <span class="text-gray-700 text-xs font-semibold flex-shrink-0">{{ $patient['age'] ?? '?' }}a</span>
                            <span class="text-gray-700 text-[11px] font-semibold flex-shrink-0">({{ $patient['birth_date'] ?? '?' }})</span>
                        </div>
                    </div>
                    {{-- Row 3: Administrative Data --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
                        <div class="grid grid-cols-3 gap-x-2 gap-y-0.5 text-[10px]">
                            <div class="truncate text-center"><span class="text-gray-600 font-medium">At:</span> <span class="text-gray-900 font-semibold">{{ $patient['nr_atendimento'] ?? 'N/A' }}</span></div>
                            <div class="truncate text-center"><span class="text-gray-600 font-medium">Pr:</span> <span class="text-gray-900 font-semibold">{{ $patient['nr_prontuario'] ?? 'N/A' }}</span></div>
                            <div class="truncate text-center">
                                <span class="text-gray-600 font-medium">Int:</span>
                                @if($patient['is_new_patient'] ?? false)
                                    <span class="text-green-700 font-bold">Hoje</span>
                                @elseif(isset($patient['internment_days']) && $patient['internment_days'] !== null)
                                    <span class="text-gray-900 font-semibold">{{ ceil($patient['internment_days']) }}d</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </div>
                            <div class="text-center whitespace-nowrap overflow-hidden">
                                <span class="text-gray-600 font-medium">Cv:</span>
                                <span class="text-gray-900 font-semibold truncate">{{ explode(' ', $patient['convenio'] ?? 'N/A')[0] }}</span>
                            </div>
                            @if(!empty($patient['medico_responsavel'] ?? null))
                                <div class="col-span-2 text-center whitespace-nowrap overflow-hidden">
                                    <span class="text-gray-600 font-medium">Dr:</span>
                                    <span class="text-gray-900 font-semibold truncate">{{ $patient['medico_responsavel'] }}</span>
                                </div>
                            @else
                                <div class="col-span-2 text-center"><span class="text-gray-400">-</span></div>
                            @endif
                        </div>
                    </div>
                    {{-- Row 4: Risk Scales --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
                        <div class="flex flex-wrap gap-0.5 justify-center items-center min-h-[18px]">
                            @if($patient['has_patient'] ?? false)
                                {{-- Braden --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] border whitespace-nowrap relative
                                    {{ $patient['braden_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['braden_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['braden_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['braden_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>Braden:</strong>
                                    <span class="ml-1">{{ $patient['braden_score'] ?? '-' }}</span>
                                    @if($patient['braden_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['braden_shift'] }})</span>
                                    @endif
                                    @if(($patient['braden_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                {{-- Morse --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] border whitespace-nowrap relative
                                    {{ $patient['morse_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['morse_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['morse_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['morse_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>Morse:</strong>
                                    <span class="ml-1">{{ $patient['morse_score'] ?? '-' }}</span>
                                    @if($patient['morse_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['morse_shift'] }})</span>
                                    @endif
                                    @if(($patient['morse_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                {{-- Dor --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] border whitespace-nowrap relative
                                    {{ $patient['dor_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['dor_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['dor_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['dor_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>Dor:</strong>
                                    <span class="ml-1">{{ $patient['dor_score'] ?? '-' }}</span>
                                    @if($patient['dor_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['dor_shift'] }})</span>
                                    @endif
                                    @if(($patient['dor_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                {{-- TEV --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] border whitespace-nowrap relative
                                    {{ $patient['tev_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['tev_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['tev_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['tev_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>TEV:</strong>
                                    <span class="ml-1">{{ $patient['tev_score'] ?? '-' }}</span>
                                    @if($patient['tev_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['tev_shift'] }})</span>
                                    @endif
                                    @if(($patient['tev_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                            @else
                                <span class="text-[9px] text-gray-400 italic">Escalas aguardando avaliação</span>
                            @endif
                        </div>
                    </div>
                    {{-- Row 5: Priority Exams --}}
                    @if(!empty($patient['priority_exams'] ?? null))
                        <div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
                            <div class="text-[10px] text-gray-800 text-center">
                                <span class="font-bold">Exames Prioritários:</span>
                                <span>{{ $patient['priority_exams'] }}</span>
                            </div>
                        </div>
                    @endif
                    {{-- Row 6: Multidisciplinary --}}
                    @if(!empty($patient['multidisciplinary']) || !empty($patient['multidisciplinary_other']))
                        @php
                            $md = $patient['multidisciplinary'] ?? [];
                            $other = $patient['multidisciplinary_other'] ?? null;
                        @endphp
                        <div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
                            <div class="flex flex-wrap justify-center text-[10px] text-gray-700 gap-x-1 gap-y-1">
                                <span>Fisio <span class="{{ ($md['fisioterapia'] ?? false) ? 'text-green-700 font-bold' : 'text-gray-400' }}">({{ ($md['fisioterapia'] ?? false) ? '✓' : '–' }}) </span></span>
                                <span>Psico <span class="{{ ($md['psicologia'] ?? false) ? 'text-green-700 font-bold' : 'text-gray-400' }}">({{ ($md['psicologia'] ?? false) ? '✓' : '–' }}) </span></span>
                                <span>Nutri <span class="{{ ($md['nutricao'] ?? false) ? 'text-green-700 font-bold' : 'text-gray-400' }}">({{ ($md['nutricao'] ?? false) ? '✓' : '–' }}) </span></span>
                                <span>Fono <span class="{{ ($md['fonoaudiologia'] ?? false) ? 'text-green-700 font-bold' : 'text-gray-400' }}">({{ ($md['fonoaudiologia'] ?? false) ? '✓' : '–' }}) </span></span>
                                <span>S. Social <span class="{{ ($md['servico_social'] ?? false) ? 'text-green-700 font-bold' : 'text-gray-400' }}">({{ ($md['servico_social'] ?? false) ? '✓' : '–' }}) </span></span>
                                <span>Time <span class="{{ ($md['acessos_vasculares'] ?? false) ? 'text-green-700 font-bold' : 'text-gray-400' }}">({{ ($md['acessos_vasculares'] ?? false) ? '✓' : '–' }}) </span></span>
                            </div>
                            @if(!empty($other))
                                <div class=" text-[10px] text-gray-600 text-center">
                                    — Outro: <span class="text-gray-800 font-medium">{{ $other }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Pending Events Section --}}
                @php
                    $pendingEvents = $patient['pending_events'] ?? [];
                    $hasScroll = is_array($pendingEvents) && count($pendingEvents) >= 1;
                @endphp
                <div class="flex-1 min-h-0 px-2 sm:px-2.5 lg:px-3 overflow-hidden flex flex-col" x-data="{ showPendingModal: false }">
                    @if(!empty($pendingEvents) && is_array($pendingEvents))
                        <div class="bg-white/20 rounded-lg p-2">
                            <div class="flex items-center justify-between mb-1">
                                <h4 class="text-xs font-semibold text-gray-800">Pendências</h4>
                                <div class="flex items-center gap-1">
                                    <span class="text-[10px] text-gray-600">próx. horas</span>
                                    @if($hasScroll)
                                        <button
                                            @click="showPendingModal = true; document.body.style.overflow = 'hidden'"
                                            class="flex items-center justify-center w-5 h-5 rounded-full bg-blue-500/10 text-blue-500 transition-all duration-150 cursor-pointer hover:bg-blue-500/20 hover:scale-110"
                                            title="Ver todas as pendências"
                                        >
                                            <x-iconoir-expand class="text-blue-500-600 h-4 w-4 flex-shrink-0" />
                                        </button>
                                    @endif
                                </div>
                            </div>
                            <div class="h-[200px] max-h-[200px] overflow-y-auto overflow-x-hidden rounded-md p-1 my-1 custom-scrollbar-pending">
                                <div class="flex flex-col gap-2">
                                    @foreach($pendingEvents as $event)
                                        @php
                                            $icon = $event['icon'] ?? 'alert-circle.svg';
                                            $text = $event['text'] ?? '';
                                            $type = $event['type'] ?? 'default';
                                        @endphp
                                        <div class="flex items-start gap-2 p-1 transition-colors duration-150 rounded hover:bg-white/10">
                                            <img src="{{ asset('images/icons/patient-card/' . $icon) }}" class="w-5 h-5 flex-shrink-0" alt="{{ $type }}">
                                            <span class="text-[11px] text-gray-800 leading-snug break-words">{{ $text }}</span>
                                        </div>
                                    @endforeach
                                </div>
                            </div>

                            {{-- Pending Modal --}}
                            <div x-show="showPendingModal"
                                 x-cloak
                                 @click.self="showPendingModal = false; document.body.style.overflow = ''"
                                 @keydown.escape.window="showPendingModal = false; document.body.style.overflow = ''"
                                 class="fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4 overflow-y-auto modal-overlay"
                                 style="margin: 0 !important;"
                            >
                                <div class="bg-white rounded-xl p-4 max-w-[90vw] w-[400px] shadow-2xl animate-[modal-slide-in_0.2s_ease-out]" @click.stop>
                                    <div class="flex justify-between items-center mb-3">
                                        <h3 class="text-sm font-bold text-gray-800">Todas as Pendências</h3>
                                        <button @click="showPendingModal = false; document.body.style.overflow = ''" class="text-gray-500 hover:text-gray-700 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                            </svg>
                                        </button>
                                    </div>
                                    <div class="space-y-2 max-h-64 overflow-y-auto custom-scrollbar">
                                        @foreach($pendingEvents as $event)
                                            @php
                                                $icon = $event['icon'] ?? 'alert-circle.svg';
                                                $text = $event['text'] ?? '';
                                                $type = $event['type'] ?? 'default';
                                            @endphp
                                            <div class="flex items-start gap-2 p-2 rounded-md bg-gray-50 transition-colors duration-150 hover:bg-gray-100">
                                                <img src="{{ asset('images/icons/patient-card/' . $icon) }}" class="w-4 h-4 flex-shrink-0" alt="{{ $type }}">
                                                <span class="text-xs text-gray-800">{{ $text }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @else
                        <div class="flex items-center justify-center h-full w-full">
                            <div class="text-center">
                                <x-iconoir-walking class="text-gray-400 h-6 w-6 mx-auto" />
                                <p class="text-xs text-gray-500 font-medium">Sem pendências</p>
                                <p class="text-xs text-gray-400">próximas horas</p>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Details Button --}}
                <div class="flex-shrink-0 p-1.5 border-t border-white/10 z-10">
                    <button
                        type="button"
                        class="w-full bg-white/20 text-gray-700 px-3 py-2 rounded-md flex items-center justify-center gap-2 shadow-sm transition-all duration-150 text-xs font-medium backdrop-blur-[4px] cursor-pointer hover:not(:disabled):bg-white/30 hover:not(:disabled):shadow-md active:not(:disabled):bg-white/40 disabled:bg-white/10 disabled:text-gray-400 disabled:cursor-not-allowed focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                        x-data="{ isLoading: false }"
                        @click.prevent="isLoading = true; $dispatch('openModal', { attendanceNumber: {{ $patient['nr_atendimento'] ?? 0 }}, hospital: '{{ $currentHospitalName ?? '' }}' }); setTimeout(() => isLoading = false, 2000)"
                        :disabled="isLoading"
                    >
                        <template x-if="isLoading">
                            <div class="flex items-center gap-2">
                                <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-600 border-t-transparent"></div>
                                <span>Carregando...</span>
                            </div>
                        </template>
                        <template x-if="!isLoading">
                            <div class="flex items-center gap-2">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                                <span>Detalhes</span>
                            </div>
                        </template>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- CSS --}}
<style>
    /* Animação do Modal */
    @keyframes modal-slide-in {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Force definitive card size and prevent children from expanding */
    .patient-card .card-inner.patient-card-fixed {
        height: 400px !important;
        max-height: 400px !important;
        display: flex;
        flex-direction: column;
    }

    /* Ensure direct flex children can shrink properly */
    .patient-card .card-inner > * {
        min-height: 0;
    }

    /* Prevent inner flex-grow items from increasing card height */
    .patient-card .card-inner .flex-1,
    .patient-card .card-inner .flex-grow,
    .patient-card .card-inner .h-full {
        min-height: 0;
    }

    /* Keep scrollable areas constrained */
    .patient-card .card-inner .custom-scrollbar-pending {
        overflow: auto;
    }

    /* Scrollbar Customizado */
    .custom-scrollbar-pending::-webkit-scrollbar,
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar-pending::-webkit-scrollbar-track,
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 3px;
    }

    .custom-scrollbar-pending::-webkit-scrollbar-thumb,
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.25);
        border-radius: 3px;
    }

    .custom-scrollbar-pending::-webkit-scrollbar-thumb:hover,
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.45);
    }

    .custom-scrollbar-pending,
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 0, 0, 0.25) rgba(0, 0, 0, 0.05);
    }

    /* Alpine.js Cloak */
    [x-cloak] {
        display: none !important;
    }
</style>
