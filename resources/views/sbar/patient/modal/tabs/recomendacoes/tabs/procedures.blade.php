<div x-show="activeRecomendacaoTab === 'tab-proc'" style="display:none;" class="pt-3">

    {{-- Procedimentos header + search --}}
    @if(($planDisplayData['counts']['tab-proc'] ?? 0) > 0)
    <p class="text-[10px] font-bold text-indigo-700 uppercase tracking-wider mb-2
               mt-0">Procedimentos</p>
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <div class="relative flex-1 min-w-[160px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style="font-size:11px;"></i>
                 <input type="search" :value="proc.q" @input.debounce.250ms="proc.setQ($event.target.value)"
                     placeholder="Buscar procedimento..."
                   class="w-full pl-8 pr-7 py-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500 transition-all placeholder-gray-400" style="font-size:16px;">
            <button x-show="proc.q" @click="proc.setQ('')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
            </button>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            <button @click="proc.setExtra('today'); proc.page = 1"
                    :class="proc.extra === 'today' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-500'"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap">Hoje</button>
            <button @click="proc.setExtra('all'); proc.page = 1"
                    :class="proc.extra === 'all' ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:border-indigo-500'"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap">Todas Pendências</button>
        </div>
        <div x-show="proc.extra === 'all'" class="flex items-center gap-2 flex-shrink-0">
            <label class="text-[10px] text-gray-500 font-semibold">Tipo</label>
            <select x-model="procType" @change="proc.page = 1"
                    class="px-2 py-1.5 text-[11px] rounded-lg border border-gray-200 bg-white text-gray-700 focus:outline-none focus:ring-2 focus:ring-indigo-200 focus:border-indigo-500">
                <option value="all">Todos</option>
                <template x-for="t in procTypes" :key="t">
                    <option :value="t" x-text="t"></option>
                </template>
            </select>
        </div>
    </div>
    @endif

    {{-- No search results --}}
    <div x-show="{{ (int) ($planDisplayData['counts']['tab-proc'] ?? 0) }} > 0 && procFiltered.length === 0"
         class="bg-white rounded-xl border border-gray-200 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-filter-circle-xmark text-gray-200" style="font-size:28px;"></i>
        <p class="text-sm text-gray-400">Nenhum resultado no período selecionado.</p>
        <div class="flex items-center gap-2">
            <button @click="clearProcFilters()" class="text-xs font-semibold text-indigo-700 hover:underline">Limpar filtros</button>
            <button @click="proc.setExtra('all'); procType = 'all'; proc.setQ(''); proc.page = 1" class="text-xs font-semibold text-gray-500 hover:underline">Mostrar todas</button>
        </div>
    </div>

    {{-- List --}}
    <div x-show="procFiltered.length > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-gray-400" x-text="procFrom + '–' + procTo + ' de ' + procFiltered.length + ' item(s)'"></span>
        </div>
        <template x-for="(p, idx) in procPaged"
                  :key="(p.origem || 'PROC') + '-' + String(p.id ?? 'noid') + '-' + String(p.scheduled_raw || p.scheduled || '') + '-' + String(idx)">
            <div class="bg-white rounded-lg border border-indigo-200 px-3 py-2.5 shadow-sm transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-baseline gap-1.5">
                            <p class="text-xs font-semibold text-gray-800 leading-snug"
                               x-text="p.name || 'Procedimento não identificado'"></p>
                            <template x-if="p.origem === 'AGENDAMENTO'">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-orange-50 text-orange-700 ring-1 ring-orange-200 leading-none">Cirurgia</span>
                            </template>
                            <span x-show="p.type" class="text-[10px] text-gray-500 font-medium leading-none" x-text="p.type"></span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1">
                            <span x-show="p.sector_name || p.sector_code" class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                <span x-text="p.sector_name || ('Setor ' + p.sector_code)"></span>
                            </span>
                            <span x-show="p.scheduled" class="text-[10px] font-mono text-gray-400">
                                <i class="fa-regular fa-clock mr-0.5"></i><span x-text="p.scheduled"></span>
                            </span>
                        </div>
                        <p x-show="p.prescriber" class="text-[10px] text-gray-400 mt-0.5">
                            <i class="fa-regular fa-user mr-1"></i><span x-text="p.prescriber"></span>
                        </p>
                        <p x-show="p.resultado_laudo" class="text-[10px] text-emerald-700 font-semibold mt-0.5">
                            <i class="fa-solid fa-flask mr-1 opacity-70"></i><span x-text="p.resultado_laudo"></span>
                        </p>
                        <span x-show="p.foi_executado_sem_baixa"
                              class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                            <i class="fa-solid fa-triangle-exclamation" style="font-size:9px;"></i>
                            Executado sem baixa
                        </span>
                        <span x-show="p.proc_realizado_em_nova_prescricao"
                              class="mt-1 inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 ring-1 ring-sky-200">
                            <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:9px;"></i>
                            Realizado em solicitação mais recente
                        </span>
                        <span x-show="p.motivo_pendente"
                              class="mt-1 inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-gray-50 text-gray-600 ring-1 ring-gray-200">
                            <i class="fa-solid fa-circle-info" style="font-size:9px;"></i>
                            <span x-text="p.motivo_pendente"></span>
                        </span>
                    </div>
                    <span class="flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded"
                          :class="procBadge(p.status)"
                          x-text="procStatusPt(p.status)"></span>
                </div>
            </div>
        </template>
    </div>

    {{-- Pagination --}}
    <div x-show="procPages > 1" class="flex items-center justify-center gap-1 pt-3">
        <button @click="proc.page = Math.max(1, proc.page-1)" :disabled="proc.page===1"
                :class="proc.page===1 ? 'opacity-40' : 'hover:bg-gray-100'"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-angle-left" style="font-size:10px;"></i>
        </button>
        <template x-for="(p,i) in pageNums(procPages, proc.page)" :key="i">
            <template x-if="typeof p === 'number'">
                <button @click="proc.page = p" :class="proc.page===p ? 'bg-indigo-600 text-white border-indigo-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-indigo-50'"
                        class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors" x-text="p"></button>
            </template>
            <template x-if="typeof p === 'string'">
                <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
            </template>
        </template>
        <button @click="proc.page = Math.min(procPages, proc.page+1)" :disabled="proc.page===procPages"
                :class="proc.page===procPages ? 'opacity-40' : 'hover:bg-gray-100'"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-angle-right" style="font-size:10px;"></i>
        </button>
    </div>

</div>{{-- /tab-proc --}}
