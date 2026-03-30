<div x-show="activeRecomendacaoTab === 'tab-rec'" style="display:none;" class="pt-3">

    {{-- Toolbar --}}
    <div class="flex flex-wrap gap-2 mb-3">
        <div class="relative flex-1 min-w-[140px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style="font-size:11px;"></i>
            <input type="search" :value="ord.q" @input.debounce.250ms="ord.setQ($event.target.value)"
                   placeholder="Buscar recomendação..."
                   class="w-full pl-8 pr-7 py-2 text-xs rounded-lg border border-gray-200 bg-white
                          focus:outline-none focus:ring-2 focus:ring-[#004D9D]/20 focus:border-[#004D9D]
                          transition-all placeholder-gray-400" style="font-size:16px;">
            <button x-show="ord.q" @click="ord.setQ('')"
                    class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
            </button>
        </div>
        <div x-show="ordTypes.length > 0" class="flex items-center gap-1 flex-shrink-0">
            <button @click="ord.setExtra('all')"
                    :class="ord.extra === 'all' ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap">Todos</button>
            <template x-for="tp in ordTypes" :key="tp">
                <button @click="ord.setExtra(tp)"
                        :class="ord.extra === tp ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'"
                        class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap"
                        x-text="tp"></button>
            </template>
        </div>
    </div>

    {{-- No filter results --}}
    <div x-show="{{ $ordCount }} > 0 && ord.filtered.length === 0"
         class="bg-white rounded-xl border border-gray-200 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-filter-circle-xmark text-gray-200" style="font-size:28px;"></i>
        <p class="text-sm text-gray-400">Nenhum resultado.</p>
        <button @click="ord.clear()" class="text-xs font-semibold text-[#004D9D] hover:underline">Limpar filtros</button>
    </div>

    {{-- List --}}
    <div x-show="ord.filtered.length > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] text-gray-400"
                  x-text="ord.from + '–' + ord.to + ' de ' + ord.filtered.length + ' recomendação(ões)'"></span>
        </div>
        <template x-for="o in ord.paged" :key="(o.text || '') + (o.schedule || '')">
            <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5 shadow-sm transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <p class="text-xs font-semibold text-gray-800 leading-snug"
                           x-text="o.text || 'Recomendação não especificada'"></p>

                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="o.type">
                                <span class="text-[10px] font-bold text-violet-700 bg-violet-50 border border-violet-200 px-1.5 py-0.5 rounded"
                                      x-text="o.type"></span>
                            </template>
                            <template x-for="(slot, slotIdx) in (o.schedule || '').split(' ').filter(s => s)" :key="'ord-' + String(o.id ?? o.text ?? 'x') + '-' + String(slot) + '-' + String(slotIdx)">
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                    <span x-text="slot"></span>
                                </span>
                            </template>
                        </div>

                        <p x-show="o.observation"
                           class="text-[10px] text-gray-500 mt-1.5 pl-2 border-l-2 border-violet-200 leading-snug"
                           x-text="o.observation"></p>

                        <div class="flex flex-wrap items-center gap-x-3 gap-y-0.5 mt-1.5">
                            <p x-show="o.prescriber" class="text-[10px] text-gray-400">
                                <i class="fa-solid fa-user-doctor mr-0.5"></i><span x-text="o.prescriber"></span>
                            </p>
                            <p x-show="o.dt_start" class="text-[10px] text-gray-400">
                                <i class="fa-regular fa-calendar mr-0.5"></i>
                                <span x-text="o.dt_start"></span>
                                <template x-if="o.dt_end"><span x-text="' → ' + o.dt_end"></span></template>
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </div>

    {{-- Pagination --}}
    <div x-show="ord.pages > 1" class="flex items-center justify-center gap-1 pt-3">
        <button @click="ord.page = Math.max(1, ord.page-1)" :disabled="ord.page===1"
                :class="ord.page===1 ? 'opacity-40' : 'hover:bg-gray-100'"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-angle-left" style="font-size:10px;"></i>
        </button>
        <template x-for="(pg,idx) in pageNums(ord.pages, ord.page)" :key="idx">
            <template x-if="typeof pg === 'number'">
                <button @click="ord.page = pg"
                        :class="ord.page===pg ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:bg-gray-50'"
                        class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors" x-text="pg"></button>
            </template>
            <template x-if="typeof pg === 'string'">
                <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
            </template>
        </template>
        <button @click="ord.page = Math.min(ord.pages, ord.page+1)" :disabled="ord.page===ord.pages"
                :class="ord.page===ord.pages ? 'opacity-40' : 'hover:bg-gray-100'"
                class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
            <i class="fa-solid fa-angle-right" style="font-size:10px;"></i>
        </button>
    </div>

</div>{{-- /tab-rec --}}
