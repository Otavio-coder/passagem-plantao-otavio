<div 
    class="h-full flex flex-col bg-white rounded-lg shadow-sm border border-gray-200 overflow-hidden"
    x-data="{
        componentId: '{{ $this->getId() }}',
        echoListenersBound: false,
        showFormatHelp: false,
        typingIndicator: false,
        lastTypingTime: null
    }"
    x-init="
        // Initialize chat when component loads if patientId exists
        @if($patientId)
            $wire.initialize();
            
            // Set up Echo listeners after initialization
            $nextTick(() => {
                if (window.bindChatEchoListeners && !echoListenersBound) {
                    window.bindChatEchoListeners('{{ $patientId }}', '{{ $currentShift }}', componentId);
                    echoListenersBound = true;
                }
            });
        @endif
    "
    @chat-initialized.window="
        if ($event.detail.componentId === componentId && window.bindChatEchoListeners && !echoListenersBound) {
            window.bindChatEchoListeners($event.detail.patientId, $event.detail.shift, componentId);
            echoListenersBound = true;
        }
    "
>
    <style>
        .prose.prose-xs {
            font-size: 0.75rem;
            line-height: 1.5;
        }
        .prose.prose-xs strong {
            font-weight: 600;
        }
        .prose.prose-xs em {
            font-style: italic;
        }
    </style>

    @if(!$patientId)
        <!-- No patient ID provided -->
        <div class="flex items-center justify-center h-full bg-gray-50">
            <div class="text-center">
                <svg class="mx-auto h-8 w-8 text-gray-400 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-gray-500 text-sm font-medium">Chat indisponível</p>
                <p class="text-gray-400 text-xs mt-1">Paciente não identificado</p>
            </div>
        </div>
    @elseif(!$initialized)
        <!-- Enhanced loading state -->
        <div class="flex items-center justify-center h-full bg-gray-50">
            <div class="text-center">
                <div class="relative">
                    <div class="w-8 h-8 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-3"></div>
                    <div class="absolute inset-0 w-8 h-8 border-4 border-t-transparent border-blue-200 rounded-full animate-pulse mx-auto"></div>
                </div>
                <p class="text-gray-600 text-sm animate-pulse">Inicializando chat...</p>
                <div class="flex justify-center mt-2 space-x-1">
                    <div class="w-1 h-1 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 0ms"></div>
                    <div class="w-1 h-1 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 150ms"></div>
                    <div class="w-1 h-1 bg-blue-500 rounded-full animate-bounce" style="animation-delay: 300ms"></div>
                </div>
            </div>
        </div>
    @else
        @php
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

            $pinned = collect($messages)->filter(fn($msg) => (bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
            $regular = collect($messages)->filter(fn($msg) => !(bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
        @endphp

        <!-- Header -->
        <div class="flex-shrink-0 bg-gradient-to-r {{ $headerBg }} text-white p-2 sm:p-3 rounded-t-lg">
            <div class="space-y-2">
                <!-- Title and patient info -->
                <div class="flex items-center justify-between flex-wrap gap-2">
                    <div class="flex items-center space-x-2 min-w-0 flex-1">
                        @svg('fluentui-shifts-team-24', ['class' => 'w-6 h-6 sm:w-8 sm:h-8 text-white flex-shrink-0'])
                        <div class="min-w-0 flex-1">
                            <h3 class="text-xs sm:text-sm font-bold truncate">PASSAGEM DE PLANTÃO</h3>
                            <p class="text-xs text-white/80 truncate">
                                At: {{ $patientId }} | Lt: {{ $bedUnit ?? 'N/A' }}
                            </p>
                        </div>
                    </div>
                    <!-- Current time -->
                    <div class="text-right flex-shrink-0">
                        <div id="current-time-display" class="text-white text-xs font-medium">{{ now()->format('H:i') }}</div>
                        <div class="text-white/70 text-xs">{{ now()->format('d/m') }}</div>
                    </div>
                </div>
                
                <!-- Shift info and history controls -->
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
                    
                    <!-- History navigation -->
                    <div class="flex flex-wrap items-center gap-1 sm:gap-2">
                        <span class="text-xs text-white/80 flex-shrink-0">Histórico:</span>
                        <div class="relative">
                            <select
                                wire:model="selectedSession"
                                wire:change="loadSessionHistory"
                                class="w-32 sm:w-44 md:w-56 px-1 py-1 text-xs border border-white/30 rounded bg-white text-gray-900 hover:bg-gray-100 transition-colors"
                            >
                                <option value="">{{ count($availableSessions) > 1 ? 'Selecione uma sessão' : 'Sessão atual' }}</option>
                                @foreach($availableSessions as $session)
                                    <option value="{{ $session['key'] }}">
                                        {{ $session['label'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        
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

        <!-- Status indicator -->
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
                <div class="flex items-center justify-between">
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
                    <!-- Format help button -->
                    @if(!$viewingHistory && !$isShiftClosed)
                        <button
                            @click="showFormatHelp = !showFormatHelp"
                            class="text-xs text-gray-500 hover:text-gray-700 transition-colors flex items-center space-x-1"
                            title="Ajuda de formatação"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Formatação</span>
                        </button>
                    @endif
                </div>
            @endif
        </div>

        <!-- Format help panel -->
        <div x-show="showFormatHelp" x-cloak x-transition class="flex-shrink-0 px-2 py-2 bg-blue-50 border-b border-blue-200 text-xs">
            <div class="grid grid-cols-2 gap-2 text-gray-700">
                <div><code>**texto**</code> = <strong>negrito</strong></div>
                <div><code>*texto*</code> = <em>itálico</em></div>
                <div><code>- item</code> = • item</div>
                <div>Enter duplo = nova linha</div>
            </div>
        </div>

        <!-- Messages area -->
        <div 
            class="flex-1 overflow-y-auto p-2 bg-gray-50 relative"
            id="messages-container"
            @scroll-to-bottom.window="
                $nextTick(() => {
                    $el.scrollTop = $el.scrollHeight;
                });
            "
        >
            <!-- Loading overlay for messages -->
            @if($loadingMessages)
                <div class="absolute inset-0 flex items-center justify-center bg-gray-50/80 backdrop-blur-sm z-10">
                    <div class="text-center">
                        <div class="w-6 h-6 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-2"></div>
                        <p class="text-gray-600 text-xs">Carregando mensagens...</p>
                    </div>
                </div>
            @endif

            @if(count($messages) > 0)
                <!-- Pinned message at the top -->
                @if(!$viewingHistory && $pinned->count() > 0)
                    @php $message = $pinned->first(); @endphp
                    <div class="sticky top-0 z-10 mb-2" id="msg-{{ $message['id'] }}">
                        <div class="border-2 border-yellow-400 bg-yellow-50 rounded-lg shadow p-2 flex items-start relative">
                            <div class="flex-shrink-0 mr-2">
                                @if(!empty($message['photo']))
                                    <img src="data:image/jpeg;base64,{{ $message['photo'] }}" alt="Foto" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                                @else
                                    <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600 flex-shrink-0">
                                        {{ substr($message['author'], 0, 1) }}
                                    </div>
                                @endif
                            </div>
                            <div class="flex-1 min-w-0">
                                <div class="flex items-center space-x-2 mb-1">
                                    <span class="inline-flex items-center text-xs font-bold text-yellow-600">
                                        📌 Mensagem fixada
                                    </span>
                                    <span class="text-xs text-gray-500">{{ $message['time'] ?? '' }}</span>
                                    @if(!$viewingHistory && !$isShiftClosed)
                                        <button
                                            wire:click="toggleMessagePin({{ $message['id'] }})"
                                            class="ml-2 focus:outline-none hover:scale-110 transition-transform"
                                            title="Desfixar mensagem"
                                        >
                                            @svg('heroicon-s-star', 'w-5 h-5 text-yellow-400')
                                        </button>
                                    @endif
                                </div>
                                <div class="flex items-center space-x-2 mb-1">
                                    <p class="text-xs font-semibold text-gray-800 truncate">{{ $message['author'] }}</p>
                                </div>
                                <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                    {!! $message['mensagem'] !!}
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Regular messages -->
                <div class="space-y-2">
                    @foreach($regular as $message)
                        <div class="flex {{ $message['is_own'] ? 'justify-end' : 'justify-start' }} group" id="msg-{{ $message['id'] }}">
                            <div class="flex items-start space-x-2 {{ $message['is_own'] ? 'flex-row-reverse space-x-reverse' : '' }} max-w-[85%]">
                                @if(!empty($message['photo']))
                                    <img src="data:image/jpeg;base64,{{ $message['photo'] }}" alt="Foto" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                                @else
                                    <div class="w-6 h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600 flex-shrink-0">
                                        {{ substr($message['author'], 0, 1) }}
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <div class="bg-white rounded-lg p-2 shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 {{ $message['is_own'] ? 'bg-blue-50 border-blue-200' : '' }}">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <p class="text-xs font-medium text-gray-800 truncate">{{ $message['author'] }}</p>
                                            <span class="text-xs text-gray-500">{{ $message['time'] ?? '' }}</span>
                                            @if(!$viewingHistory && !$isShiftClosed)
                                                <button
                                                    wire:click="toggleMessagePin({{ $message['id'] }})"
                                                    class="ml-1 focus:outline-none opacity-0 group-hover:opacity-100 transition-opacity hover:scale-110"
                                                    title="Fixar mensagem"
                                                >
                                                    @if($message['is_pinned'] ?? false)
                                                        @svg('heroicon-s-star', 'w-4 h-4 text-yellow-400')
                                                    @else
                                                        @svg('heroicon-o-star', 'w-4 h-4 text-gray-400 hover:text-yellow-400')
                                                    @endif
                                                </button>
                                            @endif
                                        </div>
                                        <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                            {!! $message['mensagem'] !!}
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <!-- Empty state -->
                <div class="flex flex-col items-center justify-center h-full min-h-[120px]">
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

        <!-- Message input area -->
        @if(!$viewingHistory && !$isShiftClosed)
            <div class="flex-shrink-0 border-t border-gray-200 bg-white p-2">
                <form 
                    wire:submit.prevent="sendMessage"
                    @submit.prevent="
                        $wire.sendMessage();
                        $refs.textarea.value = '';
                        $refs.textarea.style.height = 'auto';
                        $refs.textarea.focus();
                    "
                >
                    <div class="flex items-end space-x-2">
                        @if(!empty($currentUser['photo']))
                            <img src="data:image/jpeg;base64,{{ $currentUser['photo'] }}" alt="Foto" class="w-6 h-6 rounded-full object-cover flex-shrink-0" />
                        @else
                            <div class="w-6 h-6 bg-{{ $lightAccent }} rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-{{ $darkAccent }}">EU</span>
                            </div>
                        @endif
                        <textarea 
                            wire:model.defer="newMessage"
                            placeholder="Digite sua anotação... (Use **negrito**, *itálico*, - lista)"
                            class="p-2 border border-gray-300 rounded text-xs focus:outline-none focus:ring-1 focus:ring-{{ $accentColor }} focus:border-{{ $accentColor }} resize-none flex-1 transition-all duration-200"  
                            rows="1"
                            maxlength="1000"
                            x-ref="textarea"
                            @input="
                                $refs.textarea.style.height = 'auto';
                                $refs.textarea.style.height = Math.min($refs.textarea.scrollHeight, 120) + 'px';
                            "
                            @keydown.enter.exact="
                                if (!$event.shiftKey) {
                                    $event.preventDefault();
                                    $wire.sendMessage();
                                    $refs.textarea.value = '';
                                    $refs.textarea.style.height = 'auto';
                                }
                            "
                        ></textarea>
                        <button
                            type="submit"
                            class="px-3 py-1.5 text-xs rounded transition-all duration-200 inline-flex items-center justify-center space-x-1 whitespace-nowrap bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-400 disabled:cursor-not-allowed hover:scale-105 active:scale-95"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                        >
                            <span wire:loading.remove wire:target="sendMessage">Enviar</span>
                            <span wire:loading wire:target="sendMessage">...</span>
                            @svg('iconoir-send', 'w-3 h-3 text-white')
                        </button>
                    </div>
                    <div class="flex justify-between items-center mt-1">
                        <div class="text-xs text-gray-500">
                            <span id="input-time">{{ now()->format('H:i') }}</span> - {{ Str::limit($currentUser['name'] ?? 'Usuário', 15) }}
                        </div>
                        <div class="text-xs text-gray-400">
                            Enter = enviar | Shift+Enter = nova linha
                        </div>
                    </div>
                </form>
            </div>
        @else
            <!-- Read-only footer -->
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
    @endif
</div>