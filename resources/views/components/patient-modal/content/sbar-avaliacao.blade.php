@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<!-- SBAR Avaliação Modal Content -->
<div x-show="activeTab === 'tab-a'" class="p-1 sm:p-2 lg:p-3 h-full">
    {{-- Loading State --}}
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-4 sm:py-6 lg:py-8">
            <div class="w-6 h-6 sm:w-8 sm:h-8 border-t-2 border-r-2 border-blue-400 border-solid rounded-full animate-spin mb-2"></div>
            <p class="text-gray-600 text-xs sm:text-sm">Carregando detalhes do paciente...</p>
        </div>
    {{-- Empty Bed State --}}
    @elseif($currentPatient && !$currentPatient['has_patient'])
        <div class="flex flex-col items-center justify-center py-4 sm:py-6 text-gray-600">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-sm sm:text-base">Leito Vazio</p>
            <p class="text-gray-500 text-xs">Este leito não possui paciente internado no momento.</p>
        </div>
    {{-- Patient Details and Chat System --}}
    @elseif($patientDetails)
        <!-- Aviso de desenvolvimento -->
        <div class="mb-2 bg-amber-50 border-l-2 border-amber-300 p-2 rounded-r text-xs">
            <div class="flex items-start">
                <svg class="h-3 w-3 text-amber-500 mt-0.5 mr-2 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                    <path fill-rule="evenodd" d="M8.257 3.099c.765-1.36 2.722-1.36 3.486 0l5.58 9.92c.75 1.334-.213 2.98-1.742 2.98H4.42c-1.53 0-2.493-1.646-1.743-2.98l5.58-9.92zM11 13a1 1 0 11-2 0 1 1 0 012 0zm-1-8a1 1 0 00-1 1v3a1 1 0 002 0V6a1 1 0 00-1-1z" clip-rule="evenodd" />
                </svg>
                <p class="text-amber-800">
                    <strong>MÓDULO EM DESENVOLVIMENTO</strong> - Funcionalidade demonstrativa sem integração completa.
                </p>
            </div>
        </div>

        @php
            // Shift and color scheme logic
            $now = \Carbon\Carbon::now('America/Sao_Paulo');
            $currentHour = $now->hour;
            $isShiftClosed = false;
            if ($this->currentShift === 'dia' && ($currentHour < 7 || $currentHour >= 19)) {
                $isShiftClosed = true;
            } elseif ($this->currentShift === 'noite' && ($currentHour >= 7 && $currentHour < 19)) {
                $isShiftClosed = true;
            }
            // Color variables for UI
            if ($this->currentShift === 'dia') {
                $headerBg = 'from-sky-400 to-sky-500';
                $accentColor = 'sky-500';
                $lightAccent = 'sky-100';
                $darkAccent = 'sky-600';
            } else {
                $headerBg = 'from-indigo-500 to-indigo-600';
                $accentColor = 'indigo-500';
                $lightAccent = 'indigo-100';
                $darkAccent = 'indigo-600';
            }
        @endphp

        <!-- Container principal do chat -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 h-[462px] sm:h-[506px] lg:h-[583px] flex flex-col overflow-hidden">
            
            <!-- Cabeçalho compacto -->
            <div class="flex-shrink-0 bg-gradient-to-r {{ $headerBg }} text-white p-2 sm:p-3 rounded-t-lg">
                <div class="space-y-2">
                    <!-- Título e info do paciente -->
                    <div class="flex items-center justify-between">
                        <div class="flex items-center space-x-2 min-w-0 flex-1">
                            <span class="inline-flex items-center justify-center h-5 w-5 sm:h-6 sm:w-6 rounded-full bg-white/20 text-white text-xs font-bold flex-shrink-0">P</span>
                            <div class="min-w-0 flex-1">
                                <h3 class="text-xs sm:text-sm font-bold truncate">PASSAGEM DE PLANTÃO</h3>
                                <p class="text-xs text-white/80 truncate">
                                    At: {{ $currentPatient['nr_atendimento'] ?? 'N/A' }} | Lt: {{ $patientDetails->cd_unidade_basica ?? 'N/A' }}
                                </p>
                            </div>
                        </div>
                        <!-- Horário atual -->
                        <div class="text-right flex-shrink-0">
                            <div id="current-time-display" class="text-white text-xs font-medium">{{ now()->format('H:i') }}</div>
                            <div class="text-white/70 text-xs">{{ now()->format('d/m') }}</div>
                        </div>
                    </div>
                    
                    <!-- Info do turno e controles de histórico -->
                    <div class="flex flex-col space-y-2 border-t border-white/20 pt-2">
                        <div class="flex items-center justify-between text-xs">
                            <div class="flex items-center space-x-2 min-w-0 flex-1">
                                <div class="w-2 h-2 rounded-full bg-green-300 animate-pulse flex-shrink-0"></div>
                                <span class="text-white/80">
                                    {{ $this->currentShift === 'dia' ? 'Dia (07-19h)' : 'Noite (19-07h)' }} | 
                                    {{ Str::limit($this->currentUser['name'] ?? 'Não identificado', 20) }}
                                </span>
                            </div>
                        </div>
                        <!-- Navegação de histórico -->
                        <div class="flex items-center space-x-1 sm:space-x-2">
                            <span class="text-xs text-white/80 flex-shrink-0">Histórico:</span>
                            <!-- Dropdown de datas -->
                            <div class="relative" x-data="{ 
                                showCalendar: false,
                                availableDates: [],
                                selectedDate: @entangle('selectedHistoryDate'),
                                formatShortDate(date) {
                                    return new Date(date).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' });
                                },
                                isToday(date) {
                                    return date === new Date().toISOString().split('T')[0];
                                },
                                generateMockDates() {
                                    const dates = [];
                                    const today = new Date();
                                    for (let i = 0; i < 7; i++) {
                                        const date = new Date(today);
                                        date.setDate(date.getDate() - i);
                                        const dateStr = date.toISOString().split('T')[0];
                                        dates.push({
                                            date: dateStr,
                                            count: Math.floor(Math.random() * 10) + 1,
                                            hasData: Math.random() > 0.2
                                        });
                                    }
                                    return dates;
                                },
                                init() {
                                    this.availableDates = this.generateMockDates();
                                }
                            }">
                                <button 
                                    @click="showCalendar = !showCalendar"
                                    class="w-16 sm:w-20 px-1 py-1 text-xs border border-white/30 rounded bg-white/10 text-white hover:bg-white/20 transition-colors flex items-center justify-between"
                                >
                                    <span x-text="selectedDate ? formatShortDate(selectedDate) : 'Data'"></span>
                                    <svg class="w-2.5 h-2.5 transition-transform" :class="showCalendar && 'rotate-180'" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
                                    </svg>
                                </button>
                                <!-- Lista de datas -->
                                <div 
                                    x-show="showCalendar" 
                                    @click.outside="showCalendar = false"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    class="absolute top-full left-0 mt-1 bg-white rounded-md shadow-lg border border-gray-300 z-50 w-40"
                                    style="display: none;"
                                >
                                    <div class="px-2 py-1.5 border-b border-gray-200 bg-gray-100">
                                        <div class="text-xs font-medium text-gray-700 text-center">Últimos 7 dias</div>
                                    </div>
                                    <div class="py-1 max-h-32 overflow-y-auto">
                                        <template x-for="dateInfo in availableDates" :key="dateInfo.date">
                                            <button 
                                                @click="selectedDate = dateInfo.date; showCalendar = false; $wire.set('selectedHistoryDate', dateInfo.date)"
                                                class="w-full px-2 py-1.5 text-xs hover:bg-gray-100 text-left flex items-center justify-between"
                                                :class="{
                                                    'bg-blue-100 text-blue-800': selectedDate === dateInfo.date,
                                                    'text-gray-600': !dateInfo.hasData,
                                                    'text-gray-900': dateInfo.hasData
                                                }"
                                            >
                                                <div class="flex items-center space-x-1.5">
                                                    <span x-text="formatShortDate(dateInfo.date)"></span>
                                                    <template x-if="isToday(dateInfo.date)">
                                                        <span class="bg-green-600 text-white px-1 py-0.5 rounded text-xs font-medium">hoje</span>
                                                    </template>
                                                </div>
                                                <template x-if="dateInfo.hasData">
                                                    <span class="bg-gray-600 text-white px-1 py-0.5 rounded text-xs" x-text="dateInfo.count"></span>
                                                </template>
                                                <template x-if="!dateInfo.hasData">
                                                    <span class="text-gray-400 text-xs">-</span>
                                                </template>
                                            </button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                            <!-- Seleção de turno -->
                            <select 
                                wire:model.live="selectedHistoryShift"
                                class="w-12 sm:w-14 px-1 py-1 text-xs border border-white/30 rounded bg-white/10 text-white hover:bg-white/20 transition-colors"
                            >
                                <option value="dia" class="text-gray-800">Dia</option>
                                <option value="noite" class="text-gray-800">Noite</option>
                            </select>
                            <!-- Botão para retornar ao turno atual -->
                            @if($this->viewingHistory)
                                <button 
                                    wire:click="returnToCurrentShift"
                                    class="px-1.5 py-1 bg-white/20 hover:bg-white/30 text-white text-xs rounded border border-white/30 transition-colors flex items-center space-x-1"
                                >
                                    <svg class="w-2.5 h-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
                                    </svg>
                                    <span>Atual</span>
                                </button>
                            @endif
                        </div>
                    </div>
                </div>
            </div>

            <!-- Banner de ambiente profissional -->
            @if(!$this->viewingHistory && !$isShiftClosed)
                <div class="flex-shrink-0 px-2 py-1.5 border-b border-amber-200 bg-gradient-to-r from-amber-50 to-orange-50">
                    <div class="flex items-center space-x-2 text-xs">
                        <svg class="w-4 h-4 text-amber-600 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <div class="flex-1">
                            <span class="text-amber-800 font-medium">AMBIENTE PROFISSIONAL:</span>
                            <span class="text-amber-700 ml-1">Mantenha clareza e objetividade nas comunicações para garantir a segurança do paciente e continuidade do cuidado.</span>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Indicador de status do turno/histórico -->
            <div class="flex-shrink-0 px-2 py-1 border-b border-gray-200 bg-gray-50">
                @if($this->viewingHistory)
                    <div class="flex items-center justify-start">
                        <div class="inline-flex items-center space-x-1 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200 flex-shrink-0">
                            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium whitespace-nowrap">
                                {{ \Carbon\Carbon::parse($this->selectedHistoryDate)->format('d/m') }} - 
                                {{ $this->selectedHistoryShift === 'dia' ? 'Dia' : 'Noite' }}
                            </span>
                        </div>
                    </div>
                @else
                    <div class="flex items-center justify-start">
                        @if($isShiftClosed)
                            <div class="inline-flex items-center space-x-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full border border-gray-300 flex-shrink-0">
                                <div class="w-2 h-2 bg-gray-500 rounded-full flex-shrink-0"></div>
                                <span class="font-medium whitespace-nowrap">Turno Encerrado</span>
                            </div>
                        @else
                            <div class="inline-flex items-center space-x-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full border border-green-200 flex-shrink-0">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse flex-shrink-0"></div>
                                <span class="font-medium whitespace-nowrap">Turno Ativo</span>
                            </div>
                        @endif
                    </div>
                @endif
            </div>

            <!-- Área de mensagens -->
            <div class="flex-1 overflow-y-auto p-2 bg-gray-50 min-h-[120px] max-h-[220px] h-[120px] sm:min-h-[140px] sm:max-h-[260px] sm:h-[140px] lg:min-h-[160px] lg:max-h-[300px] lg:h-[160px]" id="messages-container">
                @if(count($this->shiftMessages) > 0)
                    {{-- Mensagens fixadas --}}
                    @php $pinnedMessages = collect($this->shiftMessages)->where('is_pinned', true) @endphp
                    @if($pinnedMessages->count() > 0)
                        <div class="mb-3">
                            <div class="flex items-center space-x-2 mb-2">
                                <svg class="w-3 h-3 text-{{ $accentColor }} flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                </svg>
                                <h4 class="text-xs font-semibold text-gray-700">Fixadas</h4>
                                <span class="text-xs text-gray-500 bg-gray-200 px-1.5 py-0.5 rounded-full">{{ $pinnedMessages->count() }}</span>
                            </div>
                            @foreach($pinnedMessages as $message)
                                <div class="bg-{{ $lightAccent }} border-l-2 border-{{ $accentColor }} rounded p-2 mb-2 shadow-sm">
                                    <div class="flex items-start justify-between">
                                        <div class="flex items-start space-x-2 flex-1 min-w-0">
                                            <div class="w-5 h-5 bg-{{ $accentColor }}/20 rounded-full flex items-center justify-center flex-shrink-0">
                                                <span class="text-xs font-medium text-{{ $darkAccent }}">{{ substr($message['role'], 0, 1) }}</span>
                                            </div>
                                            <div class="flex-1 min-w-0">
                                                <div class="flex items-center space-x-2 mb-1">
                                                    <p class="text-xs font-semibold text-gray-800 truncate">{{ $message['author'] }}</p>
                                                    <span class="text-xs text-gray-500">{{ $message['time'] }}</span>
                                                    <span class="inline-flex items-center text-xs text-{{ $darkAccent }} bg-{{ $accentColor }}/20 px-1.5 py-0.5 rounded-full">
                                                        <svg class="w-2.5 h-2.5 mr-1" fill="currentColor" viewBox="0 0 20 20">
                                                            <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                        </svg>
                                                        FIXO
                                                    </span>
                                                </div>
                                                <p class="text-xs text-gray-700 leading-relaxed">{{ $message['message'] }}</p>
                                            </div>
                                        </div>
                                        @if(!$this->viewingHistory && !$isShiftClosed)
                                            <button 
                                                wire:click="toggleMessagePin({{ $message['id'] }})"
                                                class="text-{{ $accentColor }} hover:text-{{ $darkAccent }} p-1 rounded hover:bg-{{ $lightAccent }} transition-colors ml-2" 
                                                title="Desfixar"
                                            >
                                                <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                                                    <path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/>
                                                </svg>
                                            </button>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    {{-- Mensagens regulares --}}
                    @php $regularMessages = collect($this->shiftMessages)->where('is_pinned', false) @endphp
                    @if($regularMessages->count() > 0)
                        @if($pinnedMessages->count() > 0)
                            <div class="flex items-center my-2">
                                <div class="flex-1 border-t border-gray-300"></div>
                                <span class="text-xs text-gray-500 bg-white px-2 py-0.5 rounded-full border mx-2">Demais</span>
                                <div class="flex-1 border-t border-gray-300"></div>
                            </div>
                        @endif
                        <div class="space-y-2">
                            @foreach($regularMessages as $message)
                                <div class="flex items-start space-x-2">
                                    <div class="w-5 h-5 bg-gray-200 rounded-full flex items-center justify-center flex-shrink-0">
                                        <span class="text-xs font-medium text-gray-600">{{ substr($message['role'], 0, 1) }}</span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="bg-white rounded p-2 shadow-sm border border-gray-200 hover:shadow-md transition-shadow">
                                            <div class="flex items-center justify-between mb-1">
                                                <div class="flex items-center space-x-2 min-w-0 flex-1">
                                                    <p class="text-xs font-medium text-gray-800 truncate">{{ $message['author'] }}</p>
                                                    <span class="text-xs text-gray-500">{{ $message['time'] }}</span>
                                                </div>
                                                @if(!$this->viewingHistory && !$isShiftClosed)
                                                    <div class="flex items-center space-x-1 flex-shrink-0">
                                                        <button 
                                                            wire:click="toggleMessagePin({{ $message['id'] }})"
                                                            class="text-gray-400 hover:text-{{ $accentColor }} p-1 rounded hover:bg-gray-100 transition-colors" 
                                                            title="Fixar"
                                                        >
                                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-700 leading-relaxed">{{ $message['message'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif
                @else
                    <!-- Estado vazio de mensagens -->
                    <div class="flex flex-col items-center justify-center h-full min-h-[120px] sm:min-h-[140px] lg:min-h-[160px]">
                        <svg class="mx-auto h-5 w-5 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-gray-500 text-xs font-medium">Nenhuma mensagem registrada</p>
                        <p class="text-gray-400 text-xs mt-0.5">
                            {{ $this->viewingHistory ? 'Sem anotações neste período' : 'Inicie registrando a primeira mensagem' }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- Área de input de mensagem -->
            @if(!$this->viewingHistory && !$isShiftClosed)
                <div class="flex-shrink-0 border-t border-gray-200 bg-white p-2">
                    <form wire:submit.prevent="sendChatMessage">
                        <div class="flex space-x-2">
                            <div class="w-5 h-5 bg-{{ $lightAccent }} rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-{{ $darkAccent }}">EU</span>
                            </div>
                            <div class="flex-1 space-y-2">
                                <textarea 
                                    wire:model.defer="newChatMessage"
                                    placeholder="Digite sua anotação..."
                                    class="w-full p-2 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-{{ $accentColor }} focus:border-{{ $accentColor }} resize-none"
                                    rows="2"
                                    maxlength="1000"
                                    {{ $this->messageLoading ? 'disabled' : '' }}
                                ></textarea>
                                <div class="flex justify-between items-center">
                                    <div class="text-xs text-gray-500">
                                        <span id="input-time">{{ now()->format('H:i') }}</span> - {{ Str::limit($this->currentUser['name'] ?? 'Usuário', 15) }}
                                        <span class="ml-2 px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">
                                            {{ strlen($this->newChatMessage ?? '') }}/1000
                                        </span>
                                    </div>
                                    <button 
                                        type="submit"
                                        class="px-3 py-1.5 text-white text-xs rounded transition-all duration-200 inline-flex items-center justify-center space-x-1 min-w-[80px] {{ 
                                            $this->messageLoading || empty(trim($this->newChatMessage ?? '')) 
                                                ? 'bg-gray-400 cursor-not-allowed' 
                                                : 'bg-' . $accentColor . ' hover:bg-' . $darkAccent . ' shadow-sm hover:shadow-md' 
                                        }}"
                                        disabled="{{ $this->messageLoading || empty(trim($this->newChatMessage ?? '')) ? 'true' : 'false' }}"
                                    >
                                        @if($this->messageLoading)
                                            <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                            <span>Enviando...</span>
                                        @else
                                            <span>Registrar</span>
                                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 19l9 2-9-18-9 18 9-2zm0 0v-8"/>
                                            </svg>
                                        @endif
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            @else
                <!-- Rodapé somente leitura -->
                <div class="flex-shrink-0 border-t border-gray-200 bg-gray-100 p-2">
                    <p class="text-center text-xs text-gray-600 flex items-center justify-center space-x-1">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <span>
                            {{ $this->viewingHistory ? 'Consulta de histórico' : 'Turno encerrado - Apenas visualização' }}
                        </span>
                    </p>
                </div>
            @endif
        </div>
    {{-- Erro ao carregar paciente --}}
    @else
        <div class="flex flex-col items-center justify-center py-4 text-gray-600">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
            </svg>
            <p class="text-gray-700 text-sm">Erro ao carregar detalhes do paciente</p>
            <button 
                wire:click="showPatientDetails('{{ $currentPatient['nr_atendimento'] ?? '' }}')"
                class="mt-2 px-3 py-1.5 bg-blue-500 text-white rounded hover:bg-blue-600 transition-colors text-xs"
            >
                Tentar novamente
            </button>
        </div>
    @endif
</div>
