<div class="relative patient-card max-w-full">
    <div class="card-inner rounded-xl shadow-lg h-[28rem] sm:h-80 lg:h-96 overflow-hidden max-w-full min-w-0">
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

                        <div class="flex-grow flex items-center justify-center min-w-0">
                            <div class="text-center max-w-full">
                                <svg class="mx-auto h-10 w-10 sm:h-12 sm:w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p class="text-gray-500 text-sm font-medium truncate">Leito Vago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Occupied Bed Card --}}
            <div class="h-full bg-gradient-to-br {{ $patient['gradient_class'] }} {{ $patient['border_class'] }} flex flex-col min-h-0">
                
                {{-- Header Compacto --}}
                <div class="p-2 sm:p-2.5 flex-shrink-0 space-y-1.5 min-w-0">
                    
                    {{-- Linha 1: Leito + Alertas + MEWS + Botão Modal --}}
                    <div class="flex justify-between items-center gap-2 min-w-0">
                        <span class="bg-white/80 text-gray-800 text-xs font-bold px-2 py-0.5 rounded-full shadow-sm flex-shrink-0">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>

                        {{-- Alertas centralizados com tamanho uniforme --}}
                        <div class="flex items-center justify-center gap-1.5 flex-1 min-w-0">
                            {{-- Tooltip de Alergias - REFORMATADO --}}
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
                                    </button>
                                    
                                    {{-- Tooltip responsivo --}}
                                    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 bg-red-500 text-white text-xs rounded-lg shadow-lg w-72 max-w-[90vw] z-50">
                                        <div class="font-semibold mb-1 flex items-center gap-1">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                            </svg>
                                            Alergias Registradas
                                        </div>
                                        <div class="text-xs leading-relaxed space-y-0.5">
                                            @php
                                                $alergias = $patient['alergias_detalhadas'] ?? '';
                                                 
                                                if (empty($alergias) || $alergias === 'Sem alergias registradas') {
                                                    echo '<span class="text-white/80">Nenhuma alergia registrada</span>';
                                                } else {
                                                    // Remove tags HTML e limpa o texto
                                                    $alergias = strip_tags($alergias);
                                                    $alergias = preg_replace('/\s+/', ' ', $alergias);
                                                     
                                                    // Separa por ponto e vírgula
                                                    $items = preg_split('/[;]/', $alergias);
                                                     
                                                    $alergiasList = [];
                                                    foreach ($items as $item) {
                                                        $item = trim($item);
                                                        if (!empty($item)) {
                                                            // Tenta separar medicamento de gravidade
                                                            if (preg_match('/^(.+?)\s*[-–]\s*(.+)$/u', $item, $matches)) {
                                                                $med = trim($matches[1]);
                                                                $gravidade = trim($matches[2]);
                                                                 
                                                                // Formata gravidade
                                                                $gravidadeClass = 'text-white/70';
                                                                if (stripos($gravidade, 'grave') !== false || stripos($gravidade, 'severa') !== false) {
                                                                    $gravidadeClass = 'font-bold text-yellow-200';
                                                                } elseif (stripos($gravidade, 'moderada') !== false) {
                                                                    $gravidadeClass = 'text-yellow-100';
                                                                }
                                                                 
                                                                $alergiasList[] = '<div class="flex justify-between gap-2 min-w-0"><span class="font-medium truncate">• ' . htmlspecialchars($med) . '</span><span class="' . $gravidadeClass . ' text-[10px]">' . htmlspecialchars($gravidade) . '</span></div>';
                                                            } else {
                                                                $alergiasList[] = '<div class="min-w-0">• ' . htmlspecialchars($item) . '</div>';
                                                            }
                                                        }
                                                    }
                                                     
                                                    // Mostra no máximo 5 alergias
                                                    $toShow = array_slice($alergiasList, 0, 5);
                                                    echo implode('', $toShow);
                                                     
                                                    if (count($alergiasList) > 5) {
                                                        echo '<div class="text-white/70 text-[10px] mt-1">... e mais ' . (count($alergiasList) - 5) . ' alergia(s)</div>';
                                                    }
                                                }
                                            @endphp
                                        </div>
                                        {{-- Seta do tooltip --}}
                                        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-red-500"></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Tooltip de Isolamento - REFORMATADO --}}
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
                                    
                                    {{-- Tooltip responsivo --}}
                                    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 bg-yellow-400 text-black text-xs rounded-lg shadow-lg w-72 max-w-[90vw] z-50">
                                        <div class="font-semibold mb-1 flex items-center gap-1">
                                            <img src="{{ asset('images/icons/patient-isolated.svg') }}" class="h-4 w-4" alt="Isolamento" />
                                            Precauções de Isolamento
                                        </div>
                                        <div class="text-xs leading-relaxed">
                                            @php
                                                $isolamento = $patient['motivos_isolamento'] ?? '';
                                                $isolamento = strip_tags($isolamento);
                                                $isolamento = trim($isolamento);
                                                 
                                                if (empty($isolamento) || $isolamento === 'Não') {
                                                    echo '<span class="text-black/70">Motivo não especificado</span>';
                                                } else {
                                                    // Formata o texto de isolamento
                                                    $isolamento = str_replace(' - ', ':<br><strong>', $isolamento);
                                                    $isolamento .= '</strong>';
                                                    echo $isolamento;
                                                }
                                            @endphp
                                        </div>
                                        {{-- Seta do tooltip --}}
                                        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-yellow-400"></div>
                                    </div>
                                </div>
                            @endif

                            {{-- Tooltip de Cirurgia - REFORMATADO --}}
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
                                    
                                    {{-- Tooltip responsivo --}}
                                    <div class="tooltip-container absolute top-full left-1/2 transform -translate-x-1/2 mt-2 px-3 py-2 bg-white text-purple-800 text-xs rounded-lg shadow-lg w-72 max-w-[90vw] border border-purple-200 z-50">
                                        <div class="font-semibold mb-1 flex items-center gap-1">
                                            <svg class="h-3 w-3 text-purple-500" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                                            </svg>
                                            Cirurgia Agendada
                                        </div>
                                        <div class="text-xs leading-relaxed space-y-1">
                                            @php
                                                $cirurgias = $patient['cirurgias_info'] ?? '';
                                                 
                                                if (!empty($patient['procedimentos_cirurgicos']) && is_array($patient['procedimentos_cirurgicos'])) {
                                                    foreach (array_slice($patient['procedimentos_cirurgicos'], 0, 2) as $c) {
                                                        $data = $c['data_agenda'] ?? '';
                                                        $hora = $c['hora_agenda'] ?? '';
                                                        $proc = $c['procedimento'] ?? $c['carater_cirurgia'] ?? 'Procedimento';
                                                        $local = $c['local'] ?? '';
                                                         
                                                        echo '<div class="border-l-2 border-purple-300 pl-2">';
                                                        echo '<div class="font-medium text-purple-900 truncate">' . htmlspecialchars($data) . ' às ' . htmlspecialchars($hora) . '</div>';
                                                        if ($local) {
                                                            echo '<div class="text-[10px] text-purple-700 truncate">' . htmlspecialchars($local) . '</div>';
                                                        }
                                                        echo '<div class="text-[11px] text-purple-800 truncate">' . htmlspecialchars($proc) . '</div>';
                                                        echo '</div>';
                                                    }
                                                     
                                                    if (count($patient['procedimentos_cirurgicos']) > 2) {
                                                        echo '<div class="text-purple-600 text-[10px] mt-1">... e mais ' . (count($patient['procedimentos_cirurgicos']) - 2) . ' cirurgia(s)</div>';
                                                    }
                                                } else if (!empty($cirurgias)) {
                                                    // Tenta processar texto simples
                                                    $cirurgias = strip_tags($cirurgias);
                                                     
                                                    // Procura por padrão de data/hora
                                                    if (preg_match('/(\d{2}\/\d{2}\/\d{4})\s+às\s+(\d{2}:\d{2})/', $cirurgias, $matches)) {
                                                        echo '<div class="border-l-2 border-purple-300 pl-2">';
                                                        echo '<div class="font-medium text-purple-900 truncate">' . $matches[1] . ' às ' . $matches[2] . '</div>';
                                                         
                                                        // Remove data/hora e mostra o resto
                                                        $resto = str_replace($matches[0], '', $cirurgias);
                                                        $resto = trim($resto, ' -');
                                                         
                                                        // Separa local e procedimento se houver
                                                        $partes = explode('/', $resto);
                                                        if (count($partes) > 1) {
                                                            echo '<div class="text-[10px] text-purple-700 truncate">' . htmlspecialchars(trim($partes[0])) . '</div>';
                                                            echo '<div class="text-[11px] text-purple-800 truncate">' . htmlspecialchars(trim($partes[1])) . '</div>';
                                                        } else {
                                                            echo '<div class="text-[11px] text-purple-800 truncate">' . htmlspecialchars($resto) . '</div>';
                                                        }
                                                        echo '</div>';
                                                    } else {
                                                        echo '<div class="text-purple-700 truncate">' . htmlspecialchars(Str::limit($cirurgias, 150)) . '</div>';
                                                    }
                                                } else {
                                                    echo '<span class="text-purple-600">Verificar detalhes no modal</span>';
                                                }
                                            @endphp
                                        </div>
                                        {{-- Seta do tooltip --}}
                                        <div class="tooltip-arrow absolute bottom-full left-1/2 transform -translate-x-1/2 w-0 h-0 border-l-4 border-r-4 border-b-4 border-transparent border-b-white"></div>
                                    </div>
                                </div>
                            @endif
                        </div>

                        {{-- MEWS com cor de fundo conforme classificação --}}
                        <div class="flex items-center gap-1.5 flex-shrink-0 min-w-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold shadow-sm whitespace-nowrap relative
                                {{ $patient['mews_styling']['bg'] ?? 'bg-white/90' }} 
                                {{ $patient['mews_styling']['text'] ?? 'text-gray-700' }}
                                {{ $patient['mews_styling']['border'] ?? 'border-gray-300' }}
                                border
                                {{ ($patient['mews_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                <strong>MEWS:</strong>
                                <span class="ml-1">{{ $patient['mews_score'] ?? '-' }}</span>
                                @if($patient['mews_shift'] ?? null)
                                    <span class="ml-0.5 text-[10px] font-normal">({{ $patient['mews_shift'] }})</span>
                                @endif
                                {{-- Bolinha piscante se MEWS aumentou --}}
                                @if(($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                @endif
                            </span>
                        </div>
                    </div>

                    {{-- Linha 2: Nome + Sexo + Idade --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm min-w-0">
                        <div class="flex items-center gap-2 min-w-0">
                            @if(($patient['sexo'] ?? '') === 'F')
                                <x-iconoir-female class="text-pink-600 h-4 w-4 flex-shrink-0" />
                            @elseif(($patient['sexo'] ?? '') === 'M')
                                <x-iconoir-male class="text-blue-600 h-4 w-4 flex-shrink-0" />
                            @endif
                            <p class="text-gray-900 text-sm font-bold truncate flex-1 min-w-0">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
                            <span class="text-gray-700 text-xs font-semibold flex-shrink-0">{{ $patient['age'] ?? '?' }}a</span>
                        </div>
                    </div>

                    {{-- Linha 3: Dados administrativos compactos --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm min-w-0">
                        <div class="grid grid-cols-3 gap-x-2 gap-y-0.5 text-[10px] min-w-0">
                            {{-- Linha 1: Atendimento, Prontuário, Dias Internados --}}
                            <div class="truncate text-center min-w-0"><span class="text-gray-600 font-medium">At:</span> <span class="text-gray-900 font-semibold">{{ $patient['nr_atendimento'] ?? 'N/A' }}</span></div>
                            <div class="truncate text-center min-w-0"><span class="text-gray-600 font-medium">Pr:</span> <span class="text-gray-900 font-semibold">{{ $patient['nr_prontuario'] ?? 'N/A' }}</span></div>
                            <div class="truncate text-center min-w-0">
                                <span class="text-gray-600 font-medium">Int:</span>
                                @if($patient['is_new_patient'] ?? false)
                                    <span class="text-green-700 font-bold">Hoje</span>
                                @elseif(isset($patient['internment_days']) && $patient['internment_days'] !== null)
                                    <span class="text-gray-900 font-semibold">{{ ceil($patient['internment_days']) }}d</span>
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                            </div>
                            
                            {{-- Linha 2: Convênio (primeira palavra) e Médico expandido --}}
                            <div class="text-center whitespace-nowrap overflow-hidden min-w-0">
                                <span class="text-gray-600 font-medium">Cv:</span> 
                                <span class="text-gray-900 font-medium truncate">{{ explode(' ', $patient['convenio'] ?? 'N/A')[0] }}</span>
                            </div>
                            @if(!empty($patient['medico_responsavel'] ?? null))
                                <div class="col-span-2 text-center whitespace-nowrap overflow-hidden min-w-0">
                                    <span class="text-gray-600 font-medium">Dr:</span> 
                                    <span class="text-gray-900 font-medium truncate max-w-full">{{ $patient['medico_responsavel'] }}</span>
                                </div>
                            @else
                                <div class="col-span-2 text-center"><span class="text-gray-400">-</span></div>
                            @endif
                        </div>
                    </div>

                    {{-- Linha 4: Escalas assistenciais compactas COM FORMATO PADRONIZADO --}}
                    <div class="bg-white/70 rounded-lg px-1.5 py-0.5 shadow-sm min-w-0">
                        <div class="flex flex-wrap gap-0.5 justify-center items-center min-h-[18px] min-w-0">
                            @if($patient['has_patient'] ?? false)
                                {{-- BRADEN --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] 
                                    {{ $patient['braden_styling']['bg'] ?? 'bg-gray-50' }} 
                                    {{ $patient['braden_styling']['text'] ?? 'text-gray-800' }}
                                    border {{ $patient['braden_styling']['border'] ?? 'border-gray-300' }}
                                    whitespace-nowrap relative min-w-0">
                                    <strong>Braden:</strong>
                                    <span class="ml-1">{{ $patient['braden_score'] ?? '-' }}</span>
                                    @if($patient['braden_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['braden_shift'] }})</span>
                                    @endif
                                    @if(($patient['braden_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                
                                {{-- MORSE --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] 
                                    {{ $patient['morse_styling']['bg'] ?? 'bg-gray-50' }} 
                                    {{ $patient['morse_styling']['text'] ?? 'text-gray-800' }}
                                    border {{ $patient['morse_styling']['border'] ?? 'border-gray-300' }}
                                    whitespace-nowrap relative min-w-0">
                                    <strong>Morse:</strong>
                                    <span class="ml-1">{{ $patient['morse_score'] ?? '-' }}</span>
                                    @if($patient['morse_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['morse_shift'] }})</span>
                                    @endif
                                    @if(($patient['morse_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] 
                                    {{ $patient['dor_styling']['bg'] ?? 'bg-gray-50' }} 
                                    {{ $patient['dor_styling']['text'] ?? 'text-gray-800' }}
                                    border {{ $patient['dor_styling']['border'] ?? 'border-gray-300' }}
                                    whitespace-nowrap relative min-w-0">
                                    <strong>Dor:</strong>
                                    <span class="ml-1">{{ $patient['dor_score'] ?? '-' }}</span>
                                    @if($patient['dor_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['dor_shift'] }})</span>
                                    @endif
                                    @if(($patient['dor_increased'] ?? false) && !($patient['is_new_patient'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                
                                {{-- TEV --}}
                                <span class="inline-flex items-center px-1 py-0.5 rounded text-[9px] 
                                    {{ $patient['tev_styling']['bg'] ?? 'bg-gray-50' }} 
                                    {{ $patient['tev_styling']['text'] ?? 'text-gray-800' }}
                                    border {{ $patient['tev_styling']['border'] ?? 'border-gray-300' }}
                                    whitespace-nowrap relative min-w-0">
                                    <strong>TEV:</strong>
                                    <span class="ml-1">{{ $patient['tev_score'] ?? '-' }}</span>
                                    @if($patient['tev_shift'] ?? null)
                                        <span class="ml-0.5 text-[8px] font-normal">({{ $patient['tev_shift'] }})</span>
                                    @endif
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
                        <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm min-w-0">
                            <div class="carousel-exames text-[10px] text-gray-800 text-center truncate">
                                <span class="font-bold">Exames Prioritários:</span>
                                <span class="carousel-content">{{ $patient['prioridade_exames'] }}</span>
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Pending Events List --}}
                @if(!empty($patient['pending_events_filtered']))
                    <div class="flex-1 min-h-0 px-2 sm:px-2.5 pb-1.5 min-w-0">
                        <div class="bg-white/20 rounded-lg p-1.5 h-full overflow-hidden flex flex-col min-h-0">
                            <div class="flex items-center justify-between mb-1 flex-shrink-0 min-w-0">
                                <h4 class="text-xs font-semibold text-gray-800">Pendências:</h4>
                                <span class="text-[10px] text-gray-600">próx. horas</span>
                            </div>
                            
                            <div class="flex-1 overflow-y-auto custom-scrollbar max-h-36 min-w-0">
                                <ul class="text-[11px] text-gray-800 space-y-0.5 break-words">
                                    @foreach($patient['pending_events_filtered'] as $pendencia)
                                        <li class="flex items-start gap-1.5 py-0.5 min-w-0">
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
                                                </svg>
                                            @elseif(str_contains($pendencia, '[Rec]'))
                                                <svg width="24" height="24" class="w-4 h-4 flex-shrink-0" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                    <path d="M11.9999 6.95459C12.5522 6.95459 12.9999 7.40231 12.9999 7.95459V11.0004H16.0456C16.5979 11.0004 17.0456 11.4481 17.0456 12.0004C17.0456 12.5526 16.5979 13.0004 16.0456 13.0004H12.9999V16.0459C12.9999 16.5982 12.5522 17.0459 11.9999 17.0459C11.4476 17.0459 10.9999 16.5982 10.9999 16.0459V13.0004H7.95435C7.40206 13.0004 6.95435 12.5526 6.95435 12.0004C6.95435 11.4481 7.40206 11.0004 7.95435 11.0004H10.9999V7.95459C10.9999 7.40231 11.4476 6.95459 11.9999 6.95459Z" fill="currentColor"/>
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.47715 2 2 6.47715 2 12C2 17.5228 6.47715 22 12 22C17.5228 22 22 17.5228 22 12C22 6.47715 17.5228 2 12 2ZM4 12C4 7.58172 7.58172 4 12 4C16.4183 4 20 7.58172 20 12C20 16.4183 16.4183 20 12 20C7.58172 20 4 16.4183 4 12Z" fill="currentColor"/>
                                                </svg>
                                            @else
                                                <span class="inline-block w-2 h-2 rounded-full bg-gray-400 mt-1.5 flex-shrink-0"></span>
                                            @endif
                                            <span class="text-xs leading-tight break-words flex-1 min-w-0">{{ $pendencia }}</span>
                                        </li>
                                    @endforeach
                                </ul>
                            </div>
                        </div>
                    </div>
                @else
                    {{-- No Pending Events --}}
                    <div class="flex-1 px-2 sm:px-3 pb-1 flex items-center justify-center min-w-0">
                        <div class="text-center py-2">
                            <p class="text-xs text-gray-500 font-medium">Sem pendências</p>
                            <p class="text-xs text-gray-400">próximas horas</p>
                        </div>
                    </div>
                @endif

                {{-- Botão Modal na parte inferior --}}
                <div class="p-1.5 flex-shrink-0 border-t border-white/10 min-w-0 modal-bottom-btn">
                    <button 
                        type="button"
                        class="w-full bg-white/20 hover:bg-white/30 active:bg-white/40 disabled:bg-white/10 disabled:cursor-not-allowed text-gray-700 disabled:text-gray-500 py-2 px-3 rounded-md flex items-center justify-center gap-2 shadow-sm transition-all duration-150 hover:shadow-md focus:outline-none focus:ring-2 focus:ring-blue-400 focus:ring-opacity-50 text-xs font-medium backdrop-blur-sm"
                        x-data="{ isLoading: false }"
                        @click.prevent="isLoading = true; $dispatch('openModal', { attendanceNumber: {{ $patient['nr_atendimento'] ?? 0 }}, hospital: '{{ $currentHospitalName ?? '' }}' }); setTimeout(() => isLoading = false, 2000)"
                        :disabled="isLoading"
                    >
                        {{-- Loading state com Alpine --}}
                        <template x-if="isLoading">
                            <div class="flex items-center gap-2">
                                <div class="animate-spin rounded-full h-4 w-4 border-2 border-gray-600 border-t-transparent"></div>
                                <span>Carregando...</span>
                            </div>
                        </template>
                        
                        {{-- Normal state com Alpine --}}
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

<style>
/* Custom scrollbar otimizado */
.custom-scrollbar::-webkit-scrollbar {
    width: 6px;
}

.custom-scrollbar::-webkit-scrollbar-track {
    background: rgba(255,255,255,0.06);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb {
    background: rgba(0,0,0,0.25);
    border-radius: 3px;
}

.custom-scrollbar::-webkit-scrollbar-thumb:hover {
    background: rgba(0,0,0,0.45);
}

.custom-scrollbar {
    scrollbar-width: thin;
    scrollbar-color: rgba(0,0,0,0.25) rgba(255,255,255,0.06);
}

/* Melhorar touch no mobile */
.patient-card {
    -webkit-tap-highlight-color: transparent;
    touch-action: manipulation;
    overflow-wrap: anywhere;
    word-break: break-word;
}

/* Garante limites do card e que filhos flex possam ter scroll */
.card-inner {
    display: flex;
    flex-direction: column;
    height: 100%;
    max-height: 28rem; /* corresponde a h-[28rem] */
    overflow: hidden;
}

/* Faz com que elementos flex não forcem o tamanho do container */
.patient-card .flex, .patient-card .grid {
    min-width: 0;
}

/* Garante que imagens não causem overflow */
.patient-card img {
    max-width: 100%;
    height: auto;
}

/* Tooltips responsivos e uniformes */
.tooltip-container {
    visibility: hidden;
    opacity: 0;
    transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out, transform 0.2s ease-in-out;
    z-index: 50 !important;
    pointer-events: none;
    left: 50% !important;
    transform: translateX(-50%) translateY(2px);
    max-width: calc(100vw - 32px);
    overflow-wrap: anywhere;
}

/* Hover para desktop */
.group:hover .tooltip-container {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

/* Click/touch para mobile - usando focus-within para acessibilidade */
.group:focus-within .tooltip-container {
    opacity: 1;
    visibility: visible;
    transform: translateX(-50%) translateY(0);
}

/* Suporte para touch devices */
@media (hover: none) and (pointer: coarse) {
    .group:active .tooltip-container {
        opacity: 1;
        visibility: visible;
        transform: translateX(-50%) translateY(0);
    }
}

/* Tooltips responsivos em mobile */
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

/* Garante que botões de alerta tenham tamanho consistente */
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

/* Garante que os SVGs dentro dos ícones tenham tamanho consistente */
.alert-icon svg {
    width: 20px !important;
    height: 20px !important;
    flex-shrink: 0;
}

/* Exceção para imagens dentro dos ícones */
.alert-icon img {
    width: 20px !important;
    height: 20px !important;
    flex-shrink: 0;
}

/* Melhorias para acessibilidade */
.alert-icon:focus {
    outline: 2px solid rgba(59, 130, 246, 0.5);
    outline-offset: 2px;
}

/* Estado ativo para JavaScript (será adicionado via JS para touch) */
.group.tooltip-active .tooltip-container {
    opacity: 1;
    visibility: visible;
}

/* Ajustes para telas muito pequenas */
@media (max-width: 480px) {
    .tooltip-container {
        font-size: 11px !important;
        padding: 8px 12px !important;
        max-width: calc(100vw - 16px) !important;
    }
}

/* Garantir que listas com scroll não excedam o espaço do card */
.patient-card .max-h-36 {
    max-height: 9rem; /* ajuste seguro para evitar overflow do card */
}

/* Garantir altura mínima e máxima de 400px nos cards e seus filhos para desktop */
@media (min-width: 1024px) {
    .patient-card,
    .patient-card .card-inner,
    .patient-card .h-full {
        min-height: 400px;
        max-height: 400px;
        height: 400px;
    }
    
    .card-inner {
        position: relative;
    }
    .modal-bottom-btn {
        position: absolute;
        left: 0;
        right: 0;
        bottom: 0;
        z-index: 10;
        /* Garante que o botão não fique sobreposto por overflow */
        background: inherit;
    }
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Adiciona suporte touch aos tooltips
    const alertIcons = document.querySelectorAll('.alert-icon');
    
    alertIcons.forEach(icon => {
        const group = icon.closest('.group');
        let tooltipTimeout;
        
        // Touch/click handler
        icon.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            // Remove active state from other tooltips
            document.querySelectorAll('.group.tooltip-active').forEach(g => {
                if (g !== group) g.classList.remove('tooltip-active');
            });
            
            // Toggle current tooltip
            group.classList.toggle('tooltip-active');
            
            // Auto-hide after 5 seconds on mobile
            if (window.innerWidth <= 640) {
                clearTimeout(tooltipTimeout);
                if (group.classList.contains('tooltip-active')) {
                    tooltipTimeout = setTimeout(() => {
                        group.classList.remove('tooltip-active');
                    }, 5000);
                }
            }
        });
        
        // Hide tooltip when clicking elsewhere
        document.addEventListener('click', function(e) {
            if (!group.contains(e.target)) {
                group.classList.remove('tooltip-active');
            }
        });
        
        // Hide tooltip on escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                group.classList.remove('tooltip-active');
            }
        });
    });
});
</script>
