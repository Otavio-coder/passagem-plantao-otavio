<div
    class="h-full flex flex-col bg-white rounded-none sm:rounded-lg shadow-none sm:shadow-sm border-0 sm:border sm:border-gray-200 overflow-hidden"
    x-data="chatComponent()"
    x-init="initialize()"
    wire:key="{{ $this->getId() }}"
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

        .message-sending {
            opacity: 0.7;
        }

        .message-failed {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }

        .messages-loading {
            position: absolute;
            inset: 0;
            background: rgba(255, 255, 255, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(1px);
            z-index: 20;
        }

        @keyframes spin {
            to { transform: rotate(360deg); }
        }

        @media (max-width: 767px) {
            .chat-container {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                touch-action: pan-y;
            }
        }

        .chat-container::-webkit-scrollbar { width: 6px; }
        .chat-container::-webkit-scrollbar-track { background: #f1f5f9; }
        .chat-container::-webkit-scrollbar-thumb { background: #cbd5e1; border-radius: 3px; }
        .chat-container::-webkit-scrollbar-thumb:hover { background: #94a3b8; }

        .send-btn-loading { background: #93c5fd !important; pointer-events: none; }
        .send-btn-loading .btn-text { opacity: 0.5; }
        .send-btn-loading .btn-spinner { display: block; }
        .btn-spinner {
            display: none;
            position: absolute; top: 50%; left: 50%;
            transform: translate(-50%, -50%);
            width: 16px; height: 16px;
            border: 2px solid white; border-top-color: transparent;
            border-radius: 50%; animation: spin 0.8s linear infinite;
        }

        .pinned-minimized { height: 40px; overflow: hidden; }
        .pinned-minimized .pinned-content { display: none; }

        /* Shift separator */
        .chat-shift-sep {
            display: flex; align-items: center; gap: 0.5rem;
            margin: 0.875rem 0 0.5rem;
        }
        .chat-shift-sep .sep-line { flex: 1; height: 1px; background: #e5e7eb; }
        .chat-shift-sep .sep-label {
            font-size: 0.65rem; font-weight: 500; color: #9ca3af;
            background: #f9fafb; padding: 0.2rem 0.65rem;
            border-radius: 999px; border: 1px solid #e5e7eb;
            flex-shrink: 0; white-space: nowrap; letter-spacing: 0.01em;
        }
    </style>

    @if(!$patientId)
        <div class="flex items-center justify-center h-full bg-gray-50">
            <div class="text-center p-4">
                <svg class="mx-auto h-6 w-6 sm:h-8 sm:w-8 text-gray-400 mb-2 sm:mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-gray-500 text-sm font-medium">Chat indisponível</p>
                <p class="text-gray-400 text-xs mt-1">Paciente não identificado</p>
            </div>
        </div>
    @elseif(!$initialized)
        <div class="flex items-center justify-center h-full bg-gray-50">
            <div class="text-center p-4">
                <div class="w-8 h-8 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-gray-600 text-sm">Inicializando chat...</p>
            </div>
        </div>
    @else
        @php
            // Derive current shift from the clock (no longer stored on the component)
            $currentShift = getShiftInfo()['shift'];

            $shiftColors = \App\Services\ShiftService::getShiftColors($currentShift);
            $shiftLabel  = \App\Services\ShiftService::getShiftLabel($currentShift);

            $accentColor = $shiftColors['accentColor'];
            $lightAccent = $shiftColors['lightAccent'];
            $darkAccent  = $shiftColors['darkAccent'];

            $shiftTheme = match($currentShift) {
                'morning' => [
                    'icon'      => 'heroicon-o-sun',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white animate-pulse',
                    'gradient'  => 'from-amber-400 via-orange-400 to-red-400',
                ],
                'afternoon' => [
                    'icon'      => 'heroicon-c-sun',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white',
                    'gradient'  => 'from-sky-400 via-blue-400 to-cyan-400',
                ],
                'night' => [
                    'icon'      => 'heroicon-o-moon',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white animate-pulse',
                    'gradient'  => 'from-indigo-500 via-purple-500 to-violet-600',
                ],
                default => [
                    'icon'      => 'heroicon-o-clock',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white',
                    'gradient'  => 'from-gray-400 to-gray-500',
                ],
            };

            $msgItems    = collect($messages)->filter(fn($m) => ($m['type'] ?? '') === 'message');
            $pinned      = $msgItems->filter(fn($m) => (bool)($m['is_pinned'] ?? false));
            $hasMessages = $msgItems->isNotEmpty();
            $msgCount    = $msgItems->count();
        @endphp

        <!-- Header -->
        <div class="flex-shrink-0 relative overflow-hidden rounded-none sm:rounded-t-lg">
            <div class="absolute inset-0 bg-gradient-to-r {{ $shiftTheme['gradient'] }}"></div>

            <div class="relative z-10 text-white p-2 sm:p-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center space-x-2 min-w-0 flex-1">
                        <div class="flex-shrink-0 p-1.5 sm:p-2 bg-white/20 rounded-full backdrop-blur-sm">
                            @svg($shiftTheme['icon'], ['class' => $shiftTheme['iconClass']])
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-2">
                                <h3 class="text-sm sm:text-base font-bold tracking-wide">PLANTÃO</h3>
                                <span class="px-1.5 py-0.5 bg-white/20 backdrop-blur-sm rounded-full text-xs font-medium border border-white/30">
                                    {{ strtoupper($currentShift) }}
                                </span>
                            </div>
                            <p class="text-xs text-white/90 font-medium">
                                At: {{ $patientId }} | Lt: {{ $bedUnit ?? 'N/A' }}
                            </p>
                        </div>
                    </div>

                    <div class="flex items-center gap-2">
                        <!-- User badge (desktop) -->
                        <div class="hidden sm:flex items-center space-x-1.5 bg-white/15 backdrop-blur-sm rounded-full px-2 py-1 border border-white/20">
                            @if(!empty($currentUser['photo']))
                                <img src="data:image/jpeg;base64,{{ $currentUser['photo'] }}" alt="Foto" class="w-4 h-4 rounded-full object-cover border border-white/40" />
                            @else
                                <div class="w-4 h-4 rounded-full bg-white/30 flex items-center justify-center border border-white/40">
                                    <span class="text-xs font-bold text-white">{{ substr($currentUser['name'] ?? 'U', 0, 1) }}</span>
                                </div>
                            @endif
                            <span class="text-white/90 text-xs font-medium">
                                {{ Str::limit($currentUser['name'] ?? 'Usuário', 14) }}
                            </span>
                        </div>

                        <!-- Clock -->
                        <div class="flex-shrink-0 bg-white/15 backdrop-blur-sm rounded-lg px-2 py-1 sm:px-3 sm:py-2 border border-white/20">
                            <div id="current-time-display" class="text-white text-sm sm:text-lg font-bold tracking-wider">
                                {{ now()->format('H:i') }}
                            </div>
                            <div class="text-white/80 text-xs font-medium hidden sm:block">{{ now()->format('d/m') }}</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Status bar -->
        <div class="flex-shrink-0 px-2 py-1.5 sm:px-3 sm:py-2 border-b border-gray-200 bg-gray-50">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <div class="inline-flex items-center space-x-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full border border-green-200">
                        <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                        <span class="font-medium">Ativo</span>
                    </div>

                    @if($msgCount > 0)
                        <span class="text-xs text-gray-400">{{ $msgCount }} {{ $msgCount === 1 ? 'anotação' : 'anotações' }}</span>
                    @endif
                </div>

                <button
                    @click="toggleFormatHelp()"
                    class="hidden lg:flex text-xs text-gray-500 hover:text-gray-700 transition-colors items-center space-x-1"
                >
                    <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                    <span>Formatação</span>
                </button>
            </div>
        </div>

        <!-- Format help -->
        <div x-show="showFormatHelp" x-cloak x-transition class="hidden lg:block flex-shrink-0 px-3 py-2 bg-blue-50 border-b border-blue-200 text-xs">
            <div class="grid grid-cols-2 gap-2 text-gray-700">
                <div><code>**texto**</code> = <strong>negrito</strong></div>
                <div><code>*texto*</code> = <em>itálico</em></div>
                <div><code>- item</code> = • item</div>
                <div>Shift+Enter = nova linha</div>
            </div>
        </div>

        <!-- Messages area -->
        <div
            class="flex-1 overflow-y-auto bg-gray-50 relative chat-container p-2 sm:p-3"
            id="messages-container"
            style="-webkit-overflow-scrolling: touch; overscroll-behavior: contain; touch-action: pan-y;"
            @scroll-to-bottom.window="scrollToBottom()"
        >
            <!-- Loading overlay -->
            <div x-show="$wire.loadingMessages" class="messages-loading">
                <div class="text-center">
                    <div class="w-6 h-6 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-2"></div>
                    <p class="text-gray-600 text-xs">Carregando...</p>
                </div>
            </div>

            @if($hasMessages)
                <!-- Pinned message sticky banner -->
                @if($pinned->count() > 0)
                    @php $pinnedMsg = $pinned->first(); @endphp
                    <div class="sticky top-0 z-10 mb-2">
                        <div class="border-2 border-yellow-400 bg-yellow-50 rounded-lg shadow p-2 transition-all duration-300"
                             :class="{ 'pinned-minimized': pinnedMinimized }">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start flex-1 min-w-0">
                                    <div class="flex-shrink-0 mr-2">
                                        @if(!empty($pinnedMsg['photo']))
                                            <img src="data:image/jpeg;base64,{{ $pinnedMsg['photo'] }}" alt="Foto" class="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover" />
                                        @else
                                            <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                                                {{ substr($pinnedMsg['author'] ?? 'U', 0, 1) }}
                                            </div>
                                        @endif
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="inline-flex items-center text-xs font-bold text-yellow-600">📌 Fixada</span>
                                            <span class="text-xs text-gray-500">{{ $pinnedMsg['time'] ?? '' }}</span>
                                        </div>
                                        <div class="pinned-content">
                                            <p class="text-xs font-semibold text-gray-800 mb-1">{{ $pinnedMsg['author'] ?? 'Usuário' }}</p>
                                            <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                                {!! $pinnedMsg['content'] !!}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="flex items-center space-x-1 flex-shrink-0 ml-2">
                                    <button @click="togglePinnedMinimized()" class="p-1 hover:bg-yellow-100 rounded transition-colors" :title="pinnedMinimized ? 'Expandir' : 'Minimizar'">
                                        <svg class="w-3 h-3 text-yellow-600 transition-transform" :class="{ 'rotate-180': pinnedMinimized }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    <button @click="togglePin({{ $pinnedMsg['id'] }})" class="p-1 hover:bg-yellow-100 rounded transition-colors" title="Desfixar" :disabled="isPinning({{ $pinnedMsg['id'] }})">
                                        <span x-show="!isPinning({{ $pinnedMsg['id'] }})">@svg('heroicon-s-star', 'w-3 h-3 text-yellow-400')</span>
                                        <span x-show="isPinning({{ $pinnedMsg['id'] }})"><div class="w-3 h-3 border border-yellow-400 border-t-transparent rounded-full animate-spin"></div></span>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Messages timeline -->
                <div class="space-y-0">
                    @foreach($messages as $item)
                        @if(($item['type'] ?? '') === 'separator')

                            {{-- ── Shift / date separator ── --}}
                            <div class="chat-shift-sep">
                                <div class="sep-line"></div>
                                <span class="sep-label">{{ $item['label'] }}</span>
                                <div class="sep-line"></div>
                            </div>

                        @elseif(($item['type'] ?? '') === 'message')
                            @php
                                $isTemp    = isset($item['is_temporary']) && $item['is_temporary'];
                                $isFailed  = isset($item['failed']) && $item['failed'];
                                $isReal    = !$isTemp && !str_starts_with((string)($item['id'] ?? ''), 'temp-');
                                $isFixed   = (bool)($item['is_pinned'] ?? false);
                                $isOwn     = (bool)($item['is_own'] ?? false);
                                $isEdited  = (bool)($item['is_edited'] ?? false);
                                $userReacted = (bool)($item['user_reacted'] ?? false);
                                $reactions  = $item['reactions'] ?? [];
                                $dtRaw      = $item['dt_criacao_raw'] ?? '';
                                $msgId      = (int)($item['id'] ?? 0);
                            @endphp
                            <div class="flex {{ $isOwn ? 'justify-end' : 'justify-start' }} group mb-1.5" id="msg-{{ $item['id'] }}">
                                <div class="flex items-start space-x-1.5 sm:space-x-2 {{ $isOwn ? 'flex-row-reverse space-x-reverse' : '' }} max-w-[90%] sm:max-w-[85%]">

                                    {{-- Avatar --}}
                                    @if(!empty($item['photo']))
                                        <img src="data:image/jpeg;base64,{{ $item['photo'] }}" alt="" class="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover flex-shrink-0 mt-0.5" />
                                    @else
                                        <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600 flex-shrink-0 mt-0.5">
                                            {{ substr($item['author'] ?? 'U', 0, 1) }}
                                        </div>
                                    @endif

                                    <div class="flex-1 min-w-0">
                                        {{-- Bubble --}}
                                        <div class="rounded-lg p-2 sm:p-2.5 shadow-sm border transition-all duration-200
                                            {{ $isOwn ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-200' }}
                                            {{ $isFixed ? 'ring-2 ring-yellow-400' : '' }}
                                            {{ $isFailed ? 'message-failed' : '' }}
                                            {{ $isTemp ? 'message-sending' : 'hover:shadow-md' }}"
                                        >
                                            {{-- Header row --}}
                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                <p class="text-xs font-medium text-gray-800">{{ $item['author'] ?? 'Usuário' }}</p>
                                                <span class="text-xs text-gray-400">{{ $item['time'] ?? '' }}</span>

                                                @if($isEdited)
                                                    <span class="text-xs text-gray-400 italic">(editado)</span>
                                                @endif
                                                @if($isTemp)
                                                    <span class="text-blue-500 text-xs animate-pulse">Enviando...</span>
                                                @endif
                                                @if($isFailed)
                                                    <span class="text-red-500 text-xs font-medium">Falha</span>
                                                @endif

                                                @if($isReal)
                                                    {{-- Pin button --}}
                                                    <button
                                                        @click="togglePin({{ $msgId }})"
                                                        class="ml-auto {{ $isFixed ? '' : 'opacity-0 group-hover:opacity-100' }} focus:outline-none transition-opacity hover:scale-110"
                                                        title="{{ $isFixed ? 'Desfixar' : 'Fixar' }}"
                                                        :disabled="isPinning({{ $msgId }})"
                                                    >
                                                        <span x-show="!isPinning({{ $msgId }})">
                                                            @if($isFixed)
                                                                @svg('heroicon-s-star', 'w-3.5 h-3.5 text-yellow-400')
                                                            @else
                                                                @svg('heroicon-o-star', 'w-3.5 h-3.5 text-gray-400')
                                                            @endif
                                                        </span>
                                                        <span x-show="isPinning({{ $msgId }})">
                                                            <div class="w-3.5 h-3.5 border border-gray-400 border-t-transparent rounded-full animate-spin"></div>
                                                        </span>
                                                    </button>

                                                    @if($isOwn)
                                                        {{-- Edit button — client-side 6h check --}}
                                                        <button
                                                            x-show="canEditMessage('{{ $dtRaw }}') && !isEditing({{ $msgId }})"
                                                            @click="startEdit({{ $msgId }}, {{ json_encode($item['content_text'] ?? '') }})"
                                                            class="opacity-0 group-hover:opacity-100 focus:outline-none transition-opacity"
                                                            title="Editar mensagem (até 6h após envio)"
                                                        >
                                                            <svg class="w-3.5 h-3.5 text-gray-400 hover:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                                            </svg>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>

                                            {{-- Message text (hidden while editing) --}}
                                            <div x-show="!isEditing({{ $msgId }})">
                                                <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                                    {!! $item['content'] !!}
                                                </div>
                                            </div>

                                            {{-- Inline edit form --}}
                                            @if($isOwn && $isReal)
                                                <div x-show="isEditing({{ $msgId }})" class="mt-1">
                                                    <textarea
                                                        id="edit-textarea-{{ $msgId }}"
                                                        x-model="editText"
                                                        class="w-full text-xs border border-blue-300 rounded p-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400 resize-none bg-white"
                                                        rows="3"
                                                        maxlength="1000"
                                                        @keydown.escape.prevent="cancelEdit()"
                                                        @keydown.ctrl.enter.prevent="saveEdit({{ $msgId }})"
                                                    ></textarea>
                                                    <div class="flex items-center gap-1.5 mt-1">
                                                        <button
                                                            @click="saveEdit({{ $msgId }})"
                                                            class="text-xs px-2.5 py-0.5 bg-blue-600 text-white rounded hover:bg-blue-700 transition-colors"
                                                        >Salvar</button>
                                                        <button
                                                            @click="cancelEdit()"
                                                            class="text-xs px-2.5 py-0.5 bg-gray-100 text-gray-600 rounded hover:bg-gray-200 transition-colors"
                                                        >Cancelar</button>
                                                        <span class="text-xs text-gray-400">Ctrl+Enter salva · Esc cancela</span>
                                                    </div>
                                                </div>
                                            @endif
                                        </div>

                                        {{-- Reactions row --}}
                                        @if($isReal)
                                            <div class="flex items-center mt-0.5 {{ $isOwn ? 'justify-end' : 'justify-start' }} gap-1.5">
                                                @if(true)
                                                    <button
                                                        @click="toggleReaction({{ $msgId }})"
                                                        class="flex items-center gap-0.5 text-xs px-1.5 py-0.5 rounded-full border transition-all duration-150
                                                            {{ $userReacted
                                                                ? 'bg-green-100 border-green-300 text-green-700'
                                                                : 'bg-white border-gray-200 text-gray-400 opacity-0 group-hover:opacity-100 hover:border-green-300 hover:text-green-600' }}"
                                                        title="{{ $userReacted ? 'Remover confirmação' : 'Confirmar leitura' }}"
                                                    >
                                                        <svg class="w-3 h-3" fill="{{ $userReacted ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        @if(count($reactions) > 0)
                                                            <span class="font-medium">{{ count($reactions) }}</span>
                                                        @endif
                                                    </button>
                                                @endif

                                                @if(count($reactions) > 0)
                                                    <div class="flex items-center gap-0.5">
                                                        @foreach(array_slice($reactions, 0, 4) as $reaction)
                                                            @if(!empty($reaction['photo']))
                                                                {{-- User has photo --}}
                                                                <div class="w-5 h-5 rounded-full overflow-hidden border border-green-400 flex-shrink-0" title="{{ $reaction['name'] ?? '' }}">
                                                                    <img src="data:image/jpeg;base64,{{ $reaction['photo'] }}" 
                                                                         alt="{{ $reaction['name'] ?? '' }}" 
                                                                         class="w-full h-full object-cover">
                                                                </div>
                                                            @else
                                                                {{-- Fallback to initials --}}
                                                                <div class="w-5 h-5 rounded-full bg-green-500 flex items-center justify-center flex-shrink-0" title="{{ $reaction['name'] ?? '' }}">
                                                                    <span class="text-white font-bold leading-none" style="font-size:0.6rem">{{ $reaction['initial'] ?? '?' }}</span>
                                                                </div>
                                                            @endif
                                                        @endforeach
                                                        @if(count($reactions) > 4)
                                                            <span class="text-xs text-gray-400 ml-0.5">+{{ count($reactions) - 4 }}</span>
                                                        @endif
                                                    </div>
                                                @endif
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                </div>

            @else
                <!-- Empty state -->
                <div class="flex flex-col items-center justify-center h-full min-h-[100px] sm:min-h-[120px] p-4">
                    <svg class="mx-auto h-4 w-4 sm:h-5 sm:w-5 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-gray-500 text-xs font-medium text-center">Nenhuma mensagem</p>
                    <p class="text-gray-400 text-xs mt-0.5 text-center px-2">Inicie a primeira anotação</p>
                </div>
            @endif
        </div>

        <!-- Message input -->
        <div class="flex-shrink-0 border-t border-gray-200 bg-white p-2 sm:p-3">
                <form @submit.prevent="sendMessage()">
                    <div class="flex items-end space-x-1.5 sm:space-x-2">
                        @if(!empty($currentUser['photo']))
                            <img src="data:image/jpeg;base64,{{ $currentUser['photo'] }}" alt="Foto" class="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover flex-shrink-0" />
                        @else
                            <div class="w-5 h-5 sm:w-6 sm:h-6 bg-{{ $lightAccent }} rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-{{ $darkAccent }}">EU</span>
                            </div>
                        @endif
                        <textarea
                            x-model="messageText"
                            x-ref="textarea"
                            placeholder="Digite sua anotação..."
                            class="p-2 sm:p-3 border border-gray-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-{{ $accentColor }} focus:border-{{ $accentColor }} resize-none flex-1 transition-all duration-200"
                            rows="1"
                            maxlength="1000"
                            :disabled="isSendingMessage()"
                            @input="autoResize()"
                            @keydown.enter.exact="
                                if (!$event.shiftKey && !isSendingMessage()) {
                                    $event.preventDefault();
                                    sendMessage();
                                }
                            "
                        ></textarea>
                        <button
                            type="submit"
                            :disabled="!messageText.trim() || isSendingMessage()"
                            :class="{ 'send-btn-loading': isSendingMessage() }"
                            class="relative px-4 py-2 rounded transition bg-blue-600 text-white disabled:bg-gray-400 disabled:cursor-not-allowed"
                        >
                            <div class="btn-spinner"></div>
                            <div class="btn-text flex items-center space-x-1">
                                <span class="hidden sm:inline">Enviar</span>
                                @svg('iconoir-send', 'w-3.5 h-3.5 sm:w-4 sm:h-4 text-white')
                            </div>
                        </button>
                    </div>
                    <div class="flex justify-between items-center mt-1 sm:mt-1.5">
                        <div class="text-xs text-gray-500">
                            <span id="input-time">{{ now()->format('H:i') }}</span> — {{ Str::limit($currentUser['name'] ?? 'Usuário', 10) }}
                        </div>
                        <div class="text-xs text-gray-400 hidden lg:block">
                            <span x-show="!isSendingMessage()">Enter = enviar</span>
                            <span x-show="isSendingMessage()">Enviando...</span>
                        </div>
                    </div>
                </form>
            </div>
    @endif
</div>
