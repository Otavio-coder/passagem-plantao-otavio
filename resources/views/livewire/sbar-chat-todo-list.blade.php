<div
    x-data="{
        open: false,
        panelTop: 0,
        panelLeft: 0,
        toggle(event) {
            const rect = event.currentTarget.getBoundingClientRect();
            const panelW = 304;
            const top = rect.bottom + 6;
            // Prefer opening to the left of the button; clamp within viewport
            let left = rect.right - panelW;
            if (left < 8) left = 8;
            if (left + panelW > window.innerWidth - 8) left = window.innerWidth - panelW - 8;
            this.panelTop  = top;
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
        :style="`position:fixed; top:${panelTop}px; left:${panelLeft}px; z-index:9999; width:304px;`"
        class="bg-white rounded-xl shadow-2xl border border-gray-200 overflow-hidden"
        x-cloak
    >
        {{-- Panel header --}}
        <div class="flex items-center justify-between px-3 py-2.5 bg-gray-50 border-b border-gray-200">
            <div class="flex items-center gap-2 min-w-0">
                <i class="fas fa-list-check text-gray-500 text-xs flex-shrink-0"></i>
                <div class="min-w-0">
                    <p class="text-xs font-semibold text-gray-700 leading-none">Lista de pendências</p>
                    <p class="text-[10px] text-gray-400 leading-none mt-0.5">Não vinculada ao Tasy</p>
                </div>
                @if($pendingCount > 0)
                    <span class="flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded-full
                        {{ $alertCount > 0 ? 'bg-red-100 text-red-700' : 'bg-blue-100 text-blue-700' }}">
                        {{ $pendingCount }}
                    </span>
                @else
                    <span class="flex-shrink-0 text-[10px] font-medium px-1.5 py-0.5 bg-green-100 text-green-700 rounded-full">OK</span>
                @endif
            </div>
            <button @click="open = false" class="flex-shrink-0 w-7 h-7 flex items-center justify-center text-gray-400 hover:text-gray-600 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-xmark text-xs"></i>
            </button>
        </div>

        {{-- Items list --}}
        <div class="overflow-y-auto" style="max-height: 280px;">
            @forelse($todos as $todo)
                <div wire:key="todo-{{ $todo['id'] }}"
                     class="flex items-center gap-2 px-3 py-2.5 border-b border-gray-100 last:border-0
                         {{ $todo['is_done'] ? 'bg-gray-50/80' : ($todo['has_alert'] ? 'bg-red-50/40' : 'bg-white') }}">

                    {{-- Done checkbox --}}
                    <button
                        wire:click="toggleDone({{ $todo['id'] }})"
                        class="flex-shrink-0 w-5 h-5 rounded border-2 flex items-center justify-center transition-colors
                            {{ $todo['is_done'] ? 'bg-green-500 border-green-500 text-white' : 'border-gray-300 hover:border-green-400' }}"
                        title="{{ $todo['is_done'] ? 'Reabrir' : 'Marcar como feito' }}"
                    >
                        @if($todo['is_done'])
                            <i class="fas fa-check text-[9px]"></i>
                        @endif
                    </button>

                    {{-- Content + meta --}}
                    <div class="flex-1 min-w-0">
                        <p class="text-xs leading-snug {{ $todo['is_done'] ? 'line-through text-gray-400' : 'text-gray-800' }}">
                            {{ $todo['content'] }}
                        </p>
                        <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                            @if($todo['shift'])
                                <span class="text-[9px] font-semibold px-1 py-px rounded
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
                            <span class="text-[9px] text-gray-400 truncate">{{ $todo['author'] }}</span>
                        </div>
                    </div>

                    {{-- Actions: always visible, touch-friendly --}}
                    <div class="flex-shrink-0 flex items-center gap-1" x-data="{ confirming: false }">
                        <button
                            wire:click="toggleAlert({{ $todo['id'] }})"
                            x-show="!confirming"
                            class="w-8 h-8 flex items-center justify-center rounded-lg transition-colors
                                {{ $todo['has_alert'] ? 'bg-red-100 text-red-500' : 'text-gray-300 hover:bg-gray-100 hover:text-gray-500' }}"
                            title="{{ $todo['has_alert'] ? 'Remover aviso' : 'Marcar como aviso' }}"
                        >
                            <i class="fas fa-circle-exclamation text-sm"></i>
                        </button>

                        {{-- Delete button → shows inline confirmation --}}
                        <button
                            x-show="!confirming"
                            @click.prevent="confirming = true"
                            class="w-8 h-8 flex items-center justify-center rounded-lg text-gray-300 hover:bg-red-50 hover:text-red-400 transition-colors"
                            title="Excluir"
                        >
                            <i class="fas fa-trash text-sm"></i>
                        </button>

                        {{-- Inline confirmation --}}
                        <div x-show="confirming" x-cloak class="flex items-center gap-1">
                            <button
                                @click="$wire.removeItem({{ $todo['id'] }}); confirming = false"
                                class="text-[10px] font-semibold px-2 h-6 bg-red-500 hover:bg-red-600 text-white rounded transition-colors"
                            >Sim</button>
                            <button
                                @click="confirming = false"
                                class="text-[10px] font-semibold px-2 h-6 border border-gray-300 text-gray-500 hover:bg-gray-100 rounded transition-colors"
                            >Não</button>
                        </div>
                    </div>
                </div>
            @empty
                <div class="flex flex-col items-center justify-center gap-2 py-8 text-center">
                    <i class="fas fa-clipboard-check text-gray-200 text-3xl"></i>
                    <p class="text-xs text-gray-400">Nenhuma pendência registrada</p>
                </div>
            @endforelse
        </div>

        {{-- New item form --}}
        <div class="border-t border-gray-200 p-3 bg-gray-50">
            <form wire:submit="addItem" class="flex flex-col gap-2.5">
                <div class="flex gap-2">
                    <input
                        wire:model.blur="newItem"
                        type="text"
                        placeholder="Nova pendência..."
                        maxlength="200"
                        class="flex-1 text-sm text-black rounded-lg border border-gray-300 px-3 py-2.5 focus:ring-2 focus:ring-blue-500/30 focus:border-blue-400 outline-none placeholder-gray-400"
                    />
                    <button
                        type="submit"
                        class="flex-shrink-0 w-10 h-10 flex items-center justify-center bg-[#004D9D] hover:bg-[#003d7a] text-white rounded-lg transition-colors"
                    >
                        <i class="fas fa-plus"></i>
                    </button>
                </div>

                {{-- Shift picker --}}
                <div class="flex items-center gap-2">
                    <span class="text-[10px] text-gray-500 flex-shrink-0">Turno:</span>
                    <div class="flex gap-1.5 flex-wrap">
                        @foreach(['M' => ['label' => 'Manhã', 'active' => 'bg-amber-100 border-amber-400 text-amber-700'], 'T' => ['label' => 'Tarde', 'active' => 'bg-blue-100 border-blue-400 text-blue-700'], 'N' => ['label' => 'Noite', 'active' => 'bg-indigo-100 border-indigo-400 text-indigo-700']] as $value => $meta)
                            <label class="cursor-pointer">
                                <input type="radio" wire:model.live="newItemShift" value="{{ $value }}" class="sr-only">
                                <span class="inline-block text-[10px] font-medium px-2.5 py-1 rounded-full border transition-colors
                                    {{ $newItemShift === $value ? $meta['active'] : 'border-gray-200 text-gray-400 hover:border-gray-300' }}">
                                    {{ $meta['label'] }}
                                </span>
                            </label>
                        @endforeach
                        <label class="cursor-pointer">
                            <input type="radio" wire:model.live="newItemShift" value="" class="sr-only">
                            <span class="inline-block text-[10px] font-medium px-2.5 py-1 rounded-full border transition-colors
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
