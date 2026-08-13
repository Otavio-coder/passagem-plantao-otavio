<div>
    @if($showModal)
        @php $unitNames = array_keys($historyByUnit ?? []); @endphp

        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4"
             x-data="{ activeUnit: 0 }"
             x-on:keydown.escape.window="$wire.closeModal()">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Modal --}}
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden">

                {{-- Header --}}
                <div class="flex-shrink-0 bg-[#004D9D] px-5 py-4 flex items-center justify-between">
                    <h2 class="text-lg font-bold text-white flex items-center gap-2">
                        <i class="fas fa-clock-rotate-left"></i>
                        Histórico de Rounds Unidade
                    </h2>
                    <button wire:click="closeModal" class="text-white/80 hover:text-white text-xl">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                {{-- Seletor de unidade (botão único com setas para alternar) --}}
                @if(count($unitNames) > 1)
                    @php $totalUnits = count($unitNames); @endphp
                    <div class="flex-shrink-0 px-5 pt-3 pb-2 border-b border-gray-100 flex items-center justify-center gap-3">
                        <button @click="activeUnit = (activeUnit - 1 + {{ $totalUnits }}) % {{ $totalUnits }}"
                                class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-[#004D9D] transition-colors"
                                title="Unidade anterior">
                            <i class="fas fa-chevron-left"></i>
                        </button>

                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-[#004D9D] text-white text-sm font-bold shadow-sm min-w-[180px] justify-center">
                            <i class="fas fa-hospital text-[11px]"></i>
                            @foreach($unitNames as $idx => $name)
                                <span x-show="activeUnit === {{ $idx }}" x-cloak>
                                    {{ $name }}
                                    <span class="text-[10px] font-normal opacity-70">({{ count($historyByUnit[$name]) }})</span>
                                </span>
                            @endforeach
                        </div>

                        <button @click="activeUnit = (activeUnit + 1) % {{ $totalUnits }}"
                                class="p-2 rounded-lg hover:bg-gray-100 text-gray-500 hover:text-[#004D9D] transition-colors"
                                title="Próxima unidade">
                            <i class="fas fa-chevron-right"></i>
                        </button>
                    </div>
                @endif

                {{-- Corpo --}}
                <div class="flex-1 overflow-y-auto p-5">
                    @if(empty($historyByUnit))
                        <div class="text-center py-10 text-gray-500">
                            <i class="fas fa-folder-open text-4xl text-gray-300 mb-3"></i>
                            <p class="font-semibold">Nenhum Round Unidade registrado</p>
                            <p class="text-sm mt-1">Os registros aparecerão aqui após o primeiro preenchimento.</p>
                        </div>
                    @else
                        @foreach($historyByUnit as $unitName => $records)
                            <div x-show="activeUnit === {{ $loop->index }}"
                                 x-cloak
                                 x-transition:enter="transition ease-out duration-150"
                                 x-transition:enter-start="opacity-0"
                                 x-transition:enter-end="opacity-100">

                                {{-- Título da unidade (visível quando há uma única unidade) --}}
                                @if(count($unitNames) === 1)
                                    <div class="flex items-center gap-3 mb-4 pb-2 border-b-2 border-[#004D9D]/20">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg bg-[#004D9D] text-white text-sm font-bold shadow-sm">
                                            <i class="fas fa-hospital"></i>
                                            {{ $unitName }}
                                        </span>
                                        <span class="text-xs text-gray-400 font-medium">{{ count($records) }} {{ count($records) === 1 ? 'registro' : 'registros' }}</span>
                                    </div>
                                @endif

                                {{-- Registros da unidade --}}
                                <div class="space-y-3">
                                    @foreach($records as $record)
                                        <details class="group border border-gray-200 rounded-xl overflow-hidden shadow-sm hover:shadow-md transition-shadow">
                                            <summary class="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors select-none">
                                                <div class="flex items-center gap-3 flex-wrap">
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-[#004D9D] text-white text-xs font-bold shadow-sm">
                                                        <i class="fas fa-calendar-day"></i>
                                                        {{ $record['date'] }}
                                                    </span>
                                                    <span class="text-sm text-gray-600 flex items-center gap-1">
                                                        <i class="fas fa-user-check text-gray-400"></i>
                                                        {{ $record['filled_by'] }}
                                                    </span>
                                                    <span class="text-[11px] text-gray-400 hidden sm:inline">
                                                        <i class="fas fa-clock mr-0.5"></i>{{ $record['filled_at'] }}
                                                    </span>
                                                </div>
                                                <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform duration-200"></i>
                                            </summary>

                                            <div class="px-4 py-3 space-y-3 border-t border-gray-100 bg-white">
                                                @php
                                                    $groupedAnswers = collect($record['answers'])->groupBy('axis');
                                                @endphp

                                                @foreach($groupedAnswers as $axis => $answers)
                                                    <div>
                                                        <h4 class="text-[11px] font-bold text-[#004D9D] uppercase tracking-wide mb-1.5 flex items-center gap-1.5">
                                                            <span class="w-1.5 h-1.5 rounded-full bg-[#004D9D] inline-block"></span>
                                                            {{ $axis }}
                                                        </h4>
                                                        <div class="space-y-1 ml-3">
                                                            @foreach($answers as $answer)
                                                                <div class="flex items-start gap-2 text-sm py-0.5">
                                                                    <span class="text-gray-600 flex-1">{{ $answer['label'] }}</span>
                                                                    @php
                                                                        $displayVal = $answer['value'] ?? '—';
                                                                        $valClass = 'text-gray-900';
                                                                        if ($displayVal === 'Sim') $valClass = 'text-red-600';
                                                                        elseif ($displayVal === 'Não') $valClass = 'text-green-700';
                                                                    @endphp
                                                                    <span class="font-semibold shrink-0 {{ $valClass }}">
                                                                        {{ $displayVal }}
                                                                    </span>
                                                                </div>
                                                            @endforeach
                                                        </div>
                                                    </div>
                                                    @if(! $loop->last)
                                                        <hr class="border-gray-100">
                                                    @endif
                                                @endforeach
                                            </div>
                                        </details>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
