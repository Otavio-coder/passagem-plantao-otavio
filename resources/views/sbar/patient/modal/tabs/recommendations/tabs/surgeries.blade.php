<div x-show="activeRecomendacaoTab === 'tab-surg'" style="display:none;" class="pt-3">

    <div x-show="surg.items.length === 0" class="bg-white rounded-xl border border-[#7712C7]/20 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-user-doctor text-[#7712C7]/30" style="font-size:28px;"></i>
        <p class="text-sm text-[#7712C7]/70">Nenhuma cirurgia agendada.</p>
    </div>

    <div x-show="surg.items.length > 0">
        <p class="text-[10px] font-bold text-[#7712C7] mb-2">Cirurgias Agendadas</p>
        <div class="space-y-2">
            <template x-for="s in surg.paged" :key="s.id">
                <div class="bg-white rounded-lg border shadow-sm px-3 py-2.5"
                     :class="s.is_urgent ? 'border-red-200 bg-red-50/20' : 'border-[#7712C7]/25 bg-[#7712C7]/[0.06]'">
                    <div class="flex items-start justify-between gap-3">
                        <div class="flex-1 min-w-0">
                            <p class="text-xs font-semibold text-gray-800 leading-snug" x-text="s.name"></p>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded"
                                      :class="s.is_urgent ? 'bg-red-100 text-red-700 ring-1 ring-red-300' : 'bg-[#7712C7]/15 text-[#7712C7] ring-1 ring-[#7712C7]/30'"
                                      x-text="s.carater"></span>
                                <template x-if="s.status">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 ring-1 ring-blue-200"
                                          x-text="s.status"></span>
                                </template>
                                <template x-if="s.sector_name || s.sector_code">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                        <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                        <span x-text="s.sector_name || ('Setor ' + s.sector_code)"></span>
                                    </span>
                                </template>
                                <template x-if="s.sala">
                                    <span class="text-[10px] text-gray-500 font-medium" x-text="s.sala"></span>
                                </template>
                                <template x-if="s.dt">
                                    <span class="text-[10px] font-mono text-gray-400">
                                        <i class="fa-regular fa-calendar mr-0.5"></i><span x-text="s.dt"></span>
                                    </span>
                                </template>
                            </div>
                            <template x-if="s.observacoes || s.observation">
                                <p class="text-[10px] text-gray-500 italic mt-1.5">
                                    Obs: <span x-text="s.observacoes || s.observation"></span>
                                </p>
                            </template>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div x-show="surg.pages > 1" class="flex items-center justify-center gap-1 pt-3">
            <button @click="surg.page = Math.max(1, surg.page-1)" :disabled="surg.page===1"
                    :class="surg.page===1 ? 'opacity-40' : 'hover:bg-gray-100'"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-angle-left" style="font-size:10px;"></i>
            </button>
            <template x-for="(p,i) in pageNums(surg.pages, surg.page)" :key="i">
                <span class="contents">
                    <template x-if="typeof p === 'number'">
                        <button @click="surg.page = p"
                                :class="surg.page===p ? 'bg-[#7712C7] text-white border-[#7712C7]' : 'bg-white text-gray-600 border-gray-200 hover:bg-[#7712C7]/10'"
                                class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors" x-text="p"></button>
                    </template>
                    <template x-if="typeof p === 'string'">
                        <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
                    </template>
                </span>
            </template>
            <button @click="surg.page = Math.min(surg.pages, surg.page+1)" :disabled="surg.page===surg.pages"
                    :class="surg.page===surg.pages ? 'opacity-40' : 'hover:bg-gray-100'"
                    class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors">
                <i class="fa-solid fa-angle-right" style="font-size:10px;"></i>
            </button>
        </div>
    </div>

</div>
