<div x-show="activeRecomendacaoTab === 'tab-int'" style="display:none;" class="pt-3">

    {{-- Toolbar --}}
    <div class="flex flex-wrap gap-2 mb-3">
        <div class="relative flex-1 min-w-[140px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style="font-size:11px;"></i>
            <input type="search" :value="int.q" @input.debounce.250ms="int.setQ($event.target.value)"
                   placeholder="Buscar intervenção..."
                   class="w-full pl-8 pr-7 py-2 text-xs rounded-lg border border-gray-200 bg-white
                          focus:outline-none focus:ring-2 focus:ring-[#004D9D]/20 focus:border-[#004D9D]
                          transition-all placeholder-gray-400" style="font-size:16px;">
            <button x-show="int.q" @click="int.setQ('')"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
            </button>
        </div>
        <div class="flex items-center gap-1 flex-shrink-0">
            @foreach(['all' => 'Todos', 'urgent' => 'Urgentes', 'normal' => 'Outros'] as $v => $l)
            <button @click="int.setExtra('{{ $v }}')"
                    :class="int.extra === '{{ $v }}' ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap">{{ $l }}</button>
            @endforeach
        </div>
    </div>

    {{-- No filter results --}}
    <div x-show="{{ $intCount }} > 0 && int.filtered.length === 0"
         class="bg-white rounded-xl border border-gray-200 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-filter-circle-xmark text-gray-200" style="font-size:28px;"></i>
        <p class="text-sm text-gray-400">Nenhum resultado.</p>
        <button @click="int.clear()" class="text-xs font-semibold text-[#004D9D] hover:underline">Limpar filtros</button>
    </div>

    {{-- List --}}
    <div x-show="int.filtered.length > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-gray-400"
                  x-text="int.from + '–' + int.to + ' de ' + int.filtered.length + ' intervenção(ões)'"></span>
        </div>
        <template x-for="i in int.paged" :key="i.name + (i.schedule || '')">
            <div class="bg-white rounded-lg border shadow-sm px-3 py-2.5 transition-colors"
                 :class="(i.labels || []).includes('Urgent') ? 'border-red-200 bg-red-50/20' : 'border-gray-200'">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 leading-snug"
                           x-text="i.name || 'Intervenção não identificada'"></p>

                        <div x-show="(i.labels || []).length > 0" class="flex flex-wrap gap-1 mt-1">
                            <template x-for="lbl in (i.labels || [])" :key="lbl">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                      :class="lbl === 'Urgent' ? 'bg-red-50 text-red-600 ring-1 ring-red-200'
                                            : lbl === 'PRN'    ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                                            : lbl === 'ACM'    ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200'
                                                               : 'bg-gray-100 text-gray-500'"
                                      x-text="labelPt(lbl)"></span>
                            </template>
                        </div>

                        <div x-show="i.schedule" class="flex flex-wrap gap-1 mt-1.5">
                            <template x-for="(slot, slotIdx) in (i.schedule || '').split(' ').filter(s => s)" :key="'int-' + String(i.id ?? i.name ?? 'x') + '-' + String(slot) + '-' + String(slotIdx)">
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                    <span x-text="slot"></span>
                                </span>
                            </template>
                        </div>

                        <p x-show="i.observation" class="text-[10px] text-gray-500 mt-1.5 pl-2 border-l-2 border-gray-200 leading-snug" x-text="i.observation"></p>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                            <p x-show="i.prescriber" class="text-[10px] text-gray-400">
                                <i class="fa-solid fa-user-doctor mr-0.5"></i><span x-text="i.prescriber"></span>
                            </p>
                            <p x-show="i.assignee" class="text-[10px] text-gray-400">
                                <i class="fa-solid fa-user-nurse mr-1"></i><span x-text="i.assignee"></span>
                            </p>
                            <p x-show="i.dt_start" class="text-[10px] text-gray-400">
                                <i class="fa-regular fa-calendar mr-1"></i>
                                <span x-text="i.dt_start"></span>
                                <template x-if="i.dt_end"><span x-text="' → ' + i.dt_end"></span></template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Pagination --}}
    <div x-show="int.pages > 1" class="flex items-center justify-center gap-1 pt-3">
        <button @click="int.page = Math.max(1, int.page-1)" :disabled="int.page===1"
                :class="int.page===1 ? 'opacity-40' : 'hover:bg-gray-100'"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-angle-left" style="font-size:10px;"></i>
        </button>
        <template x-for="(pg,idx) in pageNums(int.pages, int.page)" :key="idx">
            <template x-if="typeof pg === 'number'">
                <button @click="int.page = pg"
                        :class="int.page===pg ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors" x-text="pg"></button>
            </template>
            <template x-if="typeof pg === 'string'">
                <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
            </template>
        </template>
        <button @click="int.page = Math.min(int.pages, int.page+1)" :disabled="int.page===int.pages"
                :class="int.page===int.pages ? 'opacity-40' : 'hover:bg-gray-100'"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-angle-right" style="font-size:10px;"></i>
        </button>
    </div>

</div>{{-- /tab-int --}}
