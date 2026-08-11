<div class="w-full my-2 text-[#004D9D] font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">
            <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                {{-- Cabeçalho + filtros --}}
                <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 z-40 shadow-lg">
                    <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">
                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white text-center lg:text-left">
                            Round Unidade — Consulta
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

                            {{-- Limpar --}}
                            <button wire:click="clearFilters"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-white bg-white/15 border border-white/25 hover:bg-white/25 shadow-md text-sm font-medium">
                                <i class="fas fa-eraser"></i>
                                <span class="hidden sm:inline">Limpar</span>
                            </button>
                        </div>
                    </div>
                    <p class="mt-1 text-white/70 text-xs">
                        {{ $assessments->count() }} registro(s)
                        @if($selectedDate) · {{ \Carbon\Carbon::parse($selectedDate)->format('d/m/Y') }} @endif
                    </p>
                </div>

                {{-- Corpo --}}
                <div class="p-2 sm:p-3 lg:p-4">
                    @if($assessments->isEmpty())
                        <div class="bg-white/70 border border-gray-200 rounded-lg px-6 py-10 text-center text-gray-600">
                            Nenhum Round Unidade encontrado para o filtro selecionado.
                        </div>
                    @else
                        @php
                            $eixo1 = ['expected_discharges' => 'Altas Previstas', 'expected_admissions' => 'Admissões Previstas', 'blocked_beds_isolation' => 'Leitos bloq. (isolamento)', 'blocked_beds_maintenance' => 'Leitos bloq. (manutenção)'];
                            $eixo2b = ['critical_patient_no_bed' => 'Paciente grave sem leito', 'critical_medication_failure' => 'Falha de medicação crítica', 'adverse_event_24h' => 'Evento adverso (24h)', 'physical_chemical_restraint' => 'Contenção física/química', 'barrier_breach' => 'Quebra de barreira'];
                            $eixo2n = ['pressure_injuries' => 'LPP', 'falls' => 'Quedas'];
                            $eixo3b = ['staff_shortage' => 'Déficit de equipe', 'critical_exam_delay' => 'Atraso de exame crítico'];
                            $classColors = ['verde' => 'bg-green-500', 'amarelo' => 'bg-amber-400 text-amber-950', 'vermelho' => 'bg-red-500'];
                        @endphp

                        <div class="overflow-x-auto bg-white rounded-lg border border-gray-200">
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="bg-slate-100 text-slate-700 text-xs uppercase tracking-wide">
                                        <th class="text-left px-3 py-2 font-bold">Data</th>
                                        <th class="text-left px-3 py-2 font-bold">Hospital</th>
                                        <th class="text-left px-3 py-2 font-bold">Setor</th>
                                        <th class="text-left px-3 py-2 font-bold">Classificação</th>
                                        <th class="text-left px-3 py-2 font-bold">Preenchido por</th>
                                        <th class="text-left px-3 py-2 font-bold">Horário</th>
                                        <th class="px-3 py-2"></th>
                                    </tr>
                                </thead>

                                @foreach($assessments as $item)
                                    @php $m = $item['model']; @endphp
                                    <tbody x-data="{ open: false }" class="border-t border-gray-100">
                                        <tr class="hover:bg-slate-50 cursor-pointer" @click="open = !open">
                                            <td class="px-3 py-2 font-semibold text-gray-800 whitespace-nowrap">{{ $item['date'] }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $item['hospital'] }}</td>
                                            <td class="px-3 py-2 text-gray-700">{{ $item['sector'] }}</td>
                                            <td class="px-3 py-2">
                                                @if($item['classification'])
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-bold uppercase text-white {{ $classColors[$item['classification']] ?? 'bg-gray-400' }}">
                                                        {{ ucfirst($item['classification']) }}
                                                    </span>
                                                @else
                                                    <span class="text-gray-400">—</span>
                                                @endif
                                            </td>
                                            <td class="px-3 py-2 text-gray-700">{{ $item['filled_by'] }}</td>
                                            <td class="px-3 py-2 text-gray-500 whitespace-nowrap">{{ $item['filled_at'] }}</td>
                                            <td class="px-3 py-2 text-right">
                                                <i class="fas fa-chevron-down text-gray-400 transition-transform" :class="open && 'rotate-180'"></i>
                                            </td>
                                        </tr>

                                        {{-- Detalhe expansível --}}
                                        <tr x-show="open" x-cloak>
                                            <td colspan="7" class="px-4 py-3 bg-slate-50">
                                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-1 text-[13px]">
                                                    <div class="md:col-span-2 text-[11px] font-bold uppercase tracking-wide text-gray-400 mt-1">Eixo 1 · Ocupação e fluxo</div>
                                                    @foreach($eixo1 as $k => $label)
                                                        <div class="flex justify-between border-b border-gray-100 py-0.5"><span class="text-gray-600">{{ $label }}</span><strong class="text-gray-800">{{ $m->$k ?? '—' }}</strong></div>
                                                    @endforeach

                                                    <div class="md:col-span-2 text-[11px] font-bold uppercase tracking-wide text-gray-400 mt-2">Eixo 2 · Risco clínico e segurança</div>
                                                    @foreach($eixo2b as $k => $label)
                                                        <div class="flex justify-between border-b border-gray-100 py-0.5"><span class="text-gray-600">{{ $label }}</span><strong class="{{ $m->$k ? 'text-red-600' : 'text-gray-800' }}">{{ is_null($m->$k) ? '—' : ($m->$k ? 'Sim' : 'Não') }}</strong></div>
                                                    @endforeach
                                                    @foreach($eixo2n as $k => $label)
                                                        <div class="flex justify-between border-b border-gray-100 py-0.5"><span class="text-gray-600">{{ $label }}</span><strong class="text-gray-800">{{ $m->$k ?? '—' }}</strong></div>
                                                    @endforeach

                                                    <div class="md:col-span-2 text-[11px] font-bold uppercase tracking-wide text-gray-400 mt-2">Eixo 3 · Condições operacionais</div>
                                                    @foreach($eixo3b as $k => $label)
                                                        <div class="flex justify-between border-b border-gray-100 py-0.5"><span class="text-gray-600">{{ $label }}</span><strong class="{{ $m->$k ? 'text-red-600' : 'text-gray-800' }}">{{ is_null($m->$k) ? '—' : ($m->$k ? 'Sim' : 'Não') }}</strong></div>
                                                    @endforeach

                                                    <div class="md:col-span-2 text-[11px] font-bold uppercase tracking-wide text-gray-400 mt-2">Eixo 4 · Classificação</div>
                                                    <div class="md:col-span-2">
                                                        <p class="text-gray-600 mb-0.5"><strong>Justificativa:</strong> {{ $m->justification ?: '—' }}</p>
                                                        <p class="text-gray-600"><strong>Medidas imediatas:</strong> {{ $m->immediate_measures ?: '—' }}</p>
                                                    </div>
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
