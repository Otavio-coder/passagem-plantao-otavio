<div 
    class="h-full flex flex-col bg-white rounded-none sm:rounded-lg shadow-none sm:shadow-sm border-0 sm:border sm:border-gray-200 overflow-hidden"
    x-data="{
        componentId: '{{ $this->getId() }}',
        echoListenersBound: false,
        showFormatHelp: false,
        typingIndicator: false,
        lastTypingTime: null,
        isMobile: window.innerWidth < 768,
        init() {
            // Listen for window resize
            window.addEventListener('resize', () => {
                this.isMobile = window.innerWidth < 768;
            });
        }
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
        
        /* Mobile optimizations */
        @media (max-width: 767px) {
            .chat-container {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }
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
            $shiftColors = \App\Services\ShiftService::getShiftColors($currentShift);
            $shiftLabel = \App\Services\ShiftService::getShiftLabel($currentShift);
            
            $accentColor = $shiftColors['accentColor'];
            $lightAccent = $shiftColors['lightAccent'];
            $darkAccent = $shiftColors['darkAccent'];

            // Elementos temáticos para cada turno
            $shiftTheme = match($currentShift) {
                'manha' => [
                    'icon' => 'heroicon-o-sun',
                    'iconClass' => 'w-5 h-5 text-white animate-pulse',
                    'gradient' => 'from-amber-400 via-orange-400 to-red-400',
                    'accent' => 'shadow-orange-200/50',
                    'pattern' => 'bg-gradient-to-br from-yellow-50/10 to-orange-100/10',
                    'description' => 'Início com energia e foco',
                ],
                'tarde' => [
                    'icon' => 'heroicon-c-sun',
                    'iconClass' => 'w-5 h-5 text-white',
                    'gradient' => 'from-sky-400 via-blue-400 to-cyan-400',
                    'accent' => 'shadow-sky-200/50',
                    'pattern' => 'bg-gradient-to-br from-sky-50/10 to-blue-100/10',
                    'description' => 'Hora de avançar com consistência',
                ],
                'noite' => [
                    'icon' => 'heroicon-o-moon',
                    'iconClass' => 'w-5 h-5 text-white animate-pulse',
                    'gradient' => 'from-indigo-500 via-purple-500 to-violet-600',
                    'accent' => 'shadow-indigo-200/50',
                    'pattern' => 'bg-gradient-to-br from-indigo-50/10 to-purple-100/10',
                    'description' => 'Atenção e vigilância redobradas',
                ],
                default => [
                    'icon' => 'heroicon-o-clock',
                    'iconClass' => 'w-5 h-5 text-white',
                    'gradient' => 'from-gray-400 to-gray-500',
                    'accent' => 'shadow-gray-200/50',
                    'pattern' => 'bg-gradient-to-br from-gray-50/10 to-gray-100/10',
                    'description' => 'Compromisso em cada momento',
                ],
            };

            $pinned = collect($messages)->filter(fn($msg) => (bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
            $regular = collect($messages)->filter(fn($msg) => !(bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
        @endphp

        <!-- Header - Enhanced with shift themes -->
        <div class="flex-shrink-0 relative overflow-hidden rounded-none sm:rounded-t-lg">
            <!-- Background with pattern -->
            <div class="absolute inset-0 bg-gradient-to-r {{ $shiftTheme['gradient'] }} {{ $shiftTheme['accent'] }}"></div>
            <div class="absolute inset-0 {{ $shiftTheme['pattern'] }}"></div>
            
            <!-- Subtle geometric pattern overlay -->
            <div class="absolute inset-0 opacity-10">
                <svg class="w-full h-full" viewBox="0 0 100 100" preserveAspectRatio="none">
                    <defs>
                        <pattern id="shift-pattern-{{ $currentShift }}" x="0" y="0" width="20" height="20" patternUnits="userSpaceOnUse">
                            @if($currentShift === 'manha')
                                <circle cx="10" cy="10" r="1" fill="white" opacity="0.3"/>
                                <circle cx="5" cy="5" r="0.5" fill="white" opacity="0.2"/>
                                <circle cx="15" cy="15" r="0.5" fill="white" opacity="0.2"/>
                            @elseif($currentShift === 'tarde')
                                <rect x="8" y="8" width="4" height="4" fill="white" opacity="0.2"/>
                                <rect x="2" y="2" width="2" height="2" fill="white" opacity="0.15"/>
                                <rect x="14" y="14" width="2" height="2" fill="white" opacity="0.15"/>
                            @elseif($currentShift === 'noite')
                                <polygon points="10,2 14,8 10,14 6,8" fill="white" opacity="0.2"/>
                                <circle cx="5" cy="5" r="1" fill="white" opacity="0.1"/>
                                <circle cx="15" cy="15" r="1" fill="white" opacity="0.1"/>
                            @endif
                        </pattern>
                    </defs>
                    <rect width="100%" height="100%" fill="url(#shift-pattern-{{ $currentShift }})"/>
                </svg>
            </div>
            
            <!-- Content -->
            <div class="relative z-10 text-white p-3 sm:p-4">
                <div class="space-y-3">
                    <!-- Title section with shift icon and enhanced typography -->
                    <div class="flex items-center justify-between flex-wrap gap-2">
                        <div class="flex items-center space-x-3 min-w-0 flex-1">
                            <!-- Shift-specific icon -->
                            <div class="flex-shrink-0 p-2 bg-white/20 rounded-full backdrop-blur-sm">
                                @svg($shiftTheme['icon'], ['class' => $shiftTheme['iconClass']])
                            </div>
                            
                            <div class="min-w-0 flex-1">
                                <div class="flex items-center space-x-2">
                                    <h3 class="text-base sm:text-lg font-bold tracking-wide">PASSAGEM DE PLANTÃO</h3>
                                    @if($currentShift !== 'default')
                                        <span class="px-2 py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-medium border border-white/30">
                                            {{ strtoupper($currentShift) }}
                                        </span>
                                    @endif
                                </div>
                                <p class="text-xs text-white/90 font-medium mt-1">
                                    At: {{ $patientId }} | Lt: {{ $bedUnit ?? 'N/A' }} | {{ $shiftTheme['description'] }}
                                </p>
                            </div>
                        </div>
                        
                        <!-- Enhanced time display -->
                        <div class="flex flex-col items-end bg-white/15 backdrop-blur-sm rounded-lg px-3 py-2 border border-white/20">
                            <div id="current-time-display" class="text-white text-lg font-bold tracking-wider">
                                {{ now()->format('H:i') }}
                            </div>
                            <div class="text-white/80 text-xs font-medium">{{ now()->format('d/m/Y') }}</div>
                        </div>
                    </div>
                    
                    <!-- Shift info with enhanced styling -->
                    <div class="flex flex-col space-y-2 border-t border-white/30 pt-3">
                        <div class="flex items-center justify-between text-sm">
                            <div class="flex items-center space-x-3 min-w-0 flex-1">
                                <!-- Status indicator with shift-specific styling -->
                                <div class="flex items-center space-x-2">
                                    <div class="bg-white/20 backdrop-blur-sm rounded-full px-3 py-1 border border-white/30">
                                        <span class="text-white/95 font-medium text-xs">
                                            {{ $shiftLabel }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- User info with enhanced design -->
                            <div class="flex items-center space-x-2 bg-white/15 backdrop-blur-sm rounded-full px-3 py-1 border border-white/20">
                                @if(!empty($currentUser['photo']))
                                    <img src="data:image/jpeg;base64,{{ $currentUser['photo'] }}" alt="Foto" class="w-5 h-5 rounded-full object-cover border border-white/40" />
                                @else
                                    <div class="w-5 h-5 rounded-full bg-white/30 flex items-center justify-center border border-white/40">
                                        <span class="text-xs font-bold text-white">{{ substr($currentUser['name'] ?? 'U', 0, 1) }}</span>
                                    </div>
                                @endif
                                <span class="text-white/90 text-xs font-medium">
                                    {{ Str::limit($currentUser['name'] ?? 'Não identificado', 15) }}
                                </span>
                            </div>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row sm:items-center gap-2">
                            <div class="flex items-center space-x-2 text-xs text-white/80 flex-shrink-0 min-w-fit">
                                @svg('heroicon-o-clock', 'w-4 h-4')
                                <span class="font-medium whitespace-nowrap">Histórico:</span>
                            </div>
                            
                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                <!-- Select container with controlled width -->
                                <div class="history-select-container flex-shrink-0" style="min-width: 160px; max-width: 280px;">
                                    <select
                                        wire:model="selectedSession"
                                        wire:change="loadSessionHistory"
                                        class="history-select w-full px-2 py-1.5 text-xs border border-white/40 rounded-md bg-white/90 backdrop-blur-sm text-gray-900 hover:bg-white transition-all duration-200 focus:ring-2 focus:ring-white/50"
                                        style="max-width: 100%;"
                                    >
                                        <option value="">
                                            {{ count($availableSessions) > 1 ? 'Selecione sessão' : 'Sessão atual' }}
                                        </option>
                                        @foreach($availableSessions as $session)
                                            <option value="{{ $session['key'] }}" title="{{ $session['label'] }}">
                                                @if(strlen($session['label']) > 25)
                                                    {{ substr($session['label'], 0, 22) }}...
                                                @else
                                                    {{ $session['label'] }}
                                                @endif
                                            </option>
                                        @endforeach
                                    </select>
                                </div>
                                
                                <!-- Action buttons with better spacing -->
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    @if($viewingHistory)
                                        <button
                                            wire:click="returnToCurrentShift"
                                            class="px-2 py-1.5 bg-white/25 hover:bg-white/35 text-white text-xs rounded-md border border-white/40 transition-all duration-200 flex items-center space-x-1 backdrop-blur-sm"
                                            title="Voltar para sessão atual"
                                        >
                                            @svg('heroicon-o-arrow-left', 'w-3 h-3')
                                            <span class="hidden md:inline font-medium">Atual</span>
                                        </button>
                                    @endif
                                    
                                    @if($selectedSession)
                                        <button
                                            wire:click="clearSessionSelection"
                                            class="px-1.5 py-1.5 bg-white/15 hover:bg-white/25 text-white text-xs rounded-md border border-white/40 transition-all duration-200 flex items-center backdrop-blur-sm"
                                            title="Limpar seleção"
                                        >
                                            @svg('heroicon-o-x-mark', 'w-3 h-3')
                                        </button>
                                    @endif
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status indicator -->
        <div class="flex-shrink-0 px-3 py-2 border-b border-gray-200 bg-gray-50">
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
                            <div class="inline-flex items-center space-x-1 text-xs bg-green-100 text-green-700 px-2 py-1 rounded-full border border-green-200 flex-shrink-0">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse flex-shrink-0"></div>
                                <span class="font-medium whitespace-nowrap">Turno Ativo</span>
                            </div>
                        @endif
                    </div>
                    <!-- Format help button - Hidden on mobile -->
                    @if(!$viewingHistory && !$isShiftClosed)
                        <button
                            @click="showFormatHelp = !showFormatHelp"
                            class="hidden sm:flex text-xs text-gray-500 hover:text-gray-700 transition-colors items-center space-x-1"
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

        <!-- Format help panel - Hidden on mobile -->
        <div x-show="showFormatHelp && !isMobile" x-cloak x-transition class="flex-shrink-0 px-3 py-2 bg-blue-50 border-b border-blue-200 text-xs">
            <div class="grid grid-cols-2 gap-2 text-gray-700">
                <div><code>**texto**</code> = <strong>negrito</strong></div>
                <div><code>*texto*</code> = <em>itálico</em></div>
                <div><code>- item</code> = • item</div>
                <div>Enter duplo = nova linha</div>
            </div>
        </div>

        <!-- Messages area - Mobile Optimized -->
        <div 
            class="flex-1 overflow-y-auto p-3 bg-gray-50 relative chat-container"
            id="messages-container"
            style="-webkit-overflow-scrolling: touch; overscroll-behavior-y: contain;"
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

        <!-- Message input area - Mobile Optimized -->
        @if(!$viewingHistory && !$isShiftClosed)
            <div class="flex-shrink-0 border-t border-gray-200 bg-white p-3">
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
                            placeholder="Digite sua anotação..."
                            class="p-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-{{ $accentColor }} focus:border-{{ $accentColor }} resize-none flex-1 transition-all duration-200"  
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
                            class="px-4 py-3 text-sm rounded-lg transition-all duration-200 inline-flex items-center justify-center space-x-2 whitespace-nowrap bg-blue-600 hover:bg-blue-700 text-white disabled:bg-gray-400 disabled:cursor-not-allowed hover:scale-105 active:scale-95"
                            wire:loading.attr="disabled"
                            wire:target="sendMessage"
                        >
                            <span wire:loading.remove wire:target="sendMessage" class="hidden sm:inline">Enviar</span>
                            <span wire:loading wire:target="sendMessage">...</span>
                            @svg('iconoir-send', 'w-4 h-4 text-white')
                        </button>
                    </div>
                    <div class="flex justify-between items-center mt-2">
                        <div class="text-xs text-gray-500">
                            <span id="input-time">{{ now()->format('H:i') }}</span> - {{ Str::limit($currentUser['name'] ?? 'Usuário', 12) }}
                        </div>
                        <div class="text-xs text-gray-400 hidden sm:block">
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