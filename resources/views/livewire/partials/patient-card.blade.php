{{-- resources/views/livewire/partials/patient-card.blade.php - VERSÃO OTIMIZADA --}}
<div class="relative patient-card">
    <div class="rounded-xl shadow-lg h-80 sm:h-96 overflow-hidden">
        @if(!($patient['has_patient'] ?? false))
            {{-- Empty Bed Card --}}
            <div class="h-full bg-gradient-to-br from-gray-200 to-gray-300 p-3 sm:p-4 flex flex-col cursor-pointer transform transition-all duration-200 hover:shadow-xl active:scale-98"
                 wire:click="$dispatch('openModal', { attendanceNumber: {{ $patient['nr_atendimento'] ?? 0 }}, hospital: '{{ $currentHospitalName }}' })"
                 role="button"
                 tabindex="0">
                <div class="flex justify-between items-center mb-3">
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
            <div class="h-full bg-gradient-to-br {{ $patient['gradient_class'] }} {{ $patient['border_class'] }} flex flex-col cursor-pointer transform transition-all duration-200 hover:shadow-xl active:scale-98"
                 wire:click="$dispatch('openModal', { attendanceNumber: {{ $patient['nr_atendimento'] ?? 0 }}, hospital: '{{ $currentHospitalName }}' })"
                 role="button"
                 tabindex="0">
                
                {{-- Header Compacto --}}
                <div class="p-2 sm:p-2.5 flex-shrink-0 space-y-1.5">
                    
                    {{-- Linha 1: Leito + Alertas + MEWS --}}
                    <div class="flex justify-between items-center gap-2">
                        <span class="bg-white/80 text-gray-800 text-xs font-bold px-2 py-0.5 rounded-full shadow-sm flex-shrink-0">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>

                        {{-- Alertas centralizados --}}
                        <div class="flex items-center justify-center gap-1.5 flex-1">
                            @if($patient['has_allergy'] ?? false)
                                <button 
                                    type="button"
                                    popovertarget="allergy-popover-{{ $patient['nr_atendimento'] }}"
                                    class="bg-red-500 text-white rounded-full p-1 shadow-lg animate-pulse cursor-pointer hover:scale-110 transition-transform relative"
                                    aria-label="Ver alergias"
                                    @click.stop
                                >
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                    </svg>
                                </button>
                                <div id="allergy-popover-{{ $patient['nr_atendimento'] }}" popover class="info-popover">
                                    <div class="popover-header">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                        <strong>Alergias Documentadas</strong>
                                    </div>
                                    <div class="popover-content">
                                        {!! $patient['alergias_detalhadas'] ?? 'Nenhuma alergia registrada' !!}
                                    </div>
                                </div>
                            @endif

                            @if($patient['has_isolation'] ?? false)
                                <button 
                                    type="button"
                                    popovertarget="isolation-popover-{{ $patient['nr_atendimento'] }}"
                                    class="hover:scale-110 transition-transform cursor-pointer relative"
                                    aria-label="Ver isolamento"
                                    @click.stop
                                >
                                    <img src="{{ asset('images/icons/patient-isolated.svg') }}" class="h-6 w-6 animate-pulse" alt="Isolamento" />
                                </button>
                                <div id="isolation-popover-{{ $patient['nr_atendimento'] }}" popover class="info-popover">
                                    <div class="popover-header">
                                        <img src="{{ asset('images/icons/patient-isolated.svg') }}" class="h-5 w-5" alt="Isolamento" />
                                        <strong>Precauções de Isolamento</strong>
                                    </div>
                                    <div class="popover-content">
                                        {!! $patient['motivos_isolamento'] ?? 'Nenhum motivo de isolamento' !!}
                                    </div>
                                </div>
                            @endif

                            @if($patient['has_surgery'] ?? false)
                                <button 
                                    type="button"
                                    popovertarget="surgery-popover-{{ $patient['nr_atendimento'] }}"
                                    class="hover:scale-110 transition-transform cursor-pointer relative"
                                    aria-label="Ver cirurgia"
                                    @click.stop
                                >
                                    <svg class="w-6 h-6 text-purple-600 animate-pulse" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                                    </svg>
                                </button>
                                <div id="surgery-popover-{{ $patient['nr_atendimento'] }}" popover class="info-popover">
                                    <div class="popover-header">
                                        <svg class="w-5 h-5 text-purple-600" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M40 8H8V40H40V8ZM8 6C6.89543 6 6 6.89543 6 8V40C6 41.1046 6.89543 42 8 42H40C41.1046 42 42 41.1046 42 40V8C42 6.89543 41.1046 6 40 6H8Z" fill="currentColor"/>
                                            <path fill-rule="evenodd" clip-rule="evenodd" d="M16.8284 28H21.5516C22.5979 28 23.6026 27.59 24.3501 26.858L28 23.2842V22.8284C28 21.7676 28.4214 20.7501 29.1716 20L30.8607 18.3109L28.2548 16.5736L16.8284 28ZM12 30L28 14L34 18L30.5858 21.4142C30.2107 21.7893 30 22.298 30 22.8284V23.2842C30 23.8219 29.7835 24.337 29.3993 24.7132L25.7494 28.2871C24.628 29.3851 23.1211 30 21.5516 30H12Z" fill="currentColor"/>
                                        </svg>
                                        <strong>Cirurgia Programada</strong>
                                    </div>
                                    <div class="popover-content">
                                        Paciente possui cirurgia futura agendada
                                    </div>
                                </div>
                            @endif
                        </div>

                        <div class="flex items-center gap-1.5 flex-shrink-0">
                            {{-- MEWS com indicadores visuais --}}
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-bold text-gray-700 bg-white/90 shadow-sm whitespace-nowrap relative border border-gray-300
                                {{ ($patient['mews_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                {{ ($patient['mews_increased'] ?? false) && !($patient['is_new_patient'] ?? false) ? 'text-red-600' : '' }}">
                                MEWS:
                                @if(isset($patient['mews_score']) && $patient['mews_score'] !== null)
                                    {{ $patient['mews_score'] }}
                                    @if(isset($patient['mews_shift']) && $patient['mews_shift'])
                                        <span class="ml-0.5 text-[10px] opacity-70">({{ substr(getShiftLabel($patient['mews_shift']), 0, 1) }})</span>
                                    @endif
                                @else
                                    <span class="text-gray-400">-</span>
                                @endif
                                {{-- Bolinha piscante se MEWS aumentou --}}
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
                            {{-- Linha 1: Atendimento, Prontuário, Dias Internados --}}
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
                            
                            {{-- Linha 2: Convênio (primeira palavra) e Médico expandido --}}
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

                    {{-- Linha 4: Escalas assistenciais compactas --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm">
                        <div class="flex flex-wrap gap-1 justify-center items-center min-h-[20px]">
                            @if($patient['has_patient'] ?? false)
                                {{-- MEWS --}}
                                @if(isset($patient['mews_score']) && $patient['mews_score'] !== null)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-300 whitespace-nowrap relative font-semibold
                                        {{ ($patient['mews_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['mews_increased'] ?? false) ? 'text-red-600' : '' }}">
                                        MW: {{ $patient['mews_score'] }}
                                        {{-- Bolinha piscante se aumentou --}}
                                        @if($patient['mews_increased'] ?? false)
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap">
                                        MW: N/A
                                    </span>
                                @endif
                                
                                {{-- BRADEN --}}
                                @if(isset($patient['braden_score']) && $patient['braden_score'] !== null)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-300 whitespace-nowrap relative font-semibold
                                        {{ ($patient['braden_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['braden_increased'] ?? false) ? 'text-red-600' : '' }}">
                                        Bd: {{ $patient['braden_score'] }}
                                        @if($patient['braden_increased'] ?? false)
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap">
                                        Bd: N/A
                                    </span>
                                @endif
                                
                                {{-- MORSE --}}
                                @if(isset($patient['morse_score']) && $patient['morse_score'] !== null)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-300 whitespace-nowrap relative font-semibold
                                        {{ ($patient['morse_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['morse_increased'] ?? false) ? 'text-red-600' : '' }}">
                                        Ms: {{ $patient['morse_score'] }}
                                        @if($patient['morse_increased'] ?? false)
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap">
                                        Ms: N/A
                                    </span>
                                @endif
                                
                                {{-- DOR --}}
                                @if(isset($patient['dor_score']) && $patient['dor_score'] !== null)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-300 whitespace-nowrap relative font-semibold
                                        {{ ($patient['dor_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['dor_increased'] ?? false) ? 'text-red-600' : '' }}">
                                        Dr: {{ $patient['dor_score'] }}
                                        @if($patient['dor_increased'] ?? false)
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap">
                                        Dr: N/A
                                    </span>
                                @endif
                                
                                {{-- TEV --}}
                                @if(isset($patient['tev_score']) && $patient['tev_score'] !== null)
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-300 whitespace-nowrap relative font-semibold
                                        {{ ($patient['tev_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                        {{ ($patient['tev_increased'] ?? false) ? 'text-red-600' : '' }}">
                                        Tv: {{ $patient['tev_score'] }}
                                        @if($patient['tev_increased'] ?? false)
                                            <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                        @endif
                                    </span>
                                @else
                                    <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap">
                                        Tv: N/A
                                    </span>
                                @endif
                                
                                {{-- PEWS (só pediátricos) --}}
                                @if(($patient['is_pediatric'] ?? false))
                                    @if(isset($patient['pews_score']) && $patient['pews_score'] !== null)
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] bg-gray-100 text-gray-700 border border-gray-300 whitespace-nowrap relative font-semibold
                                            {{ ($patient['pews_needs_assessment'] ?? true) ? 'border-b-2 border-b-red-500' : '' }}
                                            {{ ($patient['pews_increased'] ?? false) ? 'text-red-600' : '' }}">
                                            Pw: {{ $patient['pews_score'] }}
                                            @if($patient['pews_increased'] ?? false)
                                                <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                            @endif
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-semibold bg-gray-50 text-gray-400 border border-gray-300 border-b-2 border-b-red-500 whitespace-nowrap">
                                            Pw: N/A
                                        </span>
                                    @endif
                                @endif
                            @else
                                <span class="text-[10px] text-gray-400 italic">Escalas aguardando avaliação</span>
                            @endif
                        </div>
                    </div>

                    {{-- Linha 5: Exames Prioritários --}}
                    @if(!empty($patient['exames_prioritarios'] ?? null))
                        <div class="bg-white/70 rounded-lg px-2 py-1 shadow-sm">
                            <div class="text-[10px] text-gray-800 text-center">
                                <span class="font-bold">Exames Prioritários:</span> {{ $patient['exames_prioritarios'] }}
                            </div>
                        </div>
                    @endif
                </div>

                {{-- Pending Events List - SEM MEDICAÇÕES --}}
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
                                                        <path d="M8.44912 16.4497L9.27658 13.998L10.746 15.8984L10.3441 17.0893C10.0535 17.9502 9.21903 18.5071 8.31245 18.445L5.43171 18.2477C4.88071 18.21 4.46464 17.7327 4.50238 17.1817C4.54012 16.6307 5.01738 16.2146 5.56837 16.2524L8.44912 16.4497Z" fill="currentColor"/>
                                                    </svg>
                                                @elseif(str_contains($pendencia, '[Hemot]'))
                                                    <svg class="w-4 h-4 flex-shrink-0 text-red-500 mt-0.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M16 2.8667H14.3485C13.7207 2.33266 12.9063 2 12 2C11.0937 2 10.2793 2.33266 9.6515 2.8667H8C6.34315 2.8667 5 4.20985 5 5.8667V16C5 17.6569 6.34315 19 8 19C8.00002 19.5523 8.44773 20 9 20H11V22H13V20H15C15.5523 20 16 19.5523 16 19C17.6569 19 19 17.6569 19 16V5.8667C19 4.20984 17.6569 2.8667 16 2.8667ZM16 4.8667H13.4437C13.1556 4.34859 12.6169 4 12 4C11.3831 4 10.8445 4.34859 10.5563 4.8667H8C7.44772 4.8667 7 5.31441 7 5.8667V14.0246C8.04361 13.947 9.82952 13.5504 12 12C14.1705 10.4497 15.9564 11.2068 17 12.0056V5.8667C17 5.31441 16.5523 4.8667 16 4.8667Z" fill="currentColor"/>
                                                    </svg>
                                                @elseif(str_contains($pendencia, '[Quimio]'))
                                                    <svg class="w-6 h-6 flex-shrink-0 text-black" viewBox="0 0 48 48" fill="none" xmlns="http://www.w3.org/2000/svg">
                                                        <path fill-rule="evenodd" clip-rule="evenodd" d="M33.7698 6.7752H35.1839C36.7855 6.7752 38.0839 8.06595 38.0839 9.65817V18.7246C38.0839 20.3168 36.7855 21.6076 35.1839 21.6076H34.364C34.2714 22.0613 33.8678 22.4028 33.3841 22.4028H32.5839L32.584 26.5779C32.584 27.676 31.6885 28.5661 30.584 28.5661H24.4142L22.0033 30.9628L22.0066 30.9661L20.9605 32.0061L29.8488 32.0064L32.533 29.3379H36.5261C37.3351 29.3379 38.0637 29.6803 38.5732 30.2272H39.2106C40.7512 30.2272 42.0001 31.4688 42.0001 33.0003V39.2267C42.0001 40.7582 40.7512 41.9998 39.2106 41.9998H37.4211L37.3802 41.9996H32.9473C31.6055 41.9996 30.4312 41.2865 29.7871 40.2208H16.3814C14.6924 40.2208 13.0726 39.5537 11.8783 38.3664L9.21179 35.7156L8.1705 36.8001C7.78916 37.1972 7.15617 37.2118 6.75667 36.8327C6.35717 36.4536 6.34245 35.8244 6.72379 35.4272L7.79719 34.3093L5.99988 32.5226V19.1928C5.99988 17.8168 7.7562 17.2257 8.59698 18.3188L15.0705 26.7344L16.1185 25.6429C16.4999 25.2458 17.1329 25.2311 17.5324 25.6102C17.9319 25.9893 17.9466 26.6186 17.5652 27.0158L16.3003 28.3332L18.6951 31.4464L19.2962 30.8488L19.2928 30.8456L23 27.1602C23.375 26.7873 23.8837 26.5779 24.4142 26.5779H30.584L30.5839 22.4028H29.7841C29.3003 22.4028 28.8967 22.0613 28.8041 21.6076H27.9839C26.3822 21.6076 25.0839 20.3168 25.0839 18.7246V9.65817C25.0839 8.06594 26.3822 6.7752 27.9839 6.7752H29.398C29.989 6.29629 30.7449 5.99976 31.5839 5.99976C32.4229 5.99976 33.1788 6.29629 33.7698 6.7752ZM32.8832 8.76346H35.1839C35.6809 8.76346 36.0839 9.16403 36.0839 9.65817V14.6002C35.9941 14.5629 35.9008 14.5267 35.8041 14.4921C34.6386 14.0749 33.0039 13.9028 31.1472 14.7994C29.6817 15.5071 28.4406 15.7832 27.5837 15.8854C27.3988 15.9075 27.2317 15.9214 27.0839 15.9299V9.65817C27.0839 9.16403 27.4868 8.76346 27.9839 8.76346H30.2846C30.5439 8.29989 31.0287 7.98801 31.5839 7.98801C32.1391 7.98801 32.6239 8.29989 32.8832 8.76346ZM27.0839 17.9207V18.7246C27.0839 19.2187 27.4868 19.6193 27.9839 19.6193H35.1839C35.6809 19.6193 36.0839 19.2187 36.0839 18.7246V16.8586C36.0642 16.8446 36.0432 16.8301 36.021 16.8151C35.8146 16.6756 35.5097 16.4999 35.1267 16.3628C34.3797 16.0955 33.3143 15.9633 32.0211 16.5878C30.3366 17.4012 28.8777 17.7336 27.822 17.8595C27.5474 17.8922 27.3003 17.911 27.0839 17.9207ZM14.9024 29.789L17.2683 32.8647L16.601 33.5282L14.9999 35.0279H17.8283L18.8283 34.1258L19.0072 33.9479C19.1735 33.9786 19.3437 33.9944 19.5158 33.9944L30.6772 33.9946L33.3614 31.3262H36.5261C36.9622 31.3262 37.3156 31.6776 37.3156 32.111V34.6748H36.5261C36.0901 34.6748 35.7367 34.3234 35.7367 33.89V33.0005H33.7367V33.89C33.7367 35.4215 34.9856 36.6631 36.5261 36.6631H39.3156V32.2223C39.702 32.2734 40.0001 32.6022 40.0001 33.0003V39.2267C40.0001 39.6601 39.6466 40.0115 39.2106 40.0115H37.4211L37.3904 40.0113C35.9802 39.995 34.8422 38.8535 34.8422 37.4477V36.4012H32.8422V37.4477C32.8422 38.3985 33.1354 39.2812 33.6368 40.0113H32.9473C32.0171 40.0113 31.2631 39.2617 31.2631 38.337H30.263V38.2325H16.3814C15.2229 38.2325 14.1117 37.775 13.2925 36.9605L10.5931 34.277L14.9024 29.789ZM13.6726 28.1903L9.17852 32.8707L7.99988 31.699V20.8156L13.6726 28.1903Z" fill="currentColor"/>
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
                    {{-- No Pending Events --}}
                    <div class="flex-1 px-2 sm:px-3 pb-1 flex items-center justify-center">
                        <div class="text-center py-2">
                            <p class="text-xs text-gray-500 font-medium">Sem pendências</p>
                            <p class="text-xs text-gray-400">próximas horas</p>
                        </div>
                    </div>
                @endif

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

.active-scale-98 {
    transform: scale(0.98);
}

/* Popovers - Nativos e Modernos */
[popover] {
    display: none;
}

[popover]:popover-open {
    display: block;
}

.info-popover {
    position: fixed;
    margin: auto;
    padding: 0;
    border: none;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.25);
    max-width: 320px;
    z-index: 99999;
    animation: popoverFadeIn 0.2s ease;
    overflow: hidden;
    /* Previne propagação de eventos */
    pointer-events: auto;
}

/* Previne que cliques dentro do popover propaguem para o card */
.info-popover * {
    pointer-events: auto;
}

.popover-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    background: linear-gradient(135deg, #f8fafc 0%, #e2e8f0 100%);
    border-bottom: 1px solid #cbd5e1;
    /* Previne propagação */
    pointer-events: auto;
}

.popover-close {
    margin-left: auto;
    font-size: 24px;
    font-weight: bold;
    color: #64748b;
    background: none;
    border: none;
    cursor: pointer;
    line-height: 1;
    padding: 0;
    width: 24px;
    height: 24px;
    display: flex;
    align-items: center;
    justify-content: center;
    border-radius: 4px;
    transition: all 0.2s;
    /* Importante: não deixa o evento propagar */
    pointer-events: auto;
}

.popover-close:hover {
    background: rgba(0, 0, 0, 0.1);
    color: #1e293b;
}

.popover-content {
    padding: 16px;
    color: #1e293b;
    font-size: 14px;
    line-height: 1.6;
    pointer-events: auto;
}

@keyframes popoverFadeIn {
    from {
        opacity: 0;
        transform: scale(0.95) translateY(-10px);
    }
    to {
        opacity: 1;
        transform: scale(1) translateY(0);
    }
}

/* Mobile optimization */
@media (max-width: 640px) {
    .info-popover {
        max-width: calc(100vw - 32px);
        inset: auto 16px !important;
    }
}

</style>

