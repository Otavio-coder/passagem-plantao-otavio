<div>
    @if($showModal)
        <div class="fixed inset-0 z-[70] flex items-center justify-center p-4" x-data x-on:keydown.escape.window="$wire.closeModal()">
            {{-- Overlay --}}
            <div class="absolute inset-0 bg-black/50" wire:click="closeModal"></div>

            {{-- Modal --}}
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-3xl max-h-[85vh] flex flex-col overflow-hidden">

                {{-- Header --}}
                <div class="flex-shrink-0 bg-[#004D9D] px-5 py-4 flex items-center justify-between">
                    <div>
                        <h2 class="text-lg font-bold text-white flex items-center gap-2">
                            <i class="fas fa-clock-rotate-left"></i>
                            Histórico de Rounds Unidade
                        </h2>
                        @if($sectorLabel)
                            <p class="text-white/70 text-sm mt-0.5">{{ $sectorLabel }}</p>
                        @endif
                    </div>
                    <button wire:click="closeModal" class="text-white/80 hover:text-white text-xl">
                        <i class="fas fa-xmark"></i>
                    </button>
                </div>

                {{-- Corpo --}}
                <div class="flex-1 overflow-y-auto p-5 space-y-4">
                    @if(empty($history))
                        <div class="text-center py-10 text-gray-500">
                            <i class="fas fa-folder-open text-4xl text-gray-300 mb-3"></i>
                            <p class="font-semibold">Nenhum Round Unidade registrado</p>
                            <p class="text-sm mt-1">Os registros aparecerão aqui após o primeiro preenchimento.</p>
                        </div>
                    @else
                        @foreach($history as $record)
                            <details class="group border border-gray-200 rounded-xl overflow-hidden" @if($loop->first) open @endif>
                                <summary class="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors">
                                    <div class="flex items-center gap-3">
                                        <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#004D9D] text-white text-sm font-bold">
                                            <i class="fas fa-calendar-day"></i>
                                            {{ $record['date'] }}
                                        </span>
                                        <span class="text-sm text-gray-600">
                                            <i class="fas fa-user-check text-gray-400 mr-1"></i>
                                            {{ $record['filled_by'] }}
                                        </span>
                                        <span class="text-xs text-gray-400">
                                            {{ $record['filled_at'] }}
                                        </span>
                                    </div>
                                    <i class="fas fa-chevron-down text-gray-400 group-open:rotate-180 transition-transform"></i>
                                </summary>

                                <div class="px-4 py-3 space-y-3">
                                    @php
                                        $groupedAnswers = collect($record['answers'])->groupBy('axis');
                                    @endphp

                                    @foreach($groupedAnswers as $axis => $answers)
                                        <div>
                                            <h4 class="text-xs font-bold text-gray-500 uppercase tracking-wide mb-1.5">{{ $axis }}</h4>
                                            <div class="space-y-1">
                                                @foreach($answers as $answer)
                                                    <div class="flex items-start gap-2 text-sm">
                                                        <span class="text-gray-600 flex-1">{{ $answer['label'] }}</span>
                                                        <span class="font-semibold text-gray-900 shrink-0">
                                                            {{ $answer['value'] ?? '—' }}
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
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
