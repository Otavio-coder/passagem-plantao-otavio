<div x-show="activeRecomendacaoTab === 'tab-int'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Intervenções</p>

    <div x-show="int.items.length === 0" class="bg-white rounded-lg border border-gray-200 py-8 text-center">
        <p class="text-xs text-gray-400">Sem informações de intervenções.</p>
    </div>

    <div x-show="int.items.length > 0" class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        <template x-for="i in int.items" :key="String(i.id ?? i.name ?? 'int') + '-' + String(i.dt_start ?? '')">
            <div class="px-2.5 py-2" :class="(i.labels || []).includes('Urgent') ? 'bg-red-50/30' : ''">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[11px] font-semibold text-gray-800 leading-tight" x-text="i.name || 'Intervenção não identificada'"></p>
                    <div x-show="(i.labels || []).length > 0" class="flex flex-wrap justify-end gap-1">
                        <template x-for="lbl in (i.labels || [])" :key="String(lbl)">
                            <span class="text-[9px] font-bold px-1.5 py-0.5 rounded"
                                  :class="lbl === 'Urgent' ? 'bg-red-50 text-red-600 ring-1 ring-red-200'
                                        : lbl === 'PRN'    ? 'bg-amber-50 text-amber-700 ring-1 ring-amber-200'
                                        : lbl === 'ACM'    ? 'bg-sky-50 text-sky-700 ring-1 ring-sky-200'
                                                           : 'bg-gray-100 text-gray-500'"
                                  x-text="labelPt(lbl)"></span>
                        </template>
                    </div>
                </div>

                <div class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[10px] leading-tight">
                    <span x-show="i.schedule" class="text-gray-600"><span class="font-semibold text-gray-500">Horario:</span> <span x-text="i.schedule"></span></span>
                    <span x-show="i.prescriber" class="text-gray-500"><span class="font-semibold text-gray-500">Prescritor:</span> <span x-text="i.prescriber"></span></span>
                    <span x-show="i.assignee" class="text-gray-500"><span class="font-semibold text-gray-500">Responsavel:</span> <span x-text="i.assignee"></span></span>
                    <span x-show="i.dt_start" class="text-gray-500"><span class="font-semibold text-gray-500">Vigencia:</span> <span x-text="i.dt_start"></span><span x-show="i.dt_end"> ate <span x-text="i.dt_end"></span></span></span>
                </div>

                <p x-show="i.observation" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Observacao:</span> <span x-text="i.observation"></span>
                </p>
            </div>
        </template>
    </div>

</div>{{-- /tab-int --}}
