@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
    'currentShift' => null,
    'currentUser' => null,
    'viewingHistory' => false,
    'shiftMessages' => [],
    'newChatMessage' => '',
    'messageLoading' => false,
    'availableSessions' => [],
    'selectedSession' => null,
    'loadingMessages' => false,
    'currentDate' => null,   
])

<!-- SBAR Avaliação Modal Content -->
<div x-show="activeTab === 'tab-a'" 
     x-transition:enter="transition ease-out duration-300"
     x-transition:enter-end="opacity-100 transform translate-y-0"
     @after-enter="setTimeout(() => { if (typeof scrollMessagesContainer === 'function') scrollMessagesContainer(); }, 50)" 
     class="p-1 sm:p-2 lg:p-3 h-full flex flex-col"
     style="min-height: 0;">
    {{-- Loading State --}}
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-4 sm:py-6 lg:py-8 flex-1">
            <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center" style="top: 50%;">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
            <p class="text-gray-600 text-xs sm:text-sm">Carregando detalhes do paciente...</p>
        </div>
    @elseif($currentPatient && !$currentPatient['has_patient'])
        <div class="flex flex-col items-center justify-center py-4 sm:py-6 text-gray-600 flex-1">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
            </svg>
            <p class="text-gray-700 text-sm sm:text-base">Leito Vazio</p>
            <p class="text-gray-500 text-xs">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif($patientDetails)
        @php
            $now = \Carbon\Carbon::now('America/Sao_Paulo');
            $currentHour = $now->hour;
            $isShiftClosed = false;
            if ($currentShift === 'dia' && ($currentHour < 7 || $currentHour >= 19)) {
                $isShiftClosed = true;
            } elseif ($currentShift === 'noite' && ($currentHour >= 7 && $currentHour < 19)) {
                $isShiftClosed = true;
            }
            if ($currentShift === 'dia') {
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

            $pinned = collect($shiftMessages)->filter(fn($msg) => (bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
            $regular = collect($shiftMessages)->filter(fn($msg) => !(bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
        @endphp

        <!-- Container principal do chat -->
        <div class="bg-white rounded-lg shadow-sm border border-gray-200 flex flex-col overflow-hidden flex-1 min-h-0 h-full">
            
            <!-- Cabeçalho compacto -->
            <div class="flex-shrink-0 bg-gradient-to-r {{ $headerBg }} text-white p-2 sm:p-3 rounded-t-lg">
                <div class="space-y-2">
                    <!-- Título e info do paciente -->
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center space-x-2 min-w-0 flex-1">
                            @svg('fluentui-shifts-team-24', ['class' => 'w-6 h-6 sm:w-8 sm:h-8 text-white flex-shrink-0'])
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
                                    {{ $currentShift === 'dia' ? 'Dia (07-19h)' : 'Noite (19-07h)' }} | 
                                    {{ Str::limit($currentUser['name'] ?? 'Não identificado', 20) }}
                                </span>
                            </div>
                        </div>
                        <!-- Navegação de histórico -->
                        <div class="flex flex-wrap items-center gap-1 sm:gap-2">
                            <span class="text-xs text-white/80 flex-shrink-0">Histórico:</span>
                            <div class="relative">
                                <select
                                    wire:model.defer="selectedSession"
                                    class="w-32 sm:w-44 md:w-56 px-1 py-1 text-xs border border-white/30 rounded bg-white text-gray-900 hover:bg-gray-100 transition-colors"
                                >
                                    <option value="">Selecione uma sessão</option>
                                    @foreach($availableSessions as $session)
                                        <option value="{{ $session['key'] }}">
                                            {{ $session['label'] }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>
                            @php
                                $currentKey = ($currentPatient['nr_atendimento'] ?? '') ? ($selectedHistoryDate ?? now()->toDateString()) . '|' . ($selectedHistoryShift ?? $currentShift) : '';
                                $isCurrentSession = $selectedSession === ($currentDate . '|' . $currentShift);
                            @endphp
                            <button
                                wire:click="applySessionFilter"
                                @if(!$selectedSession || $isCurrentSession) disabled @endif
                                class="px-2 py-1 bg-white/20 hover:bg-white/30 text-white text-xs rounded border border-white/30 transition-colors flex items-center space-x-1 disabled:opacity-50 disabled:cursor-not-allowed"
                            >
                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/>
                                    <circle cx="12" cy="7" r="4"/>
                                </svg>
                                <span>Aplicar Filtro</span>
                            </button>
                            @if($viewingHistory)
                                <button
                                    wire:click="returnToCurrentShift"
                                    class="px-1.5 py-1 bg-white/20 hover:bg-white/30 text-white text-xs rounded border border-white/30 transition-colors flex items-center space-x-1"
                                    title="Voltar para sessão atual"
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

            <!-- Indicador de status do turno/histórico -->
            <div class="flex-shrink-0 px-2 py-1 border-b border-gray-200 bg-gray-50">
                @if($viewingHistory && $selectedSession)
                    @php
                        $session = collect($availableSessions)->firstWhere('key', $selectedSession);
                    @endphp
                    <div class="flex items-center justify-start">
                        <div class="inline-flex items-center space-x-1 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200 flex-shrink-0">
                            <svg class="w-3 h-3 flex-shrink-0" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                            </svg>
                            <span class="font-medium whitespace-nowrap">
                                {{ $session['label'] ?? 'Sessão histórica' }}
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
            <div 
                class="flex-1 overflow-y-auto p-2 bg-gray-50"
                id="messages-container"
                x-init="
                    $el.setMaxHeight = function() {
                        var h = window.innerHeight;
                        var w = window.innerWidth;
                        var maxH;
                        if (w < 480) { maxH = h > w ? (w < 400 ? '32vh' : '38vh') : '30vh'; }
                        else if (w < 640) { maxH = h > w ? '45vh' : '32vh'; }
                        else if (w < 768) { maxH = h > w ? '55vh' : '40vh'; }
                        else if (w < 1024) { maxH = h > w ? '60vh' : '45vh'; }
                        else { maxH = h > w ? '65vh' : '55vh'; }
                        $el.style.maxHeight = maxH;
                    };
                    $el.setMaxHeight();
                    window.addEventListener('resize', $el.setMaxHeight);
                "
                style="
                    min-height: 28vh;
                    height: 20vh;
                "
            >
                @if($loadingMessages)
                    <!-- Skeleton loading para mensagens -->
                    <div class="space-y-3">
                        @for($i = 0; $i < 4; $i++)
                            <div class="flex items-start space-x-2 animate-pulse">
                                <div class="w-6 h-6 rounded-full bg-gray-200"></div>
                                <div class="flex-1 space-y-2">
                                    <div class="h-3 bg-gray-200 rounded w-1/3"></div>
                                    <div class="h-4 bg-gray-200 rounded w-5/6"></div>
                                </div>
                            </div>
                        @endfor
                    </div>
                @elseif(count($shiftMessages) > 0)
                    {{-- Pinned message at the top --}}
                    @if(!$viewingHistory && $pinned->count() > 0)
                        @php 
                            $message = $pinned->first(); 
                            $user = \App\Models\System\User::find($message['usuario_id']);
                            $author = $user ? $user->name : 'Usuário';
                            $photo = $user && method_exists($user, 'photo') ? $user->photo() : '';
                        @endphp
                        <div class="sticky top-0 z-10 mb-2" id="msg-{{ $message['id'] }}">
                            <div class="border-2 border-yellow-400 bg-yellow-50 rounded-lg shadow p-2 flex items-start relative">
                                <div class="flex-shrink-0 mr-2">
                                    @if(!empty($photo))
                                        <img src="data:image/jpeg;base64,{{ $photo }}" alt="Foto" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                                    @else
                                        <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600 flex-shrink-0">
                                            {{ substr($author, 0, 1) }}
                                        </div>
                                    @endif
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center space-x-2 mb-1">
                                        <span class="inline-flex items-center text-xs font-bold text-yellow-600">
                                            Mensagem fixada
                                        </span>
                                        <span class="text-xs text-gray-500">{{ $message['time'] ?? '' }}</span>
                                        @if(!$viewingHistory && !$isShiftClosed)
                                            <button
                                                wire:click="toggleMessagePin({{ $message['id'] }})"
                                                class="ml-2 focus:outline-none"
                                                title="Desfixar mensagem"
                                            >
                                                @svg('heroicon-s-star', 'w-5 h-5 text-yellow-400')
                                            </button>
                                        @endif
                                    </div>
                                    <div class="flex items-center space-x-2 mb-1">
                                        <p class="text-xs font-semibold text-gray-800 truncate">{{ $message['author'] }}</p>
                                    </div>
                                    <p class="text-xs text-gray-700 leading-relaxed">{{ $message['mensagem'] }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Regular messages --}}
                    <div class="space-y-2">
                        @foreach($regular as $message)
                            @php
                                $user = \App\Models\System\User::find($message['usuario_id']);
                                $isOwn = $message['usuario_id'] == ($currentUser['id'] ?? null);
                                $author = $user ? $user->name : 'Usuário';
                                $photo = $user && method_exists($user, 'photo') ? $user->photo() : '';
                                $isPinned = $message['is_pinned'] ?? $message['is_fixed'] ?? false;
                            @endphp
                            <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }}" id="msg-{{ $message['id'] }}">
                                <div class="flex items-start space-x-2 {{ $isOwn ? 'flex-row-reverse space-x-reverse' : '' }}">
                                    @if(!empty($photo))
                                        <img src="data:image/jpeg;base64,{{ $photo }}" alt="Foto" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                                    @else
                                        <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600 flex-shrink-0">
                                            {{ substr($author, 0, 1) }}
                                        </div>
                                    @endif
                                    <div class="flex-1 min-w-0">
                                        <div class="bg-white rounded p-2 shadow-sm border border-gray-200 hover:shadow-md transition-shadow {{ $isOwn ? 'bg-blue-50 border-blue-200' : '' }}">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <p class="text-xs font-medium text-gray-800 truncate">{{ $author }}</p>
                                                <span class="text-xs text-gray-500">{{ $message['time'] ?? '' }}</span>
                                                @if(!$viewingHistory && !$isShiftClosed)
                                                    <button
                                                        wire:click="toggleMessagePin({{ $message['id'] }})"
                                                        class="ml-1 focus:outline-none"
                                                        title="Fixar mensagem"
                                                    >
                                                        @if($isPinned)
                                                            @svg('heroicon-s-star', 'w-4 h-4 text-yellow-400')
                                                        @else
                                                            @svg('heroicon-o-star', 'w-4 h-4 text-gray-400 hover:text-yellow-400')
                                                        @endif
                                                    </button>
                                                @endif
                                            </div>
                                            <p class="text-xs text-gray-700 leading-relaxed">{{ $message['mensagem'] }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <!-- Estado vazio de mensagens -->
                    <div class="flex flex-col items-center justify-center h-full min-h-[120px] sm:min-h-[140px] lg:min-h-[160px]">
                        <svg class="mx-auto h-5 w-5 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                        <p class="text-gray-500 text-xs font-medium">Nenhuma mensagem registrada</p>
                        <p class="text-gray-400 text-xs mt-0.5">
                            {{ $viewingHistory ? 'Sem anotações neste período' : 'Inicie registrando a primeira mensagem' }}
                        </p>
                    </div>
                @endif
            </div>

            <!-- Área de input de mensagem -->
            @if(!$viewingHistory && !$isShiftClosed)
                <div class="flex-shrink-0 border-t border-gray-200 bg-white p-2"
                    x-data="{
                        message: @entangle('newChatMessage').defer || '',
                        loading: @entangle('messageLoading')
                    }"
                >
                    <form 
                        wire:submit.prevent="sendChatMessage"
                        @submit.prevent="
                            $wire.sendChatMessage();
                            message = '';
                            setTimeout(() => { $refs.textarea?.focus(); }, 100);
                        "
                    >
                        <div class="flex items-end space-x-2 flex-wrap">
                            @if(!empty($currentUser['photo']))
                                <img src="data:image/jpeg;base64,{{ $currentUser['photo'] }}" alt="Foto" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                            @else
                                <div class="w-6 h-6 bg-{{ $lightAccent }} rounded-full flex items-center justify-center flex-shrink-0">
                                    <span class="text-xs font-bold text-{{ $darkAccent }}">EU</span>
                                </div>
                            @endif
                            <textarea 
                                x-model="message"
                                wire:model.defer="newChatMessage"
                                placeholder="Digite sua anotação..."
                                class="p-2 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-{{ $accentColor }} focus:border-{{ $accentColor }} resize-none flex-1 min-w-0"  
                                rows="1"
                                maxlength="1000"
                                :disabled="loading"
                                x-ref="textarea"
                                style="min-width: 0;"
                            ></textarea>
                            <button
                                type="submit"
                                class="px-3 py-1.5 text-xs rounded transition-all duration-200 inline-flex items-center justify-center space-x-1 whitespace-nowrap"
                                :class="(loading || !message || !message.trim())
                                    ? 'bg-gray-400 text-white cursor-not-allowed'
                                    : 'bg-blue-600 hover:bg-blue-700 text-white'"
                                :disabled="loading || !message || !message.trim()"
                            >
                                <template x-if="loading">
                                    <span class="flex items-center space-x-1">
                                        <svg class="animate-spin h-3 w-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span>Enviando...</span>
                                    </span>
                                </template>
                                <template x-if="!loading">
                                    <span class="flex items-center space-x-1">
                                        <span>Enviar</span>
                                        @svg('iconoir-send', 'w-3 h-3 text-white')
                                    </span>
                                </template>
                            </button>
                        </div>
                        <div class="flex justify-between items-center mt-1 flex-wrap gap-y-1">
                            <div class="text-xs text-gray-500 truncate">
                                <span id="input-time">{{ now()->format('H:i') }}</span> - {{ Str::limit($currentUser['name'] ?? 'Usuário', 15) }}
                                <span class="ml-2 px-1.5 py-0.5 bg-gray-100 rounded text-gray-600">
                                    <span x-text="message ? message.length : 0"></span>/1000
                                </span>
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
                            {{ $viewingHistory ? 'Consulta de histórico' : 'Turno encerrado - Apenas visualização' }}
                        </span>
                    </p>
                </div>
            @endif
            <!-- Modal de confirmação para troca de fixação -->
            <div x-data="{ showConfirm: false, pendingPinId: null }"
                @show-pin-confirm.window="pendingPinId = $event.detail.newPinId; showConfirm = true"
            >
                <div x-show="showConfirm" class="fixed inset-0 bg-black/40 flex items-center justify-center z-50">
                    <div class="bg-white p-4 rounded shadow">
                        <p class="mb-2 text-sm">Já existe uma mensagem fixada. Deseja desfixar e fixar esta?</p>
                        <button @click="$wire.call('toggleMessagePin', pendingPinId); showConfirm = false" class="bg-blue-600 text-white px-3 py-1 rounded mr-2">Sim</button>
                        <button @click="showConfirm = false" class="bg-gray-300 px-3 py-1 rounded">Cancelar</button>
                    </div>
                </div>
            </div>
        </div>
    @else
        <div class="flex flex-col items-center justify-center py-4 text-gray-600 flex-1">
            <svg class="w-6 h-6 sm:w-8 sm:h-8 text-red-500 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77-1.333.192 3 1.732 3z" />
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