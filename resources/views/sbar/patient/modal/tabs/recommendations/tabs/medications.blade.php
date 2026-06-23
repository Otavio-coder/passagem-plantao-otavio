<div x-show="activeRecomendacaoTab === 'tab-med'" style="display:none;" class="pt-4">

    <div class="flex flex-wrap gap-2 mb-3">
        <div class="relative flex-1 min-w-[160px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style="font-size:11px;"></i>
            <input type="search"
                   :value="q"
                   @input.debounce.250ms="setSearch($event.target.value)"
                   placeholder="Buscar medicamento..."
                   class="w-full pl-8 pr-7 py-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-[#BDAD02]/20 focus:border-[#BDAD02] transition-all placeholder-gray-400"
                   style="font-size:16px;">
            <button x-show="q" @click="setSearch('')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
            </button>
        </div>

        <div class="flex items-center gap-1.5 bg-white border border-gray-200 rounded-lg px-3 py-2 flex-shrink-0">
            <i class="fa-regular fa-calendar text-gray-400" style="font-size:11px;"></i>
            <span class="text-[11px] font-bold text-gray-700 whitespace-nowrap">{{ $planDisplayData['date_label'] ?? '' }}</span>
        </div>

        <div class="flex items-center gap-1 flex-shrink-0">
            <button @click="antibioticoF = !antibioticoF; medPage = 1"
                    :class="antibioticoF ? 'bg-[#BDAD02] text-gray-900 border-[#BDAD02]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#BDAD02]'"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all leading-none whitespace-nowrap">
                Antimicrobianos
            </button>
            <button @click="medSortDir = medSortDir === 'asc' ? 'desc' : 'asc'; medPage = 1"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-gray-200 bg-white text-gray-600 hover:border-[#BDAD02] hover:text-[#5C5300] transition-all leading-none whitespace-nowrap">
                Prescrição <span x-text="medSortDir === 'asc' ? '↑' : '↓'"></span>
            </button>
        </div>
    </div>

    <div x-show="filteredMeds.length === 0" class="bg-white rounded-xl border border-gray-200 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-filter-circle-xmark text-gray-200" style="font-size:28px;"></i>
        <p class="text-sm text-gray-400">Nenhum resultado para os filtros.</p>
        <button @click="clearFilters()" class="text-xs font-semibold text-[#5C5300] hover:underline">Limpar filtros</button>
    </div>

    <div x-show="filteredMeds.length > 0">
        {{-- Mini legenda --}}
        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mb-2 px-0.5">
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-emerald-500 inline-block shrink-0"></span>Administrado</span>
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-sky-400 inline-block shrink-0"></span>Conferido</span>
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-teal-400 inline-block shrink-0"></span>Coletado</span>
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-red-400 inline-block shrink-0"></span>Recusado</span>
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-orange-300 inline-block shrink-0"></span>Desfeito</span>
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-yellow-100 ring-1 ring-yellow-400 inline-block shrink-0"></span>Reaprazado</span>
            <span class="inline-flex items-center gap-1 text-[10px] text-gray-500"><span class="w-2.5 h-2.5 rounded-sm bg-amber-50 ring-1 ring-amber-300 inline-block shrink-0"></span>Pendente</span>
            <span class="ml-auto text-[10px] text-gray-400" x-text="medFrom + '–' + medTo + ' de ' + filteredMeds.length"></span>
        </div>

        {{-- Grid de cards em 2 colunas --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-1.5">
            <template x-for="med in pagedMeds" :key="med.id">
                <div class="bg-white rounded-lg border shadow-sm px-2.5 py-2 flex flex-col gap-1"
                     :class="{
                         'border-[#BDAD02]/50 bg-[#BDAD02]/[0.04]': med.is_antibiotic,
                         'border-gray-200': !med.is_antibiotic,
                         'opacity-50': med.status === 'suspended'
                     }">

                    {{-- Nome + badges --}}
                    <div class="flex items-start justify-between gap-1.5">
                        <p class="text-[11px] font-semibold text-gray-800 leading-snug flex-1"
                           :class="{ 'line-through text-gray-400': med.status === 'suspended' }"
                           x-text="med.name"></p>
                        <div class="flex items-center gap-1 shrink-0">
                            <span x-show="med.is_antibiotic"
                                  class="text-[9px] font-bold px-1 py-0.5 rounded bg-[#BDAD02]/20 text-[#5C5300] border border-[#BDAD02]/30 leading-none">AMI</span>
                            <span x-show="med.nr_prescricao"
                                  class="text-[9px] text-gray-400 font-mono leading-none"
                                  x-text="'#' + med.nr_prescricao"></span>
                        </div>
                    </div>

                    {{-- Dose / via / frequência --}}
                    <p class="text-[10px] text-gray-500 leading-snug">
                        <span x-show="med.dose" x-text="med.dose" class="font-medium text-gray-700"></span>
                        <span x-show="med.dose && med.route"> · </span>
                        <span x-show="med.route" x-text="med.route"></span>
                        <span x-show="med.frequency">
                            <span x-show="med.dose || med.route"> · </span>
                            <span x-text="frequencyPt(med.frequency)"></span>
                        </span>
                    </p>

                    {{-- Slots de horário --}}
                    <div x-show="timeCols.length > 0" class="flex flex-wrap gap-0.5 pt-1 border-t border-gray-100">
                        <template x-for="slot in medSlots(med.id)" :key="slot.col">
                            <span x-show="slot.status !== null"
                                  class="inline-flex items-center gap-0.5 font-mono text-[9px] font-semibold px-1 py-0.5 rounded leading-none"
                                  :class="{
                                      'bg-emerald-500 text-white':                            slot.status === 'administered',
                                      'bg-sky-400 text-white':                                slot.status === 'conferido',
                                      'bg-teal-400 text-white':                               slot.status === 'coletado',
                                      'bg-red-400 text-white':                                slot.status === 'refused',
                                      'bg-orange-300 text-white':                             slot.status === 'undone',
                                      'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-400': slot.status === 'rescheduled',
                                      'bg-amber-50 text-amber-600 ring-1 ring-amber-300':     slot.status === 'scheduled'
                                  }">
                                <template x-if="slot.status === 'administered'"><i class="fa-solid fa-check" style="font-size:8px;"></i></template>
                                <template x-if="slot.status === 'conferido'"><i class="fa-solid fa-check-double" style="font-size:8px;"></i></template>
                                <template x-if="slot.status === 'coletado'"><i class="fa-solid fa-vial" style="font-size:7px;"></i></template>
                                <template x-if="slot.status === 'refused'"><i class="fa-solid fa-xmark" style="font-size:8px;"></i></template>
                                <template x-if="slot.status === 'undone'"><i class="fa-solid fa-rotate-left" style="font-size:7px;"></i></template>
                                <template x-if="slot.status === 'rescheduled'"><i class="fa-solid fa-clock-rotate-left" style="font-size:7px;"></i></template>
                                <template x-if="slot.status === 'scheduled'"><i class="fa-regular fa-clock" style="font-size:8px;"></i></template>
                                <span x-text="slot.time"></span>
                            </span>
                        </template>
                        <p x-show="!hasAnySlot(med.id) && med.schedule"
                           class="text-[10px] text-gray-400 font-mono"
                           x-text="med.schedule"></p>
                        <p x-show="!hasAnySlot(med.id) && !med.schedule"
                           class="text-[10px] text-gray-300 italic">Sem horário</p>
                    </div>
                </div>
            </template>
        </div>

        {{-- Paginação --}}
        <div x-show="medPages > 1" class="flex items-center justify-center gap-1 pt-3">
            <button @click="medPage = Math.max(1, medPage - 1)" :disabled="medPage === 1"
                    :class="medPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100'"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-600 transition-colors">
                <i class="fa-solid fa-angle-left" style="font-size:10px;"></i>
            </button>
            <template x-for="(p, idx) in pageNums(medPages, medPage)" :key="idx">
                <span class="contents">
                    <template x-if="typeof p === 'number'">
                        <button @click="medPage = p"
                                :class="medPage === p ? 'bg-[#BDAD02] text-gray-900 border-[#BDAD02]' : 'bg-white text-gray-600 border-gray-200 hover:bg-[#BDAD02]/10'"
                                class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors"
                                x-text="p"></button>
                    </template>
                    <template x-if="typeof p === 'string'">
                        <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
                    </template>
                </span>
            </template>
            <button @click="medPage = Math.min(medPages, medPage + 1)" :disabled="medPage === medPages"
                    :class="medPage === medPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100'"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center text-gray-600 transition-colors">
                <i class="fa-solid fa-angle-right" style="font-size:10px;"></i>
            </button>
        </div>
    </div>

</div>{{-- /tab-med --}}
