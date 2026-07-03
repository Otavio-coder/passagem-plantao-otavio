<div
    class="h-full flex flex-col bg-white rounded-none sm:rounded-lg shadow-none sm:shadow-sm border-0 sm:border sm:border-gray-200 overflow-hidden"
    x-data="chatComponent()"
    x-init="initialize()"
    wire:poll.15s="refreshShiftHeader"
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

        <!-- Header -->
        <div class="flex-shrink-0 relative overflow-hidden rounded-none sm:rounded-t-lg">
            <div class="absolute inset-0" style="background: {{ $this->shiftDisplay['gradient_style'] ?? 'linear-gradient(90deg, #9ca3af 0%, #6b7280 100%)' }};"></div>

            <div class="relative z-10 text-white px-3 sm:px-4 py-2.5 sm:py-3.5">
                <div class="flex items-center gap-2.5 min-w-0">
                    {{-- Shift icon --}}
                    <div class="flex-shrink-0 p-2 sm:p-2.5 bg-white/20 rounded-xl border border-white/20">
                        {!! $this->shiftDisplay['icon_html'] !!}
                    </div>

                    {{-- Shift name + user info --}}
                    <div class="min-w-0 flex-1">
                        <div class="flex items-center gap-1.5 leading-none">
                            <p class="text-[11px] sm:text-xs font-bold text-white/80 leading-none">
                                Turno {{ $this->shiftDisplay['badge'] }}
                            </p>
                            @if(($this->messageStats['pinned_count'] ?? 0) > 0)
                                <button
                                    type="button"
                                    @click="document.getElementById('messages-container')?.scrollTo({ top: 0, behavior: 'smooth' })"
                                    title="Ir para anotação fixada"
                                    class="inline-flex items-center justify-center text-yellow-300 hover:text-yellow-200 focus:outline-none"
                                >
                                    <i class="fas fa-thumbtack text-[10px] sm:text-xs"></i>
                                </button>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5 mt-1 min-w-0">
                            <x-ui.user-avatar
                                :photo="$this->userPhotos[$currentUser['id'] ?? 0] ?? null"
                                :name="$currentUser['name'] ?? 'U'"
                                class="w-4 h-4 sm:w-5 sm:h-5 flex-shrink-0 border border-white/40"
                            />
                            <div class="flex items-center gap-1 bg-white/15 rounded-lg px-2 sm:px-2.5 py-0.5 sm:py-1 min-w-0">
                                <span class="text-white text-xs sm:text-sm font-semibold leading-none">
                                    {{ $currentUser['name'] ?? 'Usuário' }}
                                </span>
                                @if(!empty($currentUser['role']))
                                    <span class="text-white/70 text-[11px] sm:text-xs leading-none">· {{ $currentUser['role'] }}</span>
                                @endif
                            </div>
                        </div>
                    </div>

                    {{-- Todo list + Time + stats --}}
                    <div class="flex-shrink-0 flex flex-col items-end gap-0.5">
                        <div class="flex items-center gap-2">
                            @livewire('sbar-chat-todo-list', ['patientId' => (int) $patientId], key('chat-todo-'.$patientId))
                            <div id="current-time-display" class="text-white text-sm sm:text-base font-bold tracking-wide leading-none">
                                {{ now()->format('H:i') }}
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5">
                            <div class="w-1.5 h-1.5 bg-green-400 rounded-full animate-pulse"></div>
                            @if(($this->messageStats['shift_count'] ?? 0) > 0)
                                <span class="text-white/80 text-[10px] sm:text-xs font-medium">
                                    {{ $this->messageStats['shift_count'] }} {{ $this->messageStats['shift_count'] === 1 ? 'no turno' : 'no turno' }}
                                </span>
                            @else
                                <span class="text-white/60 text-[10px] sm:text-xs">Sem anotações no turno</span>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Metrics strip --}}
                @php
                    $stats = $this->messageStats;
                    $metricChips = [];

                    if ($internmentDays !== null && $internmentDays >= 0) {
                        $metricChips[] = [
                            'icon' => 'fa-hospital-user',
                            'value' => $internmentDays.'d internado',
                            'title' => 'Tempo de internação do paciente',
                        ];
                    }

                    if (($stats['shift_count'] ?? 0) > 0 && ($stats['unique_contributors'] ?? 0) > 0) {
                        $metricChips[] = [
                            'icon' => 'fa-users',
                            'value' => $stats['unique_contributors'].' '.($stats['unique_contributors'] === 1 ? 'participante' : 'participantes'),
                            'title' => 'Profissionais que anotaram neste turno',
                        ];
                    }

                    $minutesAgo = $stats['last_message_minutes_ago'] ?? null;
                    if ($minutesAgo !== null && $stats['has_messages']) {
                        if ($minutesAgo < 1) {
                            $lastLabel = 'agora';
                        } elseif ($minutesAgo < 60) {
                            $lastLabel = 'há '.$minutesAgo.'min';
                        } elseif ($minutesAgo < 60 * 24) {
                            $lastLabel = 'há '.intdiv($minutesAgo, 60).'h';
                        } else {
                            $lastLabel = 'há '.intdiv($minutesAgo, 60 * 24).'d';
                        }
                        $metricChips[] = [
                            'icon' => 'fa-clock',
                            'value' => 'Última: '.$lastLabel,
                            'title' => 'Tempo desde a última anotação',
                        ];
                    }
                @endphp
                @if(!empty($metricChips))
                    <div class="mt-1.5 flex flex-nowrap sm:flex-wrap items-center gap-1.5 border-t border-white/15 pt-1.5 overflow-x-auto sm:overflow-visible -mx-1 px-1 sm:mx-0 sm:px-0">
                        @foreach($metricChips as $chip)
                            <span
                                class="inline-flex items-center gap-1 bg-white/10 rounded-full px-2 py-0.5 text-[10px] text-white/80 whitespace-nowrap flex-shrink-0"
                                title="{{ $chip['title'] ?? '' }}"
                            >
                                <i class="fas {{ $chip['icon'] }} text-[9px]"></i>
                                {{ $chip['value'] }}
                            </span>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>

        {{-- Notice banner: chat is for digital SBAR notes, not formal evaluations --}}
        <div
            x-data="{
                show: localStorage.getItem('sbar_chat_notice_v1') !== 'dismissed'
            }"
            x-show="show"
            x-cloak
            class="flex-shrink-0 border-b border-amber-200 bg-amber-50 px-3 py-2"
        >
            <div class="flex items-start gap-2">
                <i class="fas fa-circle-info text-amber-500 text-xs mt-0.5 flex-shrink-0"></i>
                <p class="flex-1 text-[11px] text-amber-800 leading-snug">
                    <span class="font-semibold">Este chat é o SBAR digital.</span>
                    Registre aqui as informações que seriam escritas no papel durante a passagem de plantão.
                    Não é uma avaliação formal de enfermagem e não possui integração direta com o Tasy.
                </p>
                <button
                    @click="show = false; localStorage.setItem('sbar_chat_notice_v1', 'dismissed')"
                    class="flex-shrink-0 text-amber-400 hover:text-amber-600 transition-colors p-0.5"
                    title="Entendido, não mostrar novamente"
                >
                    <i class="fas fa-xmark text-xs"></i>
                </button>
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

            @if(!$showAllMessages && $totalMessageCount > 5)
                <button
                    wire:click="loadAllMessages"
                    wire:loading.attr="disabled"
                    wire:target="loadAllMessages"
                    class="w-full mb-2 flex items-center justify-center gap-2 px-3 py-2 bg-blue-50 hover:bg-blue-100 border border-blue-200 rounded-lg text-xs text-blue-700 font-medium transition-colors"
                >
                    <span wire:loading.remove wire:target="loadAllMessages">
                        <i class="fas fa-comments fa-xs"></i>
                        Carregar todas as {{ $totalMessageCount }} anotações
                    </span>
                    <span wire:loading wire:target="loadAllMessages" class="flex items-center gap-1.5">
                        <div class="w-3 h-3 border-2 border-blue-400 border-t-transparent rounded-full animate-spin"></div>
                        Carregando...
                    </span>
                </button>
            @endif

            @if($hasOlderMessages || $hasArchivedHistory)
                <div class="mb-2 flex items-center gap-1.5 px-2 py-1.5 bg-gray-100 border border-gray-200 rounded-lg text-xs text-gray-400">
                    <i class="fas fa-clock-rotate-left fa-xs"></i>
                    <span>Anotações limitadas aos últimos 30 dias.</span>
                </div>
            @endif

            @if($this->messageStats['has_messages'])
                <!-- Pinned message sticky banner -->
                @if($this->messageStats['pinned_first'])
                    <div class="sticky top-0 z-10 mb-2">
                        <div class="border-2 border-yellow-400 bg-yellow-50 rounded-lg shadow p-2 transition-all duration-300"
                             :class="{ 'pinned-minimized': pinnedMinimized }">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start flex-1 min-w-0">
                                    <div class="flex-shrink-0 mr-2">
                                        <x-ui.user-avatar :photo="$this->userPhotos[$this->messageStats['pinned_first']['user_id'] ?? 0] ?? null" :name="$this->messageStats['pinned_first']['author'] ?? 'U'" class="w-5 h-5 sm:w-6 sm:h-6" />
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="inline-flex items-center gap-1 text-xs font-bold text-yellow-600"><i class="fas fa-thumbtack fa-xs"></i> Fixada</span>
                                            <span class="text-xs text-gray-500">{{ $this->messageStats['pinned_first']['time'] ?? '' }}</span>
                                        </div>
                                        <div class="pinned-content">
                                            <p class="text-xs font-semibold text-gray-800 mb-1">{{ $this->messageStats['pinned_first']['author'] ?? 'Usuário' }}</p>
                                            <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                                {!! $this->messageStats['pinned_first']['content'] !!}
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
                                    <button @click="togglePin({{ $this->messageStats['pinned_first']['id'] }})" class="p-1 hover:bg-yellow-100 rounded transition-colors" title="Desfixar" :disabled="isPinning({{ $this->messageStats['pinned_first']['id'] }})">
                                        <span x-show="!isPinning({{ $this->messageStats['pinned_first']['id'] }})"><i class="fas fa-star fa-xs text-yellow-400"></i></span>
                                        <span x-show="isPinning({{ $this->messageStats['pinned_first']['id'] }})"><div class="w-3 h-3 border border-yellow-400 border-t-transparent rounded-full animate-spin"></div></span>
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
                            <div class="flex {{ ($item['is_own'] ?? false) ? 'justify-end' : 'justify-start' }} group mb-1.5" id="msg-{{ $item['id'] }}">
                                <div class="flex items-start space-x-1.5 sm:space-x-2 {{ ($item['is_own'] ?? false) ? 'flex-row-reverse space-x-reverse' : '' }} max-w-[90%] sm:max-w-[85%]">

                                    {{-- Avatar --}}
                                    <x-ui.user-avatar :photo="$this->userPhotos[$item['user_id'] ?? 0] ?? null" :name="$item['author'] ?? 'U'" class="w-5 h-5 sm:w-6 sm:h-6 mt-0.5" />

                                    <div class="flex-1 min-w-0">
                                        {{-- Bubble --}}
                                        <div class="rounded-lg p-2 sm:p-2.5 shadow-sm border transition-all duration-200
                                            {{ ($item['is_own'] ?? false) ? 'bg-blue-50 border-blue-200' : 'bg-white border-gray-200' }}
                                            {{ ($item['is_pinned'] ?? false) ? 'ring-2 ring-yellow-400' : '' }}
                                            {{ ($item['failed'] ?? false) ? 'message-failed' : '' }}
                                            {{ ($item['is_temporary'] ?? false) ? 'message-sending' : 'hover:shadow-md' }}"
                                        >
                                            {{-- Header row --}}
                                            <div class="flex items-center gap-1.5 mb-1 flex-wrap">
                                                <p class="text-xs font-medium text-gray-800">{{ $item['author'] ?? 'Usuário' }}</p>
                                                <span class="text-xs text-gray-400">{{ $item['time'] ?? '' }}</span>

                                                @if($item['is_edited'] ?? false)
                                                    <span class="text-xs text-gray-400 italic">(editado)</span>
                                                @endif
                                                @if($item['is_temporary'] ?? false)
                                                    <span class="text-blue-500 text-xs animate-pulse">Enviando...</span>
                                                @endif
                                                @if($item['failed'] ?? false)
                                                    <span class="text-red-500 text-xs font-medium">Falha</span>
                                                @endif

                                                @if($item['is_real'] ?? false)
                                                    {{-- Pin button --}}
                                                    <button
                                                        @click="togglePin({{ $item['msg_id'] ?? 0 }})"
                                                        class="ml-auto {{ ($item['is_pinned'] ?? false) ? '' : 'opacity-0 group-hover:opacity-100' }} focus:outline-none transition-opacity hover:scale-110"
                                                        title="{{ ($item['is_pinned'] ?? false) ? 'Desfixar' : 'Fixar' }}"
                                                        :disabled="isPinning({{ $item['msg_id'] ?? 0 }})"
                                                    >
                                                        <span x-show="!isPinning({{ $item['msg_id'] ?? 0 }})">
                                                            @if($item['is_pinned'] ?? false)
                                                                <i class="fas fa-star fa-sm text-yellow-400"></i>
                                                            @else
                                                                <i class="far fa-star fa-sm text-gray-400"></i>
                                                            @endif
                                                        </span>
                                                        <span x-show="isPinning({{ $item['msg_id'] ?? 0 }})">
                                                            <div class="w-3.5 h-3.5 border border-gray-400 border-t-transparent rounded-full animate-spin"></div>
                                                        </span>
                                                    </button>

                                                    @if($item['is_own'] ?? false)
                                                        {{-- Edit button — client-side 6h check --}}
                                                        <button
                                                            x-show="canEditMessage('{{ $item['dt_criacao_raw'] ?? '' }}') && !isEditing({{ $item['msg_id'] ?? 0 }})"
                                                            @click="startEdit({{ $item['msg_id'] ?? 0 }}, {{ json_encode($item['content_text'] ?? '') }})"
                                                            class="opacity-0 group-hover:opacity-100 focus:outline-none transition-opacity"
                                                            title="Editar mensagem (até 6h após envio)"
                                                        >
                                                            <i class="fas fa-pen fa-sm text-gray-400 hover:text-blue-500"></i>
                                                        </button>
                                                    @endif
                                                @endif
                                            </div>

                                            {{-- Message text (hidden while editing) --}}
                                            <div x-show="!isEditing({{ $item['msg_id'] ?? 0 }})">
                                                <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                                    {!! $item['content'] !!}
                                                </div>
                                            </div>

                                            {{-- Inline edit form --}}
                                            @if(($item['is_own'] ?? false) && ($item['is_real'] ?? false))
                                                <div x-show="isEditing({{ $item['msg_id'] ?? 0 }})" class="mt-1">
                                                    <textarea
                                                        id="edit-textarea-{{ $item['msg_id'] ?? 0 }}"
                                                        x-model="editText"
                                                        class="w-full text-xs border border-blue-300 rounded p-1.5 focus:outline-none focus:ring-1 focus:ring-blue-400 resize-none bg-white"
                                                        rows="3"
                                                        maxlength="1000"
                                                        @keydown.escape.prevent="cancelEdit()"
                                                        @keydown.ctrl.enter.prevent="saveEdit({{ $item['msg_id'] ?? 0 }})"
                                                    ></textarea>
                                                    <div class="flex items-center gap-1.5 mt-1">
                                                        <button
                                                            @click="saveEdit({{ $item['msg_id'] ?? 0 }})"
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
                                        @if($item['is_real'] ?? false)
                                            <div class="flex items-center mt-0.5 {{ ($item['is_own'] ?? false) ? 'justify-end' : 'justify-start' }} gap-1.5">
                                                @if(!($item['is_own'] ?? false))
                                                    <button
                                                        @click="toggleReaction({{ $item['msg_id'] ?? 0 }})"
                                                        class="flex items-center gap-0.5 text-xs px-1.5 py-0.5 rounded-full border transition-all duration-150
                                                            {{ ($item['user_reacted'] ?? false)
                                                                ? 'bg-green-100 border-green-300 text-green-700'
                                                                : 'bg-white border-gray-200 text-gray-400 opacity-0 group-hover:opacity-100 hover:border-green-300 hover:text-green-600' }}"
                                                        title="{{ ($item['user_reacted'] ?? false) ? 'Remover confirmação' : 'Confirmar leitura' }}"
                                                    >
                                                        <svg class="w-3 h-3" fill="{{ ($item['user_reacted'] ?? false) ? 'currentColor' : 'none' }}" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                                                        </svg>
                                                        @if(($item['reactions_count'] ?? 0) > 0)
                                                            <span class="font-medium">{{ $item['reactions_count'] }}</span>
                                                        @endif
                                                    </button>
                                                @endif

                                                @if(($item['reactions_count'] ?? 0) > 0)
                                                    <div class="flex items-center gap-0.5">
                                                        @foreach(array_slice($item['reactions'] ?? [], 0, 4) as $reaction)
                                                            <x-ui.user-avatar
                                                                :photo="$this->userPhotos[$reaction['user_id'] ?? 0] ?? null"
                                                                :name="$reaction['name'] ?? '?'"
                                                                class="w-5 h-5 border border-green-400"
                                                                :title="$reaction['name'] ?? ''"
                                                            />
                                                        @endforeach
                                                        @if(($item['reactions_count'] ?? 0) > 4)
                                                            <span class="text-xs text-gray-400 ml-0.5">+{{ ($item['reactions_count'] ?? 0) - 4 }}</span>
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
        <div class="flex-shrink-0 border-t border-slate-200 bg-white p-2 sm:p-3">
            <form @submit.prevent="sendMessage()">
                <div class="flex items-center gap-2.5 sm:gap-2">
                    <div class="relative flex-1">
                        <textarea
                            x-model="messageText"
                            x-ref="textarea"
                            placeholder="Digite sua anotação..."
                            class="w-full min-h-[44px] max-h-40 resize-none rounded-xl border border-slate-300 bg-white px-3 py-2.5 text-sm leading-relaxed text-slate-800 shadow-sm transition duration-200 placeholder:text-slate-400 focus:ring-2 focus:outline-none disabled:bg-slate-100 disabled:text-slate-400"
                            style="--focus-color: {{ $this->shiftDisplay['hex_color'] }}; box-shadow: none;"
                            rows="1"
                            maxlength="1000"
                            :disabled="isSendingMessage()"
                            @input="autoResize()"
                            @focus="$el.style.borderColor = '{{ $this->shiftDisplay['hex_color'] }}'; $el.style.boxShadow = '0 0 0 3px {{ $this->shiftDisplay['hex_color'] }}26';"
                            @blur="$el.style.borderColor = ''; $el.style.boxShadow = 'none';"
                            @keydown.enter.exact="
                                if (!$event.shiftKey && !isSendingMessage()) {
                                    $event.preventDefault();
                                    sendMessage();
                                }
                            "
                        ></textarea>
                    </div>
                    <button
                        type="submit"
                        :disabled="!messageText.trim() || isSendingMessage()"
                        class="flex h-11 w-11 sm:h-10 sm:w-10 flex-shrink-0 items-center justify-center rounded-xl text-white shadow-sm transition-all duration-200 focus:outline-none active:scale-95 disabled:cursor-not-allowed disabled:opacity-60"
                        :style="(!messageText.trim() || isSendingMessage()) ? 'background-color: #94a3b8;' : 'background-color: {{ $this->shiftDisplay['hex_color'] }};'"
                    >
                        <i x-show="!isSendingMessage()" class="fas fa-paper-plane text-sm"></i>
                        <i x-show="isSendingMessage()" class="fas fa-spinner fa-spin text-sm"></i>
                    </button>
                </div>
            </form>
        </div>
    @endif
</div>
