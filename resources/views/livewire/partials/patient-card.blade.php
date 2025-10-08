{{-- resources/views/livewire/partials/patient-card.blade.php - VERSÃO CORRIGIDA --}}
<div class="relative patient-card">
    <div class="rounded-xl shadow-lg h-[28rem] sm:h-80 lg:h-96 overflow-hidden">
        @if(!($patient['has_patient'] ?? false))
            {{-- Empty Bed Card --}}
            <div class="h-full bg-gradient-to-br from-gray-200 to-gray-300 p-3 sm:p-4 flex flex-col">
                <div class="flex justify-between items-center mb-3 flex-shrink-0">
                    <span class="bg-white/70 text-gray-700 text-xs font-bold px-2.5 py-0.5 rounded-full">
                        Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                    </span>
                </div>

                <div class="flex-grow flex items-center justify-center">
                    <div class="text-center">
                        <svg class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                        </svg>
                        <p class="text-gray-500 text-sm font-medium">Leito Vago</p>
                    </div>
                </div>
            </div>
        @else
            {{-- Occupied Bed Card --}}
            <div class="h-full bg-gradient-to-br {{ $patient['gradient_class'] }} {{ $patient['border_class'] }} flex flex-col">
                
                {{-- Header Compacto --}}
                <div class="p-2 sm:p-2.5 flex-shrink-0 space-y-1.5">
                    
                    {{-- Linha 1: Leito + Alertas + MEWS + Botão Modal --}}
                    <div class="flex justify-between items-center gap-2">
                        <span class="bg-white/80 text-gray-800 text-xs font-bold px-2 py-0.5 rounded-full shadow-sm flex-shrink-0">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>

                        {{-- Alertas centralizados com tamanho uniforme --}}
                        <div class="flex items-center justify-center gap-1.5 flex-1">
                            @if($patient['has_allergy'] ?? false)
                                <div class="relative group">
                                    <button 
                                        type="button"
                                        class="alert-icon w-7 h-7 bg-red-500 text-white rounded-full flex items-center justify-center shadow-lg animate-pulse hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-red-400 focus:ring-opacity-50"
                                        aria-label="Ver alergias"
                                        tabindex="0"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </button>
                                    
                                    {{-- Tooltip responsivo --}}
                                    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 bg-red-500 text-white text-xs rounded-lg shadow-lg w-72 max-w-[90vw] z-50">
                                        <div class="font-semibold mb-1 flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            Alergias Registradas
                                        </div>
                                        <div class="text-xs leading-relaxed">
                                            @php
                                                $alergias = $patient['alergias_detalhadas'] ?? 'Nenhuma alergia registrada';
                                                
                                                if (empty($alergias) || $alergias === 'Nenhuma alergia registrada' || $alergias === 'Sem alergias registradas') {
                                                    echo 'Nenhuma alergia registrada no sistema.';
                                                } else {
                                                    $allergiesText = strip_tags($alergias);
                                                    $allergiesText = trim($allergiesText);
                                                    
                                                    if (str_contains($allergiesText, ';') || str_contains($allergiesText, ',')) {
                                                        $items = preg_split('/[;,]/', $allergiesText);
                                                        $formattedItems = [];
                                                        
                                                        foreach (array_slice($items, 0, 3) as $item) {
                                                            $item = trim($item);
                                                            if (!empty($item)) {
                                                                $formattedItems[] = "• " . $item;
                                                            }
                                                        }
                                                        
                                                        if (count($items) > 3) {
                                                            $formattedItems[] = "• ... e mais " . (count($items) - 3) . " alergia(s)";
                                                        }
                                                        
                                                        echo implode("\n", $formattedItems);
                                                    } else {
                                                        echo "<strong>Alergia:</strong> " . Str::limit($allergiesText, 150);
                                                    }
                                                }
                                            @endphp
                                        </div>
                                        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-red-500"></div>
                                    </div>
                                </div>
                            @endif

                            @if($patient['has_isolation'] ?? false)
                                <div class="relative group">
                                    <button 
                                        type="button"
                                        class="alert-icon w-7 h-7 bg-yellow-400 rounded-full flex items-center justify-center shadow-lg animate-pulse hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-yellow-400 focus:ring-opacity-50"
                                        aria-label="Ver isolamento"
                                        tabindex="0"
                                    >
                                        <img src="{{ asset('images/icons/patient-isolated.svg') }}" class="h-5 w-5" alt="Isolamento" />
                                    </button>
                                    
                                    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 bg-yellow-400 text-black text-xs rounded-lg shadow-lg w-72 max-w-[90vw] z-50">
                                        <div class="font-semibold mb-1 flex items-center gap-1">
                                            <img src="{{ asset('images/icons/patient-isolated.svg') }}" class="h-4 w-4" alt="Isolamento" />
                                            Precauções de Isolamento
                                        </div>
                                        <div class="text-xs leading-relaxed">
                                            @php
                                                $isolamento = $patient['motivos_isolamento'] ?? 'Nenhum motivo de isolamento';
                                                $isolamento = strip_tags($isolamento);
                                                $isolamento = preg_replace('/\s+/', ' ', $isolamento);
                                                $isolamento = trim($isolamento);
                                                
                                                if (empty($isolamento) || $isolamento === 'Nenhum motivo de isolamento' || $isolamento === 'Não') {
                                                    echo 'Paciente em precauções de isolamento. Motivo não especificado no sistema.';
                                                } else {
                                                    echo Str::limit($isolamento, 120);
                                                }
                                            @endphp
                                        </div>
                                        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-yellow-400"></div>
                                    </div>
                                </div>
                            @endif

                            @if($patient['has_surgery'] ?? false)
                                <div class="relative group">
                                    <div class="alert-icon w-7 h-7 bg-white rounded-full flex items-center justify-center shadow-lg animate-pulse hover:scale-110 transition-transform focus:outline-none focus:ring-2 focus:ring-purple-400 focus:ring-opacity-50 border border-purple-200 cursor-pointer"
                                         tabindex="0"
                                         role="button"
                                         aria-label="Ver cirurgia">
                                        <svg class="w-5 h-5 flex-shrink-0 text-purple-500" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M10 34H20V36H10V34Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M28 34H32V36H28V34Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M22 34H26V36H22V34Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M34 34H38V36H34V34Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M26.7071 21.2929C27.0976 21.6834 27.0976 22.3166 26.7071 22.7071L23.7071 25.7071C23.3166 26.0976 22.6834 26.0976 22.2929 25.7071C21.9024 25.3166 21.9024 24.6834 22.2929 24.2929L25.2929 21.2929C25.6834 20.9024 26.3166 20.9024 26.7071 21.2929Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M36.7071 8.1075L29.1075 15.7071L27.6933 14.2929L35.2929 6.69328L36.7071 8.1075Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M38.2071 12.3925C38.5976 12.783 38.5976 13.4162 38.2071 13.8067L32.7071 19.3067L31.2929 17.8925L36.7929 12.3925C37.1834 12.002 37.8166 12.002 38.2071 12.3925Z" fill="currentColor"/>
                                        </svg>
                                    </div>
                                    
                                    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 bg-white text-purple-800 text-xs rounded-lg shadow-lg w-72 max-w-[90vw] border border-purple-200 z-50">
                                        <div class="font-semibold mb-1 flex items-center gap-1">
                                            <svg class="h-3 w-3 text-purple-500" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                                        </div>
                                        <div class="text-xs leading-relaxed">
                                            @php
                                                $cirurgias = $patient['cirurgias_info'] ?? '';
                                                
                                                if (!empty($patient['procedimentos_cirurgicos']) && is_array($patient['procedimentos_cirurgicos'])) {
                                                    $cirurgiasFormatadas = collect($patient['procedimentos_cirurgicos'])
                                                        ->map(function($c) {
                                                            $data = $c['data_agenda'] ?? 'Data não definida';
                                                            $hora = $c['hora_agenda'] ?? '00:00';
                                                            $procedimento = $c['procedimento'] ?? $c['carater_cirurgia'] ?? 'Procedimento não especificado';
                                                            return "{$data} às {$hora} - {$procedimento}";
                                                        })
                                                        ->take(2)
                                                        ->join("\n• ");
                                                    echo '• ' . $cirurgiasFormatadas;
                                                } else if (!empty($cirurgias)) {
                                                    $cirurgias = strip_tags($cirurgias);
                                                    $cirurgias = preg_replace('/\s+/', ' ', $cirurgias);
                                                    echo Str::limit($cirurgias, 120);
                                                } else {
                                                    echo 'Paciente com cirurgia agendada. Verificar detalhes no modal.';
                                                }
                                            @endphp
                                        </div>
                                        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- MEWS --}}
                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold text-gray-700 bg-white/90 shadow-sm whitespace-nowrap relative border border-gray-300
                                {{ ($patient['mews_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                {{ ($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? 'text-red-600' : '' }}">
                                MEWS:
                                @if(isset($patient['mews_score']) && $patient['mews_score'] !== null)
                                    {{ $patient['mews_score'] }}
                                    @if(isset($patient['mews_shift']) && $patient['mews_shift'] && function_exists('getShiftLabel'))
                                        <span class="ml-0.5 text-[10px] opacity-70">({{ substr(getShiftLabel($patient['mews_shift']), 0, 1) }})</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                                @if(($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                @endif
                            </span>
                        </div>
                    </div>

                    {{-- Linha 2: Nome + Sexo + Idade --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm">
                        <div class="flex items-center gap-2">
                            @if(($patient['sexo'] ?? '') === 'F')
                                <x-iconoir-female class="text-pink-600 h-4 w-4 flex-shrink-0" />
                            @elseif(($patient['sexo'] ?? '') === 'M')
                                <x-iconoir-male class="text-blue-600 h-4 w-4 flex-shrink-0" />
                            @endif
                            <p class="text-gray-900 text-sm font-bold truncate flex-1">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
                            <span class="text-gray-700 text-xs font-semibold flex-shrink-0">{{ $patient['age'] ?? '?' }}a</span>
                        </div>
                    </div>

                    {{-- Linha 3: Dados administrativos compactos --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm">
                        <div class="grid grid-cols-3 gap-x-2 gap-y-0.5 text-[10px]">
                            <div class="truncate text-center"><span class="text-gray-600 font-medium">At:</span> <span class="text-gray-900 font-semibold">{{ $patient['nr_atendimento'] ?? 'N/A' }}</span></div>
                            <div class="truncate text-center"><span class="text-gray-600 font-medium">Pr:</span> <span class="text-gray-900 font-semibold">{{ $patient['nr_prontuario'] ?? 'N/A' }}</span></div>
                            <div class="truncate text-center">
                                <span class="text-gray-600 font-medium">Int:</span>
                                @if($patient['is_new_patient'] ?? false)
                                    <span class="text-green-700 font-bold">Hoje</span>
                                @elseif(isset($patient['internment_days']) && $patient['internment_days'] !== null)
                                    @php $days = ceil($patient['internment_days']); @endphp
                                    <span class="text-gray-900 font-semibold">{{ $days }}d</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </div>
                            
                            <div class="text-center whitespace-nowrap overflow-hidden">
                                <span class="text-gray-600 font-medium">Cv:</span> 
                                @php
                                    $convenio = $patient['convenio'] ?? 'N/A';
                                    $primeirapalavra = explode(' ', $convenio)[0];
                                @endphp
                                <span class="text-gray-900 font-medium">{{ $primeirapalavra }}</span>
                            </div>
                            @if(!empty($patient['medico_responsavel'] ?? null))
                                <div class="col-span-2 text-center whitespace-nowrap overflow-hidden">
                                    <span class="text-gray-600 font-medium">Dr:</span> 
                                    <span class="text-gray-900 font-medium truncate inline-block max-w-[180px] align-bottom">{{ $patient['medico_responsavel'] }}</span>
                                </div>
                            @else
                                <div class="col-span-2 text-center"><span class="text-gray-400">-</span></div>
                            @endif
                        </div>
                    </div>
                    
                    {{-- Linha 4: Escalas assistenciais compactas COM CORES --}}
                    <div class="bg-white/70 rounded-lg px-1.5 py-0.5 shadow-sm">
                        <div class="flex flex-wrap gap-0.5 justify-center items-center min-h-[18px]">
                            @if($patient['has_patient'] ?? false)
                                @php
                                    $isPediatric = isset($patient['age']) && intval($patient['age']) < 16;
                                @endphp
                                
                                @if(!$isPediatric)
                                    @php
                                        $mewsScore = $patient['mews_score'] ?? null;
                                        $mewsStyling = $patient['mews_styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                        $mewsShiftValue = $patient['mews_shift'] ?? null;
                                        $mewsShift = $mewsShiftValue ? ' (' . substr(getShiftLabel($mewsShiftValue), 0, 1) . ')' : '';
                                    @endphp
                                    <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] {{ $mewsStyling['bg'] }} {{ $mewsStyling['text'] }} border {{ $mewsStyling['border'] }} whitespace-nowrap relative font-semibold
                                        {{ ($patient['mews_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? '!text-red-600' : '' }}">
                                        MW:{{ $mewsScore ?? '-' }}<span class="text-[10px] font-bold ml-0.5">{{ $mewsShift }}</span>
                                        @if(($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                            <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @else
                                    @php
                                        $pewsScore = $patient['pews_score'] ?? null;
                                        $pewsStyling = $patient['pews_styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                        $pewsShiftValue = $patient['pews_shift'] ?? null;
                                        $pewsShift = $pewsShiftValue ? ' (' . substr(getShiftLabel($pewsShiftValue), 0, 1) . ')' : '';
                                    @endphp
                                    <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] {{ $pewsStyling['bg'] }} {{ $pewsStyling['text'] }} border {{ $pewsStyling['border'] }} whitespace-nowrap relative font-semibold
                                        {{ ($patient['pews_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['pews_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? '!text-red-600' : '' }}">
                                        Pw:{{ $pewsScore ?? '-' }}<span class="text-[10px] font-bold ml-0.5">{{ $pewsShift }}</span>
                                        @if(($patient['pews_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                            <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @endif
                                
                                @php
                                    $bradenScore = $patient['braden_score'] ?? null;
                                    $bradenStyling = $patient['braden_styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                    $bradenShiftValue = $patient['braden_shift'] ?? null;
                                    $bradenShift = $bradenShiftValue ? ' (' . substr(getShiftLabel($bradenShiftValue), 0, 1) . ')' : ' (24h)';
                                @endphp
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] {{ $bradenStyling['bg'] }} {{ $bradenStyling['text'] }} border {{ $bradenStyling['border'] }} whitespace-nowrap relative font-semibold
                                    {{ ($patient['braden_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                    {{ ($patient['braden_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? '!text-red-600' : '' }}">
                                    Bd:{{ $bradenScore ?? '-' }}<span class="text-[10px] font-bold ml-0.5">{{ $bradenShift }}</span>
                                    @if(($patient['braden_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                
                                @php
                                    $morseScore = $patient['morse_score'] ?? null;
                                    $morseStyling = $patient['morse_styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                    $morseShiftValue = $patient['morse_shift'] ?? null;
                                    $morseShift = $morseShiftValue ? ' (' . substr(getShiftLabel($morseShiftValue), 0, 1) . ')' : ' (24h)';
                                @endphp
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] {{ $morseStyling['bg'] }} {{ $morseStyling['text'] }} border {{ $morseStyling['border'] }} whitespace-nowrap relative font-semibold
                                    {{ ($patient['morse_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                    {{ ($patient['morse_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? '!text-red-600' : '' }}">
                                    Ms:{{ $morseScore ?? '-' }}<span class="text-[10px] font-bold ml-0.5">{{ $morseShift }}</span>
                                    @if(($patient['morse_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                @php
                                    $dorScore = $patient['dor_score'] ?? null;
                                    $dorStyling = $patient['dor_styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                    $dorShiftValue = $patient['dor_shift'] ?? null;
                                    $dorShift = $dorShiftValue ? ' (' . substr(getShiftLabel($dorShiftValue), 0, 1) . ')' : '';
                                @endphp
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] {{ $dorStyling['bg'] }} {{ $dorStyling['text'] }} border {{ $dorStyling['border'] }} whitespace-nowrap relative font-semibold
                                    {{ ($patient['dor_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                    {{ ($patient['dor_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? '!text-red-600' : '' }}">
                                    Dr:{{ $dorScore ?? '-' }}<span class="text-[10px] font-bold ml-0.5">{{ $dorShift }}</span>
                                    @if(($patient['dor_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                
                                @php
                                    $tevScore = $patient['tev_score'] ?? null;
                                    $tevStyling = $patient['tev_styling'] ?? ['bg' => 'bg-gray-50', 'border' => 'border-gray-300', 'text' => 'text-gray-800'];
                                    $tevShiftValue = $patient['tev_shift'] ?? null;
                                    $tevShift = $tevShiftValue ? ' (' . substr(getShiftLabel($tevShiftValue), 0, 1) . ')' : ' (24h)';
                                @endphp
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] {{ $tevStyling['bg'] }} {{ $tevStyling['text'] }} border {{ $tevStyling['border'] }} whitespace-nowrap relative font-semibold
                                    {{ ($patient['tev_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                    {{ ($patient['tev_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? '!text-red-600' : '' }}">
                                    Tv:{{ $tevScore ?? '-' }}<span class="text-[10px] font-bold ml-0.5">{{ $tevShift }}</span>
                                    @if(($patient['tev_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                            @else
                                <span class="text-[9px] text-gray-400 italic">Escalas aguardando avaliação</span>
                            @endif
                        </div>
                    </div>

                    {{-- Linha 5: Exames Prioritários --}}
                    @if(!empty($patient['prioridade_exames'] ?? null))
                        <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm">
                            <div class="text-[10px] text-gray-800 text-center">
                                <span class="font-bold">Exames Prioritários:</span> {{ $patient['prioridade_exames'] }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Pending Events List --}}
                @if(!empty($patient['pending_events_filtered']))
                    <div class="flex-1 min-h-0 px-2 sm:px-2.5 pb-1.5">
                        <div class="bg-white/20 rounded-lg p-1.5 h-full overflow-hidden flex flex-col">
                            <div class="flex items-center justify-between mb-1 flex-shrink-0">
                                <h4 class="text-xs font-semibold text-gray-800">Pendências:</h4>
                                <span class="text-[10px] text-gray-600">próx. horas</span>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar">
                                <ul class="text-[11px] text-gray-800 space-y-0.5">
                                    @foreach($patient['pending_events_filtered'] as $pendencia)
                                        <li class="flex items-start gap-1.5 py-0.5">
                                            @if(str_contains($pendencia, '[ALTA'))
                                                <img src="{{ asset('images/icons/physician-arrow-up.svg') }}" class="w-4 h-4 flex-shrink-0 mt-0.5" alt="Alta" />
                                            @elseif(str_contains($pendencia, '[PREVISÃO DE ALTA'))
                                                <img src="{{ asset('images/icons/physician-arrow-up.svg') }}" class="w-4 h-4 flex-shrink-0 mt-0.5" alt="Prev. alta" />
                                            @elseif(str_contains($pendencia, '[Proc]'))
                                                <svg class="w-4 h-4 flex-shrink-0 text-red-500 mt-0.5" viewBox="0 0 24 24" fill="none">
                                                    <path d="M1.5 12.5L5.57574 16.5757C5.81005 16.8101 6.18995 16.8101 6.42426 16.5757L9 14" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                                    <path d="M16 7L12 11" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                                    <path d="M7 12L11.5757 16.5757C11.8101 16.8101 12.1899 16.8101 12.4243 16.5757L22 7" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                                                </svg>
                                            @elseif(str_contains($pendencia, '[Cir]'))
                                                <svg class="w-4 h-4 flex-shrink-0 text-purple-950 mt-0.5" viewBox="0 0 48 48" fill="none">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                                                </svg>
                                            @elseif(str_contains($pendencia, '[Exame]'))
                                                <svg class="w-4 h-4 rounded-full bg-yellow-500 text-black flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M15.2501 6.5C16.4927 6.5 17.5001 5.49264 17.5001 4.25C17.5001 3.00736 16.4927 2 15.2501 2C14.0074 2 13.0001 3.00736 13.0001 4.25C13.0001 5.49264 14.0074 6.5 15.2501 6.5Z" fill="currentColor"/>
                                                    <path d="M12.3827 6.49876C10.8875 6.28944 7.47101 6.89609 6.06373 10.6488C5.86981 11.166 6.13181 11.7424 6.64893 11.9363C7.16605 12.1302 7.74247 11.8682 7.93639 11.3511C8.5197 9.7956 9.57155 9.03454 10.5097 8.69638L9.34067 11.7021C9.32145 11.7515 9.30642 11.8015 9.29542 11.8518C9.20171 12.1529 9.25147 12.4933 9.45894 12.7616L13.0211 17.3687L13.252 21.0623C13.2864 21.6135 13.7612 22.0325 14.3124 21.998C14.8636 21.9636 15.2826 21.4888 15.2481 20.9376L14.9789 16.6312L12.8861 13.9244L14.2594 11.2629L14.3519 11.3973C14.8887 12.1774 15.8991 12.4741 16.7725 12.1081L18.8866 11.2222C19.3959 11.0087 19.6358 10.4228 19.4224 9.91341C19.2089 9.40404 18.6229 9.16415 18.1136 9.3776L15.9995 10.2635L14.393 7.92894C14.0375 7.31458 13.4664 6.81797 12.7317 6.5684C12.6163 6.52917 12.4991 6.50636 12.3827 6.49876Z" fill="currentColor"/>
                                            </svg>
                                            @elseif(str_contains($pendencia, '[Rec]'))
                                                <svg width="24" height="24" class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M11.9999 6.95459C12.5522 6.95459 12.9999 7.40231 12.9999 7.95459V11.0004H16.0456C16.5979 11.0004 17.0456 11.4481 17.0456 12.0004C17.0456 12.5526 16.5979 13.0004 16.0456 13.0004H12.9999V16.0459C12.9999 16.5982 12.5522 17.0459 11.9999 17.0459C11.4476 17.0459 10.9999 16.5982 10.9999 16.0459V13.0004H7.95435C7.40206 13.0004 6.95435 12.5526 6.95435 12.0004C6.95435 11.4481 7.40206 11.0004 7.95435 11.0004H10.9999V7.95459C10.9999 7.40231 11.4476 6.95459 11.9999 6.95459Z" fill="currentColor"/>
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12Z" fill="currentColor"/>
                                                </svg>
                                            @else
                                                <span class="inline-block w-2 h-2 rounded-full bg-gray-400 mt-1.5 flex-shrink-0"></span>
                                            @endif
                                            <span class="text-xs leading-tight break-words flex-1">{{ $pendencia }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @else
                    <div class="flex-1 px-2 sm:px-3 pb-1 flex items-center justify-center">
                        <div class="text-center py-2">
                            <p class="text-xs text-gray-500 font-medium">Sem pendências</p>
                            <p class="text-xs text-gray-400">próximas horas</p>
                        </div>
                    </div>
                @endif

                {{-- Botão Modal - CORRIGIDO --}}
                <div class="p-1.5 flex-shrink-0 border-t border-white/10">
                    <button 
                        type="button"
                        class="modal-open-btn w-full relative bg-white/20 hover:bg-white/30 active:bg-white/40 disabled:bg-white/10 disabled:cursor-not-allowed text-gray-700 disabled:text-gray-500 py-2 px-3 rounded-md flex items-center justify-center gap-1.5 shadow-sm transition-all duration-150 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-opacity-50 text-xs font-medium backdrop-blur-sm transform hover:scale-[1.02] active:scale-[0.98]"
                        title="Ver detalhes completos do paciente"
                        aria-label="Abrir detalhes completos do paciente"
                        wire:click="$dispatch('openModal', { attendanceNumber: {{ $patient['nr_atendimento'] ?? 0 }}, hospital: '{{ $currentHospitalName }}' })"
                        wire:loading.attr="disabled"
                    >
                        {{-- Loading spinner --}}
                        <div 
                            wire:loading 
                            class="absolute inset-0 flex items-center justify-center bg-white/30 rounded-md"
                        >
                            <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-600 border-t-transparent"></div>
                        </div>
                        
                        {{-- Normal state --}}
                        <div 
                            wire:loading.remove 
                            class="flex items-center gap-1.5"
                        >
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                            </svg>
                            Detalhes
                        </div>
                    </button>
                </div>

            </div>
        @endif
    </div>
</div>

<style>
/* Custom scrollbar optimizado */
.custom-scrollbar::-webkit-scrollbar {
    width: 3px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.1);
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.3);
    border-radius: 2px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.5);
}

.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.3) rgba(255,255,255,0.1);
}

/* Melhorar touch no mobile */
.patient-card {
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
}

/* Tooltips responsivos e uniformes */
.tooltip-container {
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out;
    z-index: 50 !important;
    pointer-events: none;
}

.group:hover .tooltip-container {
    opacity: 1;
    visibility: visible;
}

.group:focus-within .tooltip-container {
    opacity: 1;
    visibility: visible;
}

@media (hover: none) and (pointer: coarse) {
    .group:active .tooltip-container {
        opacity: 1;
        visibility: visible;
    }
}

@media (max-width: 640px) {
    .tooltip-container {
        position: fixed !important;
        left: 50% !important;
        transform: translateX(-50%) !important;
        bottom: auto !important;
        top: 120px !important;
        max-width: calc(100vw - 32px) !important;
        margin: 0 !important;
        width: auto !important;
    }
    
    .tooltip-arrow {
        display: none !important;
    }
}

.tooltip-container {
    white-space: normal;
    word-wrap: break-word;
    hyphens: auto;
}

.group .tooltip-container {
    transform: translateX(-50%) translateY(2px);
}

.group:hover .tooltip-container,
.group:focus-within .tooltip-container,
.group.tooltip-active .tooltip-container {
    transform: translateX(-50%) translateY(0);
}

.alert-icon {
    min-width: 28px;
    min-height: 28px;
    max-width: 28px;
    max-height: 28px;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.alert-icon svg {
    width: 20px !important;
    height: 20px !important;
    flex-shrink: 0;
}

.alert-icon img {
    width: 20px !important;
    height: 20px !important;
    flex-shrink: 0;
}

.alert-icon:focus {
    outline: 2px solid rgba(59, 130, 246, 0.5);
    outline-offset: 2px;
}

.group.tooltip-active .tooltip-container {
    opacity: 1;
    visibility: visible;
}

@media (max-width: 480px) {
    .tooltip-container {
        font-size: 11px !important;
        padding: 8px 12px !important;
        max-width: calc(100vw - 16px) !important;
    }
}
</style>

{{-- JavaScript para suporte touch em tooltips --}}
<script>
document.addEventListener('DOMContentLoaded', function() {
    const alertIcons = document.querySelectorAll('.alert-icon');
    
    alertIcons.forEach(icon => {
        const group = icon.closest('.group');
        let tooltipTimeout;
        
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            document.querySelectorAll('.group.tooltip-active').forEach(g => {
                if (g !== group) g.classList.remove('tooltip-active');
            });
            
            group.classList.toggle('tooltip-active');
            
            if (window.innerWidth <= 640) {
                clearTimeout(tooltipTimeout);
                if (group.classList.contains('tooltip-active')) {
                    tooltipTimeout = setTimeout(() => {
                        group.classList.remove('tooltip-active');
                    }, 5000);
                }
            }
        });
        
        document.addEventListener('click', function(e) {
            if (!group.contains(e.target)) {
                group.classList.remove('tooltip-active');
            }
        });
        
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                group.classList.remove('tooltip-active');
            }
        });
    });
});
</script>