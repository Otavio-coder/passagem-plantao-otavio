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
            <div class="flex flex-col items-start leading-none">
                <span class="text-[11px] font-bold text-gray-700 whitespace-nowrap">{{ $dateLabel }}</span>
                <span class="text-[10px] font-semibold text-[#5C5300] mt-0.5">Hoje</span>
            </div>
        </div>

        <div class="flex items-center gap-1 flex-shrink-0">
            <button @click="antibioticoF = !antibioticoF; medPage = 1"
                    :class="antibioticoF ? 'bg-[#BDAD02] text-gray-900 border-[#BDAD02]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#BDAD02]'"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border transition-all leading-none whitespace-nowrap">
                Antimicrobianos
            </button>
            <button @click="medSortDir = medSortDir === 'asc' ? 'desc' : 'asc'; medPage = 1"
                    class="px-2.5 py-1.5 rounded-lg text-[11px] font-semibold border border-gray-200 bg-white text-gray-600 hover:border-[#BDAD02] hover:text-[#5C5300] transition-all leading-none whitespace-nowrap">
                Prescricao <span x-text="medSortDir === 'asc' ? '↑' : '↓'"></span>
            </button>
        </div>
    </div>

    <div x-show="filteredMeds.length === 0" class="bg-white rounded-xl border border-gray-200 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-filter-circle-xmark text-gray-200" style="font-size:28px;"></i>
        <p class="text-sm text-gray-400">Nenhum resultado para os filtros.</p>
        <button @click="clearFilters()" class="text-xs font-semibold text-[#5C5300] hover:underline">Limpar filtros</button>
    </div>

    <div x-show="filteredMeds.length > 0" class="block sm:hidden space-y-2">
        <div class="flex items-center justify-between flex-wrap gap-2 mb-1">
            <span class="text-[10px] text-gray-400" x-text="medFrom + '–' + medTo + ' de ' + filteredMeds.length"></span>
        </div>

        <template x-for="(med, idx) in pagedMeds" :key="med.id">
            <div>
                <template x-if="idx === 0 || pagedMeds[idx-1].nr_prescricao !== med.nr_prescricao">
                    <div class="flex items-center gap-2 px-1 pt-2 pb-0.5">
                        <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider" x-text="med.nr_prescricao ? 'Prescricao #' + med.nr_prescricao : 'Sem prescricao'"></span>
                        <div class="flex-1 h-px bg-gray-100"></div>
                    </div>
                </template>

                <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden transition-colors"
                     :class="{
                          'border-[#BDAD02]/60': expandedMed === med.id,
                         'border-[#BDAD02]/60 bg-[#BDAD02]/[0.06]': med.is_antibiotic,
                         'cursor-pointer': med.has_details,
                         'cursor-default': !med.has_details
                     }"
                     @click="if (med.has_details) expandedMed = expandedMed === med.id ? null : med.id">
                    <div class="px-3 pt-3 pb-2">
                        <div class="flex items-start justify-between gap-2 mb-1">
                            <p class="font-semibold text-gray-800 text-sm leading-snug flex-1 min-w-0" :class="{ 'line-through text-gray-400': med.status === 'suspended' }" x-text="med.name"></p>
                            <i x-show="med.has_details"
                               class="fa-solid fa-chevron-down text-gray-300 flex-shrink-0 mt-1 transition-transform"
                               style="font-size:10px;"
                               :class="{ 'rotate-180 !text-[#5C5300]': expandedMed === med.id }"></i>
                        </div>
                        <p class="text-xs text-gray-500 leading-snug mb-1.5">
                            <span x-show="med.dose" x-text="med.dose" class="font-medium text-gray-700"></span>
                            <span x-show="med.dose && med.route"> · </span>
                            <span x-show="med.route" x-text="med.route"></span>
                            <span x-show="med.frequency"><span x-show="med.route || med.dose"> · </span><span x-text="med.frequency" class="font-mono"></span></span>
                        </p>
                    </div>

                    <div x-show="timeCols.length > 0" class="flex flex-wrap gap-1 px-3 py-2 border-t border-gray-100 bg-gray-50/60">
                        <template x-for="slot in medSlots(med.id)" :key="slot.col">
                            <span x-show="slot.status !== null"
                                  class="inline-flex items-center gap-0.5 font-mono text-[11px] font-semibold px-2 py-1 rounded-lg leading-none"
                                  :class="{
                                      'bg-emerald-500 text-white':                            slot.status === 'administered',
                                      'bg-sky-400 text-white':                                slot.status === 'conferido',
                                      'bg-teal-400 text-white':                               slot.status === 'coletado',
                                      'bg-red-400 text-white':                                slot.status === 'refused',
                                      'bg-orange-300 text-white':                             slot.status === 'undone',
                                      'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-400': slot.status === 'rescheduled',
                                      'bg-amber-50 text-amber-600 ring-1 ring-amber-300':     slot.status === 'scheduled'
                                  }">
                                <template x-if="slot.status === 'administered'">
                                    <i class="fa-solid fa-check" style="font-size:9px;"></i>
                                </template>
                                <template x-if="slot.status === 'conferido'">
                                    <i class="fa-solid fa-check-double" style="font-size:9px;"></i>
                                </template>
                                <template x-if="slot.status === 'coletado'">
                                    <i class="fa-solid fa-vial" style="font-size:8px;"></i>
                                </template>
                                <template x-if="slot.status === 'refused'">
                                    <i class="fa-solid fa-xmark" style="font-size:9px;"></i>
                                </template>
                                <template x-if="slot.status === 'undone'">
                                    <i class="fa-solid fa-rotate-left" style="font-size:8px;"></i>
                                </template>
                                <template x-if="slot.status === 'rescheduled'">
                                    <i class="fa-solid fa-clock-rotate-left" style="font-size:8px;"></i>
                                </template>
                                <template x-if="slot.status === 'scheduled'">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                </template>
                                <span x-text="slot.time"></span>
                                <span class="font-sans font-medium" x-text="' - ' + statusPt(slot.status)"></span>
                            </span>
                        </template>
                    </div>
                </div>
            </div>
        </template>

        <div x-show="medPages > 1" class="flex items-center justify-center gap-2 pt-1">
            <button @click="medPage = Math.max(1, medPage - 1)" :disabled="medPage === 1" :class="medPage === 1 ? 'opacity-40' : 'active:bg-gray-200'" class="w-10 h-10 rounded-xl border border-gray-200 bg-white flex items-center justify-center text-gray-600 transition-colors">
                <i class="fa-solid fa-angle-left" style="font-size:12px;"></i>
            </button>
            <span class="text-xs text-gray-500 font-semibold" x-text="medPage + ' / ' + medPages"></span>
            <button @click="medPage = Math.min(medPages, medPage + 1)" :disabled="medPage === medPages" :class="medPage === medPages ? 'opacity-40' : 'active:bg-gray-200'" class="w-10 h-10 rounded-xl border border-gray-200 bg-white flex items-center justify-center text-gray-600 transition-colors">
                <i class="fa-solid fa-angle-right" style="font-size:12px;"></i>
            </button>
        </div>
    </div>{{-- /mobile cards --}}

    <div x-show="filteredMeds.length > 0" class="hidden sm:block">
        <div class="flex items-center gap-3 mb-2 flex-wrap">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Status Administracao</span>
            <span class="ml-auto text-[10px] text-gray-400" x-text="medFrom + '–' + medTo + ' de ' + filteredMeds.length + ' itens'"></span>
        </div>

        <div class="bg-white rounded-xl border border-gray-200 shadow-sm overflow-hidden divide-y divide-gray-100">
            <template x-for="(med, idx) in pagedMeds" :key="med.id">
                <div>
                    <template x-if="idx === 0 || pagedMeds[idx-1].nr_prescricao !== med.nr_prescricao">
                        <div class="px-4 py-1 bg-gray-50 border-b border-gray-100">
                            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider" x-text="med.nr_prescricao ? 'Prescricao #' + med.nr_prescricao : 'Sem prescricao'"></span>
                        </div>
                    </template>

                    <div class="px-3 py-2.5 transition-colors"
                         :class="{
                             'opacity-50':               med.status === 'suspended',
                             'hover:bg-[#BDAD02]/[0.08]':expandedMed !== med.id && med.has_details,
                             'bg-[#BDAD02]/[0.12]':      expandedMed === med.id,
                             'border border-[#BDAD02]/40 bg-[#BDAD02]/[0.06]': med.is_antibiotic,
                             'cursor-pointer': med.has_details,
                             'cursor-default': !med.has_details
                         }"
                         @click="if (med.has_details) expandedMed = expandedMed === med.id ? null : med.id">

                        <div class="grid grid-cols-1 xl:grid-cols-[280px,1fr] gap-3">
                            <div class="min-w-0">
                                <div class="flex items-start justify-between gap-1 mb-0.5">
                                    <p class="font-semibold text-gray-800 text-[12px] leading-snug" :class="{ 'line-through text-gray-400': med.status === 'suspended' }" x-text="med.name"></p>
                                    <i class="fa-solid fa-chevron-down text-gray-300 flex-shrink-0 mt-0.5 transition-transform" style="font-size:9px;" x-show="med.has_details" :class="{ 'rotate-180 !text-[#5C5300]': expandedMed === med.id }"></i>
                                </div>

                                <p class="text-[11px] text-gray-500 leading-snug mb-1">
                                    <span x-show="med.dose" x-text="med.dose" class="font-medium text-gray-700"></span>
                                    <span x-show="med.dose && med.route"> · </span>
                                    <span x-show="med.route" x-text="med.route"></span>
                                    <span x-show="med.frequency" class="font-mono"><span x-show="med.route || med.dose"> · </span><span x-text="med.frequency"></span></span>
                                </p>
                            </div>

                            <div class="min-w-0">
                                <div class="flex flex-wrap gap-1.5">
                                    <template x-for="slot in medSlots(med.id)" :key="slot.col">
                                        <span x-show="slot.status !== null"
                                              class="inline-flex items-center gap-1 font-mono text-[11px] font-semibold px-2 py-1 rounded-lg leading-none"
                                              :class="{
                                                  'bg-emerald-500 text-white':                            slot.status === 'administered',
                                                  'bg-sky-400 text-white':                                slot.status === 'conferido',
                                                  'bg-teal-400 text-white':                               slot.status === 'coletado',
                                                  'bg-red-400 text-white':                                slot.status === 'refused',
                                                  'bg-orange-300 text-white':                             slot.status === 'undone',
                                                  'bg-yellow-100 text-yellow-700 ring-1 ring-yellow-400': slot.status === 'rescheduled',
                                                  'bg-amber-50 text-amber-700 ring-1 ring-amber-300':     slot.status === 'scheduled'
                                              }">
                                            <template x-if="slot.status === 'administered'">
                                                <i class="fa-solid fa-check" style="font-size:9px;"></i>
                                            </template>
                                            <template x-if="slot.status === 'conferido'">
                                                <i class="fa-solid fa-check-double" style="font-size:9px;"></i>
                                            </template>
                                            <template x-if="slot.status === 'coletado'">
                                                <i class="fa-solid fa-vial" style="font-size:8px;"></i>
                                            </template>
                                            <template x-if="slot.status === 'refused'">
                                                <i class="fa-solid fa-xmark" style="font-size:9px;"></i>
                                            </template>
                                            <template x-if="slot.status === 'undone'">
                                                <i class="fa-solid fa-rotate-left" style="font-size:8px;"></i>
                                            </template>
                                            <template x-if="slot.status === 'rescheduled'">
                                                <i class="fa-solid fa-clock-rotate-left" style="font-size:8px;"></i>
                                            </template>
                                            <template x-if="slot.status === 'scheduled'">
                                                <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                            </template>
                                            <span x-text="slot.time"></span>
                                            <span class="font-sans font-medium" x-text="' - ' + statusPt(slot.status)"></span>
                                        </span>
                                    </template>
                                </div>

                                <p x-show="!hasAnySlot(med.id) && med.schedule" class="text-[11px] text-gray-400 font-mono mt-1" x-text="med.schedule"></p>
                                <p x-show="!hasAnySlot(med.id) && !med.schedule" class="text-[11px] text-gray-300 italic mt-1">Sem horario para o dia selecionado</p>
                            </div>
                        </div>
                    </div>
                </div>
            </template>

            <div x-show="medPages > 1" class="px-4 py-2.5 border-t border-gray-100 flex items-center justify-between gap-2">
                <span class="text-[11px] text-gray-400" x-text="medFrom + '–' + medTo + ' de ' + filteredMeds.length"></span>
                <div class="flex items-center gap-1">
                    <button @click="medPage = Math.max(1, medPage - 1)" :disabled="medPage === 1" :class="medPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100'" class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-600 transition-colors">
                        <i class="fa-solid fa-angle-left" style="font-size:10px;"></i>
                    </button>
                    <template x-for="(p, idx) in pageNums(medPages, medPage)" :key="idx">
                        <template x-if="typeof p === 'number'">
                            <button @click="medPage = p"
                                    :class="medPage === p ? 'bg-[#BDAD02] text-gray-900 border-[#BDAD02]' : 'bg-white text-gray-600 border-gray-200 hover:bg-[#BDAD02]/10'"
                                    class="w-7 h-7 rounded-lg border text-[11px] font-bold transition-colors"
                                    x-text="p"></button>
                        </template>
                        <template x-if="typeof p === 'string'">
                            <span class="w-7 text-center text-gray-400 text-sm leading-7">…</span>
                        </template>
                    </template>
                    <button @click="medPage = Math.min(medPages, medPage + 1)" :disabled="medPage === medPages" :class="medPage === medPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-gray-100'" class="w-7 h-7 rounded-lg border border-gray-200 flex items-center justify-center text-gray-600 transition-colors">
                        <i class="fa-solid fa-angle-right" style="font-size:10px;"></i>
                    </button>
                </div>
            </div>
        </div>
    </div>{{-- /desktop MAR --}}

</div>{{-- /tab-med --}}
