<div 
    class="h-full flex flex-col bg-white rounded-none sm:rounded-lg shadow-none sm:shadow-sm border-0 sm:border sm:border-gray-200 overflow-hidden"
    x-data="chatComponent()"
    x-init="initialize()"
    wire:key="<?php echo e($this->getId()); ?>"
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
        
        /* Loading states simplificados */
        .message-sending {
            opacity: 0.7;
        }
        
        .message-failed {
            border-color: #ef4444 !important;
            background-color: #fef2f2 !important;
        }
        
        /* Overlay de loading específico para área de mensagens */
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
        
        /* Loading overlay para histórico */
        .history-loading {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, transparent, #3b82f6, transparent);
            animation: loading-bar 1.2s infinite;
            z-index: 15;
        }
        
        @keyframes loading-bar {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }
        
        /* Animations */
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        
        /* Mobile optimizations */
        @media (max-width: 767px) {
            .chat-container {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
                touch-action: pan-y;
            }
        }
        
        /* Scrollbar customizado */
        .chat-container::-webkit-scrollbar {
            width: 6px;
        }
        
        .chat-container::-webkit-scrollbar-track {
            background: #f1f5f9;
        }
        
        .chat-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }
        
        .chat-container::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
        
        /* Send button loading */
        .send-btn-loading {
            background: #93c5fd !important;
            pointer-events: none;
        }
        
        .send-btn-loading .btn-text {
            opacity: 0.5;
        }
        
        .send-btn-loading .btn-spinner {
            display: block;
        }
        
        .btn-spinner {
            display: none;
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            width: 16px;
            height: 16px;
            border: 2px solid white;
            border-top-color: transparent;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        
        /* Mensagem fixada minimizada */
        .pinned-minimized {
            height: 40px;
            overflow: hidden;
        }
        
        .pinned-minimized .pinned-content {
            display: none;
        }
        
        /* Histórico melhorado */
        .history-dropdown {
            max-height: 60px; /* Lista de opções bem menor */
            overflow-y: auto;
            font-size: 0.75rem;
            height: 28px; /* Altura fixa menor para o select */
            min-height: 28px;
        }
        
        .history-dropdown::-webkit-scrollbar {
            width: 3px;
        }
        
        .history-dropdown::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 2px;
        }
        
        .history-dropdown optgroup {
            font-weight: 600;
            color: #374151;
            background-color: #f9fafb;
            padding: 2px 4px; /* Padding menor para economizar espaço */
            font-size: 0.65rem;
        }
        
        .history-dropdown option {
            font-weight: normal;
            color: #6b7280;
            padding: 1px 2px; /* Padding menor */
            font-size: 0.65rem;
        }
        
        /* Responsivo para mobile */
        @media (max-width: 640px) {
            .history-dropdown {
                max-height: 50px; /* Lista ainda menor no mobile */
                font-size: 0.7rem;
                height: 26px;
                min-height: 26px;
            }
            
            .history-dropdown optgroup {
                font-size: 0.6rem;
                padding: 1px 3px;
            }
            
            .history-dropdown option {
                font-size: 0.6rem;
                padding: 0px 1px;
            }
        }
        
        /* Container do histórico responsivo */
        .history-container {
            min-width: 0;
            max-width: 280px;
        }
        
        @media (max-width: 768px) {
            .history-container {
                max-width: 200px;
            }
        }
        
        @media (max-width: 640px) {
            .history-container {
                max-width: 160px;
            }
        }
        
        /* Forçar altura menor no select */
        .history-select-container select {
            height: 28px !important;
            min-height: 28px !important;
            padding: 2px 6px !important;
            line-height: 1.2 !important;
        }
        
        @media (max-width: 640px) {
            .history-select-container select {
                height: 26px !important;
                min-height: 26px !important;
                padding: 1px 4px !important;
            }
        }
    </style>

    <!--[if BLOCK]><![endif]--><?php if(!$patientId): ?>
        <!-- No patient ID provided -->
        <div class="flex items-center justify-center h-full bg-gray-50">
            <div class="text-center p-4">
                <svg class="mx-auto h-6 w-6 sm:h-8 sm:w-8 text-gray-400 mb-2 sm:mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
                <p class="text-gray-500 text-sm font-medium">Chat indisponível</p>
                <p class="text-gray-400 text-xs mt-1">Paciente não identificado</p>
            </div>
        </div>
    <?php elseif(!$initialized): ?>
        <!-- Loading state -->
        <div class="flex items-center justify-center h-full bg-gray-50">
            <div class="text-center p-4">
                <div class="w-8 h-8 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-3"></div>
                <p class="text-gray-600 text-sm">Inicializando chat...</p>
            </div>
        </div>
    <?php else: ?>
        <?php
            $shiftColors = \App\Services\ShiftService::getShiftColors($currentShift);
            $shiftLabel = \App\Services\ShiftService::getShiftLabel($currentShift);
            
            $accentColor = $shiftColors['accentColor'];
            $lightAccent = $shiftColors['lightAccent'];
            $darkAccent = $shiftColors['darkAccent'];

            $shiftTheme = match($currentShift) {
                'manha' => [
                    'icon' => 'heroicon-o-sun',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white animate-pulse',
                    'gradient' => 'from-amber-400 via-orange-400 to-red-400',
                    'accent' => 'shadow-orange-200/50',
                ],
                'tarde' => [
                    'icon' => 'heroicon-c-sun',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white',
                    'gradient' => 'from-sky-400 via-blue-400 to-cyan-400',
                    'accent' => 'shadow-sky-200/50',
                ],
                'noite' => [
                    'icon' => 'heroicon-o-moon',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white animate-pulse',
                    'gradient' => 'from-indigo-500 via-purple-500 to-violet-600',
                    'accent' => 'shadow-indigo-200/50',
                ],
                default => [
                    'icon' => 'heroicon-o-clock',
                    'iconClass' => 'w-4 h-4 sm:w-5 sm:h-5 text-white',
                    'gradient' => 'from-gray-400 to-gray-500',
                    'accent' => 'shadow-gray-200/50',
                ],
            };

            $pinned = collect($messages)->filter(fn($msg) => (bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
            $regular = collect($messages)->filter(fn($msg) => !(bool)($msg['is_pinned'] ?? $msg['is_fixed'] ?? false));
        ?>

        <!-- Header -->
        <div class="flex-shrink-0 relative overflow-hidden rounded-none sm:rounded-t-lg">
            <div class="absolute inset-0 bg-gradient-to-r <?php echo e($shiftTheme['gradient']); ?> <?php echo e($shiftTheme['accent']); ?>"></div>
            
            <!-- Loading bar para histórico -->
            <div x-show="isLoadingHistory()" class="history-loading"></div>
            
            <div class="relative z-10 text-white p-2 sm:p-3">
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center space-x-2 min-w-0 flex-1">
                        <div class="flex-shrink-0 p-1.5 sm:p-2 bg-white/20 rounded-full backdrop-blur-sm">
                            <?php echo e(svg($shiftTheme['icon'], ['class' => $shiftTheme['iconClass']])); ?>
                        </div>
                        
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center space-x-2 flex-wrap">
                                <h3 class="text-sm sm:text-base font-bold tracking-wide">PLANTÃO</h3>
                                <span class="px-1.5 py-0.5 sm:px-2 sm:py-1 bg-white/20 backdrop-blur-sm rounded-full text-xs font-medium border border-white/30">
                                    <?php echo e(strtoupper($currentShift)); ?>

                                </span>
                            </div>
                            <p class="text-xs text-white/90 font-medium">
                                At: <?php echo e($patientId); ?> | Lt: <?php echo e($bedUnit ?? 'N/A'); ?>

                            </p>
                        </div>
                    </div>
                    
                    <div class="flex-shrink-0 bg-white/15 backdrop-blur-sm rounded-lg px-2 py-1 sm:px-3 sm:py-2 border border-white/20">
                        <div id="current-time-display" class="text-white text-sm sm:text-lg font-bold tracking-wider">
                            <?php echo e(now()->format('H:i')); ?>

                        </div>
                        <div class="text-white/80 text-xs font-medium hidden sm:block"><?php echo e(now()->format('d/m')); ?></div>
                    </div>
                </div>
                
                <!-- History section melhorado -->
                <div class="mt-2 pt-2 border-t border-white/30 hidden sm:block">
                    <div class="flex items-center justify-between gap-2">
                        <div class="flex items-center gap-2 flex-1 min-w-0">
                            <div class="flex items-center space-x-1 text-xs text-white/80 flex-shrink-0">
                                <?php echo e(svg('heroicon-o-clock', 'w-3 h-3')); ?>
                                <span class="font-medium">Histórico:</span>
                            </div>
                            
                            <div class="flex items-center gap-1 flex-1 min-w-0 max-w-xs lg:max-w-sm">
                                <!-- Navigation for history pagination -->
                                <!-- Navigation for history pagination -->
                                <div class="flex items-center gap-1 flex-shrink-0" x-show="totalHistoryPages > 1 && Object.keys($wire.groupedSessions || {}).length > 0">
                                    <button
                                        @click="previousHistoryPage()"
                                        class="p-1 bg-white/15 hover:bg-white/25 text-white text-xs rounded border border-white/40 transition-all duration-200 disabled:opacity-30"
                                        title="Página anterior"
                                        :disabled="isLoadingHistory() || historyPage <= 0"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                        </svg>
                                    </button>
                                    
                                    <div class="px-1.5 py-0.5 bg-white/20 rounded text-xs text-white font-medium">
                                        <span x-text="historyPage + 1"></span>/<span x-text="totalHistoryPages"></span>
                                    </div>
                                    
                                    <button
                                        @click="nextHistoryPage()"
                                        class="p-1 bg-white/15 hover:bg-white/25 text-white text-xs rounded border border-white/40 transition-all duration-200 disabled:opacity-30"
                                        title="Próxima página"
                                        :disabled="isLoadingHistory() || historyPage >= totalHistoryPages - 1"
                                    >
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                        </svg>
                                    </button>
                                </div>
                                
                                <div class="history-select-container flex-1 relative">
                                    <select
                                        x-model="$wire.selectedSession"
                                        @change="if ($wire.selectedSession) { loadSessionHistory(); }"
                                        class="w-full px-2 py-1 text-xs border border-white/40 rounded-md bg-white/95 backdrop-blur-sm text-gray-900 hover:bg-white transition-all duration-200 focus:ring-2 focus:ring-white/50 history-dropdown"
                                        :disabled="isLoadingHistory()"
                                        :class="{ 'opacity-50': isLoadingHistory() }"
                                    >
                                        <option value="">
                                            <!--[if BLOCK]><![endif]--><?php if(count($groupedSessions) > 0): ?>
                                                Selecionar sessão
                                            <?php else: ?>
                                                Sem histórico
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                        </option>
                                        <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $groupedSessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $dateGroup => $sessions): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                            <optgroup label="📅 <?php echo e($dateGroup); ?>">
                                                <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $sessions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $session): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                                                    <option value="<?php echo e($session['key']); ?>">
                                                        &nbsp;&nbsp;<?php echo e($session['shift_label']); ?> (<?php echo e($session['messageCount']); ?>)
                                                    </option>
                                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                            </optgroup>
                                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                                    </select>
                                </div>
                                
                                <div class="flex items-center gap-1 flex-shrink-0">
                                    <!--[if BLOCK]><![endif]--><?php if($viewingHistory): ?>
                                        <button
                                            @click="returnToCurrentShift()"
                                            class="px-2 py-1 bg-white/25 hover:bg-white/35 text-white text-xs rounded-md border border-white/40 transition-all duration-200 disabled:opacity-50"
                                            title="Voltar"
                                            :disabled="isLoadingHistory()"
                                        >
                                            <?php echo e(svg('heroicon-o-arrow-left', 'w-3 h-3')); ?>
                                        </button>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    
                                    <!--[if BLOCK]><![endif]--><?php if($selectedSession): ?>
                                        <button
                                            @click="clearSessionSelection()"
                                            class="px-1.5 py-1 bg-white/15 hover:bg-white/25 text-white text-xs rounded-md border border-white/40 transition-all duration-200 disabled:opacity-50"
                                            title="Limpar"
                                            :disabled="isLoadingHistory()"
                                        >
                                            <?php echo e(svg('heroicon-o-x-mark', 'w-3 h-3')); ?>
                                        </button>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex items-center space-x-2 bg-white/15 backdrop-blur-sm rounded-full px-2 py-1 border border-white/20 flex-shrink-0">
                            <!--[if BLOCK]><![endif]--><?php if(!empty($currentUser['photo'])): ?>
                                <img src="data:image/jpeg;base64,<?php echo e($currentUser['photo']); ?>" alt="Foto" class="w-4 h-4 rounded-full object-cover border border-white/40" />
                            <?php else: ?>
                                <div class="w-4 h-4 rounded-full bg-white/30 flex items-center justify-center border border-white/40">
                                    <span class="text-xs font-bold text-white"><?php echo e(substr($currentUser['name'] ?? 'U', 0, 1)); ?></span>
                                </div>
                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                            <span class="text-white/90 text-xs font-medium hidden lg:inline">
                                <?php echo e(Str::limit($currentUser['name'] ?? 'Usuário', 12)); ?>

                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Status indicator -->
        <div class="flex-shrink-0 px-2 py-1.5 sm:px-3 sm:py-2 border-b border-gray-200 bg-gray-50">
            <!--[if BLOCK]><![endif]--><?php if($viewingHistory && $selectedSession): ?>
                <?php
                    $session = collect($availableSessions)->firstWhere('key', $selectedSession);
                ?>
                <div class="flex items-center justify-between">
                    <div class="inline-flex items-center space-x-1 text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full border border-amber-200">
                        <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm1-12a1 1 0 10-2 0v4a1 1 0 00.293.707l2.828 2.829a1 1 0 101.415-1.415L11 9.586V6z" clip-rule="evenodd"/>
                        </svg>
                        <span class="font-medium"><?php echo e($session['label'] ?? 'Histórico'); ?></span>
                    </div>
                </div>
            <?php else: ?>
                <div class="flex items-center justify-between">
                    <div class="flex items-center justify-start">
                        <!--[if BLOCK]><![endif]--><?php if($isShiftClosed): ?>
                            <div class="inline-flex items-center space-x-1 text-xs bg-gray-100 text-gray-600 px-2 py-0.5 rounded-full border border-gray-300">
                                <div class="w-2 h-2 bg-gray-500 rounded-full"></div>
                                <span class="font-medium">Encerrado</span>
                            </div>
                        <?php else: ?>
                            <div class="inline-flex items-center space-x-1 text-xs bg-green-100 text-green-700 px-2 py-0.5 rounded-full border border-green-200">
                                <div class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></div>
                                <span class="font-medium">Ativo</span>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                    </div>
                    
                    <!--[if BLOCK]><![endif]--><?php if(!$viewingHistory && !$isShiftClosed): ?>
                        <button
                            @click="toggleFormatHelp()"
                            class="hidden lg:flex text-xs text-gray-500 hover:text-gray-700 transition-colors items-center space-x-1"
                        >
                            <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.228 9c.549-1.165 2.03-2 3.772-2 2.21 0 4 1.343 4 3 0 1.4-1.278 2.575-3.006 2.907-.542.104-.994.54-.994 1.093m0 3h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                            </svg>
                            <span>Formatação</span>
                        </button>
                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
        </div>

        <!-- Format help -->
        <div x-show="showFormatHelp" x-cloak x-transition class="hidden lg:block flex-shrink-0 px-3 py-2 bg-blue-50 border-b border-blue-200 text-xs">
            <div class="grid grid-cols-2 gap-2 text-gray-700">
                <div><code>**texto**</code> = <strong>negrito</strong></div>
                <div><code>*texto*</code> = <em>itálico</em></div>
                <div><code>- item</code> = • item</div>
                <div>Enter duplo = nova linha</div>
            </div>
        </div>

        <!-- Mensagens área -->
        <div 
            class="flex-1 overflow-y-auto bg-gray-50 relative chat-container p-2 sm:p-3"
            id="messages-container"
            style="-webkit-overflow-scrolling: touch; overscroll-behavior: contain; touch-action: pan-y;"
            @scroll-to-bottom.window="scrollToBottom()"
        >
            <!-- Loading overlay específico para mensagens -->
            <div 
                x-show="$wire.loadingMessages"
                class="messages-loading"
            >
                <div class="text-center">
                    <div class="w-6 h-6 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-2"></div>
                    <p class="text-gray-600 text-xs">Carregando mensagens...</p>
                </div>
            </div>

            <!--[if BLOCK]><![endif]--><?php if(count($messages) > 0): ?>
                <!-- Mensagem fixada com opção de minimizar -->
                <!--[if BLOCK]><![endif]--><?php if($pinned->count() > 0): ?>
                    <?php $message = $pinned->first(); ?>
                    <div class="sticky top-0 z-10 mb-2">
                        <div class="border-2 border-yellow-400 bg-yellow-50 rounded-lg shadow p-2 transition-all duration-300"
                             :class="{ 'pinned-minimized': pinnedMinimized }">
                            <div class="flex items-start justify-between">
                                <div class="flex items-start flex-1 min-w-0">
                                    <div class="flex-shrink-0 mr-2">
                                        <!--[if BLOCK]><![endif]--><?php if(!empty($message['photo'])): ?>
                                            <img src="data:image/jpeg;base64,<?php echo e($message['photo']); ?>" alt="Foto" class="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover" />
                                        <?php else: ?>
                                            <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600">
                                                <?php echo e(substr($message['author'], 0, 1)); ?>

                                            </div>
                                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center space-x-2 mb-1">
                                            <span class="inline-flex items-center text-xs font-bold text-yellow-600">
                                                📌 Fixada
                                            </span>
                                            <span class="text-xs text-gray-500"><?php echo e($message['time'] ?? ''); ?></span>
                                        </div>
                                        <div class="pinned-content">
                                            <div class="flex items-center space-x-2 mb-1">
                                                <p class="text-xs font-semibold text-gray-800"><?php echo e($message['author']); ?></p>
                                            </div>
                                            <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                                <?php echo $message['mensagem']; ?>

                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center space-x-1 flex-shrink-0 ml-2">
                                    <button
                                        @click="togglePinnedMinimized()"
                                        class="p-1 hover:bg-yellow-100 rounded transition-colors"
                                        :title="pinnedMinimized ? 'Expandir' : 'Minimizar'"
                                    >
                                        <svg class="w-3 h-3 text-yellow-600 transition-transform" 
                                             :class="{ 'rotate-180': pinnedMinimized }" 
                                             fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                        </svg>
                                    </button>
                                    
                                    <!--[if BLOCK]><![endif]--><?php if(!$viewingHistory && !$isShiftClosed): ?>
                                        <button
                                            @click="togglePin(<?php echo e($message['id']); ?>)"
                                            class="p-1 hover:bg-yellow-100 rounded transition-colors"
                                            title="Desfixar"
                                            :disabled="isPinning(<?php echo e($message['id']); ?>)"
                                        >
                                            <span x-show="!isPinning(<?php echo e($message['id']); ?>)">
                                                <?php echo e(svg('heroicon-s-star', 'w-3 h-3 text-yellow-400')); ?>
                                            </span>
                                            <span x-show="isPinning(<?php echo e($message['id']); ?>)">
                                                <div class="w-3 h-3 border border-yellow-400 border-t-transparent rounded-full animate-spin"></div>
                                            </span>
                                        </button>
                                    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->

                <!-- Mensagens regulares -->
                <div class="space-y-1.5 sm:space-y-2">
                    <!--[if BLOCK]><![endif]--><?php $__currentLoopData = $regular; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $message): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                        <div class="flex <?php echo e($message['is_own'] ? 'justify-end' : 'justify-start'); ?> group" id="msg-<?php echo e($message['id']); ?>">
                            <div class="flex items-start space-x-1.5 sm:space-x-2 <?php echo e($message['is_own'] ? 'flex-row-reverse space-x-reverse' : ''); ?> max-w-[90%] sm:max-w-[85%]">
                                <!--[if BLOCK]><![endif]--><?php if(!empty($message['photo'])): ?>
                                    <img src="data:image/jpeg;base64,<?php echo e($message['photo']); ?>" alt="Foto" class="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover flex-shrink-0" />
                                <?php else: ?>
                                    <div class="w-5 h-5 sm:w-6 sm:h-6 rounded-full bg-gray-300 flex items-center justify-center text-xs text-gray-600 flex-shrink-0">
                                        <?php echo e(substr($message['author'], 0, 1)); ?>

                                    </div>
                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                <div class="flex-1 min-w-0">
                                    <div class="bg-white rounded-lg p-2 sm:p-2.5 shadow-sm border border-gray-200 hover:shadow-md transition-all duration-200 
                                        <?php echo e($message['is_own'] ? 'bg-blue-50 border-blue-200' : ''); ?>

                                        <?php echo e(isset($message['failed']) && $message['failed'] ? 'message-failed' : ''); ?>

                                        <?php echo e(isset($message['is_temporary']) && $message['is_temporary'] ? 'message-sending' : ''); ?>"
                                    >
                                        <div class="flex items-center space-x-1.5 sm:space-x-2 mb-1">
                                            <p class="text-xs font-medium text-gray-800">
                                                <?php echo e($message['author']); ?>

                                            </p>
                                            <span class="text-xs text-gray-500"><?php echo e($message['time'] ?? ''); ?></span>
                                            
                                            <!--[if BLOCK]><![endif]--><?php if(isset($message['is_temporary']) && $message['is_temporary']): ?>
                                                <span class="ml-1 text-blue-500 text-xs font-medium animate-pulse">Enviando...</span>
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                            
                                            <!--[if BLOCK]><![endif]--><?php if(isset($message['failed']) && $message['failed']): ?>
                                                <span class="ml-1 text-red-500 text-xs font-medium">Falha</span>
                                                <button 
                                                    @click="retryMessage('<?php echo e($message['id']); ?>')"
                                                    class="ml-1 text-red-500 hover:text-red-700 text-xs underline"
                                                >
                                                    Reenviar
                                                </button>
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                            
                                            <!--[if BLOCK]><![endif]--><?php if(!$viewingHistory && !$isShiftClosed): ?>
                                                <?php
                                                    $isTemporary = isset($message['is_temporary']) && $message['is_temporary'];
                                                    $isOptimistic = isset($message['is_optimistic']) && $message['is_optimistic'];
                                                    $isRealMessage = !$isTemporary && !$isOptimistic && !str_starts_with($message['id'], 'temp-');
                                                ?>
                                                
                                                <!--[if BLOCK]><![endif]--><?php if($isRealMessage): ?>
                                                    <button
                                                        @click="togglePin(<?php echo e($message['id']); ?>)"
                                                        class="ml-1 focus:outline-none opacity-0 group-hover:opacity-100 sm:opacity-100 transition-opacity hover:scale-110"
                                                        title="Fixar mensagem"
                                                        :disabled="isPinning(<?php echo e($message['id']); ?>)"
                                                    >
                                                        <span x-show="!isPinning(<?php echo e($message['id']); ?>)">
                                                            <?php echo e(svg('heroicon-o-star', 'w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-400 hover:text-yellow-400')); ?>
                                                        </span>
                                                        <span x-show="isPinning(<?php echo e($message['id']); ?>)">
                                                            <div class="w-3.5 h-3.5 border border-gray-400 border-t-transparent rounded-full animate-spin"></div>
                                                        </span>
                                                    </button>
                                                <?php else: ?>
                                                    <div class="ml-1 opacity-50" title="Aguarde o envio">
                                                        <?php echo e(svg('heroicon-o-star', 'w-3.5 h-3.5 sm:w-4 sm:h-4 text-gray-300')); ?>
                                                    </div>
                                                <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                                        </div>
                                        <div class="text-xs text-gray-700 leading-relaxed prose prose-xs max-w-none">
                                            <?php echo $message['mensagem']; ?>

                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><!--[if ENDBLOCK]><![endif]-->
                </div>
            <?php else: ?>
                <!-- Empty state -->
                <div class="flex flex-col items-center justify-center h-full min-h-[100px] sm:min-h-[120px] p-4">
                    <svg class="mx-auto h-4 w-4 sm:h-5 sm:w-5 text-gray-400 mb-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                    </svg>
                    <p class="text-gray-500 text-xs font-medium text-center">Nenhuma mensagem</p>
                    <p class="text-gray-400 text-xs mt-0.5 text-center px-2">
                        <?php echo e($viewingHistory ? 'Sem anotações' : 'Inicie a primeira mensagem'); ?>

                    </p>
                </div>
            <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
        </div>

        <!-- Message input area -->
        <!--[if BLOCK]><![endif]--><?php if(!$viewingHistory && !$isShiftClosed): ?>
            <div class="flex-shrink-0 border-t border-gray-200 bg-white p-2 sm:p-3">
                <form @submit.prevent="sendMessage()">
                    <div class="flex items-end space-x-1.5 sm:space-x-2">
                        <!--[if BLOCK]><![endif]--><?php if(!empty($currentUser['photo'])): ?>
                            <img src="data:image/jpeg;base64,<?php echo e($currentUser['photo']); ?>" alt="Foto" class="w-5 h-5 sm:w-6 sm:h-6 rounded-full object-cover flex-shrink-0" />
                        <?php else: ?>
                            <div class="w-5 h-5 sm:w-6 sm:h-6 bg-<?php echo e($lightAccent); ?> rounded-full flex items-center justify-center flex-shrink-0">
                                <span class="text-xs font-bold text-<?php echo e($darkAccent); ?>">EU</span>
                            </div>
                        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
                        <textarea 
                            x-model="messageText"
                            x-ref="textarea"
                            placeholder="Digite sua anotação..."
                            class="p-2 sm:p-3 border border-gray-300 rounded-lg text-xs sm:text-sm focus:outline-none focus:ring-2 focus:ring-<?php echo e($accentColor); ?> focus:border-<?php echo e($accentColor); ?> resize-none flex-1 transition-all duration-200"  
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
                                <?php echo e(svg('iconoir-send', 'w-3.5 h-3.5 sm:w-4 sm:h-4 text-white')); ?>
                            </div>
                        </button>
                    </div>
                    <!-- Info footer -->
                    <div class="flex justify-between items-center mt-1 sm:mt-1.5">
                        <div class="text-xs text-gray-500">
                            <span id="input-time"><?php echo e(now()->format('H:i')); ?></span> - <?php echo e(Str::limit($currentUser['name'] ?? 'Usuário', 8)); ?>

                        </div>
                        <div class="text-xs text-gray-400 hidden lg:block">
                            <span x-show="!isSendingMessage()">Enter = enviar</span>
                            <span x-show="isSendingMessage()">Enviando...</span>
                        </div>
                    </div>
                </form>
            </div>
        <?php else: ?>
            <!-- Read-only footer -->
            <div class="flex-shrink-0 border-t border-gray-200 bg-gray-100 p-1.5">
                <p class="text-center text-xs text-gray-600 flex items-center justify-center space-x-1">
                    <svg class="w-3 h-3" fill="currentColor" viewBox="0 0 20 20">
                        <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                    </svg>
                    <span><?php echo e($viewingHistory ? 'Histórico' : 'Encerrado'); ?></span>
                </p>
            </div>
        <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
    <?php endif; ?><!--[if ENDBLOCK]><![endif]-->
</div><?php /**PATH /var/www/passagem-plantao/resources/views/livewire/chat-component.blade.php ENDPATH**/ ?>