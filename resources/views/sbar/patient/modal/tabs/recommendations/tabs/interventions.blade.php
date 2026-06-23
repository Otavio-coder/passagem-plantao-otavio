<div x-show="activeRecomendacaoTab === 'tab-int'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-amber-700 mb-2">Intervenções</p>

    <div x-show="int.items.length === 0" class="bg-white rounded-lg border border-amber-200 py-8 text-center">
        <p class="text-xs text-amber-700/70">Sem informações de intervenções.</p>
    </div>

    <div x-show="int.items.length > 0" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
        <template x-for="i in int.items" :key="String(i.id ?? i.name ?? 'int') + '-' + String(i.dt_start ?? '')">
              <div class="bg-white rounded-md border border-amber-200 px-2 py-1.5 min-h-auto xl:min-h-[92px] flex flex-col"
                 :class="(i.labels || []).includes('Urgent') ? 'bg-red-50/30 border-red-200' : ''">
                <div class="flex items-start gap-1.5 min-w-0 mb-1">
                    <x-ui.patient-icon name="alert" class="w-3.5 h-3.5 object-contain flex-shrink-0 opacity-75 mt-0.5 text-amber-700" />
                    <div class="flex-1 min-w-0">
                        <p class="text-[13px] font-semibold text-gray-800 leading-snug" x-text="i.name || 'Intervenção não identificada'"></p>
                        <div x-show="(i.labels || []).length > 0" class="flex flex-wrap gap-1 mt-0.5">
                            <template x-for="lbl in (i.labels || [])" :key="String(lbl)">
                                <span class="text-[9px] font-bold px-1 py-0.5 rounded"
                                      :class="lbl === 'Urgent' ? 'bg-red-50 text-red-600 ring-1 ring-red-200'
                                            : lbl === 'PRN'    ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                                            : lbl === 'ACM'    ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200'
                                                               : 'bg-gray-100 text-gray-500'"
                                      x-text="labelPt(lbl)"></span>
                            </template>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] leading-tight text-gray-600">
                    <span x-show="i.prescriber"><span class="font-semibold text-gray-500">Presc:</span> <span x-text="i.prescriber"></span></span>
                    <span x-show="i.assignee"><span class="font-semibold text-gray-500">Resp:</span> <span x-text="i.assignee"></span></span>
                    <span x-show="i.dt_start" class="text-gray-500"><span class="font-semibold">Vig:</span> <span x-text="i.dt_start"></span></span>
                </div>

                <p x-show="i.observation" class="mt-1 text-[11px] text-gray-600 leading-snug line-clamp-4 mt-auto">
                    <span class="font-semibold text-gray-500">Obs:</span> <span x-text="i.observation"></span>
                </p>
            </div>
        </template>
    </div>

</div>{{-- /tab-int --}}
