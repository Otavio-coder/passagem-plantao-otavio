<div class="w-full my-2 text-[#004D9D] font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">
            <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                {{-- Cabeçalho + filtros --}}
                <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 z-40 shadow-lg">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white text-center lg:text-left">
                            <i class="fas fa-clipboard-check mr-1"></i>
                            Checklist de Pacientes — Consulta
                        </h1>

                        <div class="flex flex-wrap items-center justify-center lg:justify-end gap-2">
                            {{-- Data --}}
                            <input type="date" wire:model.live="selectedDate"
                                   class="rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium px-3 py-2 shadow-sm focus:ring-2 focus:ring-white/60">

                            {{-- Hospital --}}
                            <select wire:model.live="selectedHospital"
                                    class="min-w-[14rem] max-w-full rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium pl-3 pr-8 py-2 shadow-sm focus:ring-2 focus:ring-white/60">
                                <option value="">Todos os hospitais</option>
                                @foreach($hospitals as $hospital)
                                    <option value="{{ $hospital['id'] }}">{{ $hospital['name'] }}</option>
                                @endforeach
                            </select>

                            {{-- Unidade (setor) — aparece quando hospital selecionado --}}
                            @if(! empty($sectors))
                                <select wire:model.live="selectedSector"
                                        class="min-w-[12rem] max-w-full rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium pl-3 pr-8 py-2 shadow-sm focus:ring-2 focus:ring-white/60">
                                    <option value="">Todas as unidades</option>
                                    @foreach($sectors as $sector)
                                        <option value="{{ $sector['id'] }}">{{ $sector['name'] }}</option>
                                    @endforeach
                                </select>
                            @endif

                            {{-- Limpar --}}
                            <button wire:click="clearFilters"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-white bg-white/15 border border-white/25 hover:bg-white/25 shadow-md text-sm font-medium">
                                <i class="fas fa-eraser"></i>
                                <span class="hidden sm:inline">Limpar</span>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-white/70 text-xs">
                        {{ $days->count() }} registro(s)
                        @if($selectedDate) · {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }} @endif
                    </p>
                </div>

                {{-- Corpo --}}
                <div class="p-2 sm:p-3 lg:p-4">
                    @if($days->isEmpty())
                        <div class="bg-white/70 border border-gray-200 rounded-lg px-6 py-10 text-center text-gray-600">
                            <i class="fas fa-folder-open text-4xl text-gray-300 mb-3 block"></i>
                            <p class="font-semibold">Nenhum checklist encontrado</p>
                            <p class="text-sm mt-1">Os registros aparecerão aqui após o preenchimento do checklist de pacientes.</p>
                        </div>
                    @else
                        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wide">
                                        <th class="text-left px-3 py-2 font-bold">Data</th>
                                        <th class="text-left px-3 py-2 font-bold">Hospital</th>
                                        <th class="text-left px-3 py-2 font-bold">Unidade</th>
                                        <th class="text-left px-3 py-2 font-bold">Atendimento</th>
                                        <th class="text-center px-3 py-2 font-bold">Cor</th>
                                        <th class="text-center px-3 py-2 font-bold">Itens</th>
                                        <th class="text-left px-3 py-2 font-bold">Preenchido por</th>
                                        <th class="text-left px-3 py-2 font-bold">Horário</th>
                                        <th class="text-center px-3 py-2 font-bold">Status</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>

                                @foreach($days as $item)
                                    <tbody x-data="{ open: false }" class="border-t border-gray-100">
                                        <tr class="hover:bg-slate-50 cursor-pointer" @click="open = !open">
                                            <td class="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">{{ $item['date'] }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $item['hospital'] }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $item['sector'] }}</td>
                                            <td class="px-3 py-2 text-gray-800 font-medium font-mono text-xs">{{ $item['nr_atendimento'] }}</td>
                                            <td class="px-3 py-2 text-center">
                                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold uppercase text-white {{ $item['color'] === 'green' ? 'bg-green-500' : 'bg-red-500' }}">
                                                    {{ $item['color'] === 'green' ? 'Green' : 'Red' }}
                                                </span>
                                            </td>
                                            <td class="px-3 py-2 text-center">
                                                @if($item['red_count'] > 0)
                                                    <span class="text-red-600 font-semibold">{{ $item['red_count'] }}</span><span class="text-gray-400">/{{ $item['answers_count'] }}</span>
                                                @elseif($item['answers_count'] > 0)
                                                    <span class="text-green-600 font-semibold">{{ $item['answers_count'] }}</span><span class="text-gray-400">/{{ $item['answers_count'] }}</span>
                                                @else
                                                    <span class="text-gray-400">0</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-700">{{ $item['filled_by'] }}</td>
                                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $item['filled_at'] }}</td>
                                            <td class="px-3 py-2 text-center">
                                                @if($item['finalized'])
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-green-100 text-green-700">
                                                        <i class="fas fa-lock text-[8px]"></i> Finalizado
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-700">
                                                        Em aberto
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-right">
                                                <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
                                            </td>
                                        </tr>

                                        {{-- Detalhe expansível: checklist completo --}}
                                        <tr x-show="open" x-cloak>
                                            <td colspan="10" class="px-4 py-3 bg-slate-50">
                                                <div class="space-y-2">
                                                    {{-- Comentários --}}
                                                    @if(! empty($item['comments']))
                                                        <div class="bg-amber-50 border border-amber-200 rounded-lg px-3 py-2 text-sm">
                                                            <span class="font-semibold text-amber-800"><i class="fas fa-comment-dots mr-1"></i>Comentários:</span>
                                                            <span class="text-amber-700">{{ $item['comments'] }}</span>
                                                        </div>
                                                    @endif

                                                    {{-- Itens do checklist --}}
                                                    <div class="grid grid-cols-1 gap-1.5">
                                                        @foreach($checklistItems as $ci)
                                                            @php
                                                                $code = $ci->value;
                                                                $a = $item['answers'][$code] ?? null;
                                                                $signal = $a['signal'] ?? null;
                                                                $answer = $a['answer'] ?? null;
                                                            @endphp
                                                            <div class="flex items-start gap-3 px-3 py-2 rounded-lg border {{ $signal === 'red' ? 'border-red-200 bg-red-50/50' : ($signal === 'green' ? 'border-green-200 bg-green-50/50' : 'border-gray-200 bg-white') }}">
                                                                {{-- Indicador --}}
                                                                <div class="shrink-0 mt-0.5">
                                                                    @if($signal === 'red')
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-red-500 text-white text-[10px]"><i class="fas fa-xmark"></i></span>
                                                                    @elseif($signal === 'green')
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-green-500 text-white text-[10px]"><i class="fas fa-check"></i></span>
                                                                    @else
                                                                        <span class="inline-flex items-center justify-center w-5 h-5 rounded-full bg-gray-300 text-white text-[10px]"><i class="fas fa-minus"></i></span>
                                                                    @endif
                                                                </div>

                                                                {{-- Conteúdo --}}
                                                                <div class="flex-1 min-w-0">
                                                                    <p class="text-sm text-gray-800 font-medium">{{ $ci->label() }}</p>
                                                                    <div class="flex flex-wrap items-center gap-x-4 gap-y-0.5 mt-0.5 text-[11px] text-gray-500">
                                                                        <span>Resposta: <strong class="{{ $signal === 'red' ? 'text-red-600' : ($signal === 'green' ? 'text-green-700' : 'text-gray-600') }}">{{ $answer ? ucfirst($answer) : '—' }}</strong></span>
                                                                        @if($a)
                                                                            <span>Por: <strong>{{ $a['answered_by'] }}</strong></span>
                                                                            <span>{{ $a['answered_at'] ?? '' }}</span>
                                                                        @endif
                                                                    </div>
                                                                    @if(! empty($a['notes']))
                                                                        <p class="mt-1 text-xs text-gray-600 bg-white/80 rounded px-2 py-1 border border-gray-100">
                                                                            <i class="fas fa-pen-to-square text-gray-400 mr-0.5"></i>{{ $a['notes'] }}
                                                                        </p>
                                                                    @endif
                                                                </div>
                                                            </div>
                                                        @endforeach
                                                    </div>

                                                    {{-- Finalização --}}
                                                    @if($item['finalized'])
                                                        <p class="text-[11px] text-green-600 font-medium mt-1">
                                                            <i class="fas fa-lock mr-0.5"></i>Finalizado em {{ $item['finalized_at'] }}
                                                        </p>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    </tbody>
                                @endforeach
                            </table>
                        </div>
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>
