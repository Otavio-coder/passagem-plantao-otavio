<div
    x-data="{
        open: false,
        panelTop: 0,
        panelLeft: 0,
        toggle(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const panelW = Math.min(360, window.innerWidth - 16);
            let left = rect.right - panelW;
            if (left < 8) left = 8;
            if (left + panelW > window.innerWidth - 8) left = window.innerWidth - panelW - 8;
            this.panelTop  = rect.bottom + 6;
            this.panelLeft = left;
            this.open = !this.open;
        }
    }"
    @keydown.escape.window="open = false"
>
    {{-- Trigger button --}}
    @php
        $pendingCount = count(array_filter($todos, fn($t) => !$t['is_done']));
        $alertCount   = count(array_filter($todos, fn($t) => !$t['is_done'] && $t['has_alert']));
    @endphp

    <button
        @click="toggle($event)"
        title="Lista de pendências (não vinculada ao Tasy)"
        :class="open ? 'bg-white/30' : 'bg-white/20 hover:bg-white/30'"
        class="relative inline-flex items-center gap-1.5 px-2.5 h-8 rounded-lg border border-white/30 text-white text-xs font-medium transition-colors"
    >
        <i class="fas fa-list-check text-xs flex-shrink-0"></i>
        <span class="hidden sm:inline leading-none">Pendências</span>
        @if($pendingCount > 0)
            <span class="absolute -top-1 -right-1 min-w-[16px] h-4 px-0.5 rounded-full text-[9px] font-bold flex items-center justify-center
                {{ $alertCount > 0 ? 'bg-red-500 text-white' : 'bg-sky-400 text-white' }}">
                {{ $pendingCount }}
            </span>
        @endif
    </button>

    {{-- Dropdown panel --}}
    <div
        x-show="open"
        @click.outside="open = false"
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100 scale-100"
        x-transition:leave-end="opacity-0 scale-95"
        :style="`position:fixed; top:${panelTop}px; left:${panelLeft}px; z-index:9999; width:${Math.min(360, window.innerWidth - 16)}px;`"
        class="bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden"
        x-cloak
    >
        {{-- Panel header --}}
        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center gap-2 min-w-0">
                <i class="fas fa-list-check text-gray-500 text-xs flex-shrink-0"></i>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-700 leading-none">Lista de pendências</p>
                    <p class="text-[10px] text-gray-400 leading-none mt-0.5">Não vinculada ao Tasy</p>
                </div>
                @if($pendingCount > 0)
                    <span class="flex-shrink-0 text-[10px] font-bold px-2 py-0.5 rounded-full
                        {{ $alertCount > 0 ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $pendingCount }}
                    </span>
                @else
                    <span class="flex-shrink-0 text-[10px] font-medium px-2 py-0.5 bg-green-100 text-green-700 rounded-full">OK</span>
                @endif
            </div>
            <button
                @click="open = false"
                class="flex-shrink-0 w-9 h-9 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-xl hover:bg-gray-100 transition-colors"
                aria-label="Fechar"
            >
                <i class="fas fa-xmark text-sm"></i>
            </button>
        </div>

        {{-- Items list --}}
        <div class="divide-y divide-gray-100" style="max-height: 320px; overflow-y: auto;">
            @forelse($paginatedTodos as $todo)
                <div wire:key="todo-{{ $todo['id'] }}"
                     class="flex items-center gap-3 px-4 py-3
                         {{ $todo['is_done'] ? 'bg-gray-50/80' : ($todo['has_alert'] ? 'bg-red-50/50' : 'bg-white') }}">

                    {{-- Done toggle — 44 px hit target --}}
                    <button
                        wire:click="toggleDone({{ $todo['id'] }})"
                        class="flex-shrink-0 w-11 h-11 rounded-xl border-2 flex items-center justify-center transition-colors
                            {{ $todo['is_done']
                                ? 'bg-green-500 border-green-500 text-white'
                                : 'border-gray-300 bg-white hover:border-green-400 hover:bg-green-50' }}"
                        title="{{ $todo['is_done'] ? 'Reabrir' : 'Marcar como feito' }}"
                    >
                        @if($todo['is_done'])
                            <i class="fas fa-check text-sm"></i>
                        @endif
                    </button>

                    {{-- Content + meta --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-sm leading-snug {{ $todo['is_done'] ? 'line-through text-gray-400' : 'text-gray-800' }}">
                            {{ $todo['content'] }}
                        </p>
                        <div class="flex items-center gap-1.5 mt-1 flex-wrap">
                            @if($todo['shift'])
                                <span class="text-[10px] font-semibold px-1.5 py-px rounded
                                    @switch($todo['shift'])
                                        @case('M') bg-amber-100 text-amber-700 @break
                                        @case('T') bg-blue-100 text-blue-700 @break
                                        @case('N') bg-indigo-100 text-indigo-700 @break
                                        @default bg-gray-100 text-gray-500
                                    @endswitch">
                                    @switch($todo['shift'])
                                        @case('M') Manhã @break
                                        @case('T') Tarde @break
                                        @case('N') Noite @break
                                        @default {{ $todo['shift'] }}
                                    @endswitch
                                </span>
                            @endif
                            <span class="text-[10px] text-gray-400 truncate">{{ $todo['author'] }}</span>
                        </div>
                    </div>

                    {{-- Actions — 44 px touch targets --}}
                    <div class="flex-shrink-0 flex items-center gap-0.5" x-data="{ confirming: false }">
                        {{-- Alert toggle --}}
                        <button
                            wire:click="toggleAlert({{ $todo['id'] }})"
                            x-show="!confirming"
                            class="w-11 h-11 flex items-center justify-center rounded-xl transition-colors
                                {{ $todo['has_alert']
                                    ? 'bg-red-100 text-red-500'
                                    : 'text-gray-300 hover:bg-gray-100 hover:text-gray-500' }}"
                            title="{{ $todo['has_alert'] ? 'Remover aviso' : 'Marcar como aviso' }}"
                        >
                            <i class="fas fa-circle-exclamation text-base"></i>
                        </button>

                        {{-- Delete --}}
                        <button
                            x-show="!confirming"
                            @click.prevent="confirming = true"
                            class="w-11 h-11 flex items-center justify-center rounded-xl text-gray-300 hover:bg-red-50 hover:text-red-400 transition-colors"
                            title="Excluir"
                        >
                            <i class="fas fa-trash text-base"></i>
                        </button>

                        {{-- Inline confirmation --}}
                        <div x-show="confirming" x-cloak class="flex items-center gap-1.5">
                            <button
                                @click="$wire.removeItem({{ $todo['id'] }}); confirming = false"
                                class="h-10 px-3 text-sm font-semibold bg-red-500 hover:bg-red-600 text-white rounded-xl transition-colors"
                            >Sim</button>
                            <button
                                @click="confirming = false"
                                class="h-10 px-3 text-sm font-semibold border border-gray-300 text-gray-600 hover:bg-gray-100 rounded-xl transition-colors"
                            >Não</button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                    <i class="fas fa-clipboard-check text-gray-200 text-4xl"></i>
                    <p class="text-sm text-gray-400">Nenhuma pendência registrada</p>
                </div>
            @endforelse
        </div>

        {{-- Pagination --}}
        @if($totalPages > 1)
            <div class="flex items-center justify-between px-4 py-2.5 bg-gray-50 border-t border-gray-200">
                <button
                    wire:click="prevPage"
                    @disabled($currentPage <= 1)
                    class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    aria-label="Página anterior"
                >
                    <i class="fas fa-chevron-left text-xs"></i>
                </button>

                <span class="text-xs text-gray-500 font-medium">
                    {{ $currentPage }}&nbsp;/&nbsp;{{ $totalPages }}
                    <span class="text-gray-400">({{ $totalCount }} {{ $totalCount === 1 ? 'item' : 'itens' }})</span>
                </span>

                <button
                    wire:click="nextPage"
                    @disabled($currentPage >= $totalPages)
                    class="w-9 h-9 flex items-center justify-center rounded-lg border border-gray-200 text-gray-500 hover:bg-gray-100 disabled:opacity-40 disabled:cursor-not-allowed transition-colors"
                    aria-label="Próxima página"
                >
                    <i class="fas fa-chevron-right text-xs"></i>
                </button>
            </div>
        @endif

        {{-- New item form --}}
        <div class="border-t border-gray-200 p-4 bg-gray-50">
            <form wire:submit="addItem" class="flex flex-col gap-3">
                <div class="flex gap-2">
                    <input
                        wire:model.blur="newItem"
                        type="text"
                        placeholder="Nova pendência..."
                        maxlength="200"
                        class="flex-1 text-sm text-black rounded-xl border border-gray-300 px-3 py-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none placeholder-gray-400"
                    />
                    <button
                        type="submit"
                        class="flex-shrink-0 w-11 h-11 flex items-center justify-center bg-[#004D9D] hover:bg-[#003d7a] text-white rounded-xl transition-colors"
                        title="Adicionar"
                    >
                        <i class="fas fa-plus text-base"></i>
                    </button>
                </div>

                {{-- Shift picker --}}
                <div class="flex items-center gap-2">
                    <span class="text-[11px] text-gray-500 flex-shrink-0">Turno:</span>
                    <div class="flex gap-1.5 flex-wrap">
                        @foreach([
                            'M' => ['label' => 'Manhã',  'active' => 'bg-amber-100 border-amber-400 text-amber-700'],
                            'T' => ['label' => 'Tarde',  'active' => 'bg-blue-100 border-blue-400 text-blue-700'],
                            'N' => ['label' => 'Noite',  'active' => 'bg-indigo-100 border-indigo-400 text-indigo-700'],
                        ] as $value => $meta)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="newItemShift" value="{{ $value }}" class="sr-only">
                                <span class="inline-block text-[11px] font-medium px-3 py-1.5 rounded-full border transition-colors
                                    {{ $newItemShift === $value ? $meta['active'] : 'border-gray-200 text-gray-400 hover:border-gray-300' }}">
                                    {{ $meta['label'] }}
                                </span>
                            </label>
                        @endforeach
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="newItemShift" value="" class="sr-only">
                            <span class="inline-block text-[11px] font-medium px-3 py-1.5 rounded-full border transition-colors
                                {{ $newItemShift === '' ? 'bg-gray-100 border-gray-400 text-gray-700' : 'border-gray-200 text-gray-400 hover:border-gray-300' }}">
                                Qualquer
                            </span>
                        </label>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>
