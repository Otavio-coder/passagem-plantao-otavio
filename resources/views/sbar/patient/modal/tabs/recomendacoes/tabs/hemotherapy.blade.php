<div x-show="activeRecomendacaoTab === 'tab-hemo'" style="display:none;" class="pt-3">
    <div x-show="{{ $hemoCount }} > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Hemoterapia</span>
            <span class="text-[10px] text-gray-400" x-text="hemo.items.length + ' item(s)'"></span>
        </div>
        <template x-for="h in hemo.items" :key="h.id">
            <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5 shadow-sm"
                 :class="{ 'border-red-200 bg-red-50/20': h.is_urgent }">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2">
                            <i class="fa-solid fa-droplet text-red-500" style="font-size:12px;"></i>
                            <p class="text-xs font-semibold text-gray-800 leading-snug" x-text="h.name"></p>
                            <span x-show="h.is_urgent"
                                  class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-700 ring-1 ring-red-300">
                                Urgente
                            </span>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="h.sector_name || h.sector_code">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                    <span x-text="h.sector_name || ('Setor ' + h.sector_code)"></span>
                                </span>
                            </template>
                            <template x-if="h.volume">
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-flask text-gray-400" style="font-size:9px;"></i>
                                    <span x-text="h.volume"></span>
                                </span>
                            </template>
                            <template x-for="(slot, slotIdx) in (h.schedule || '').split(' ').filter(s => s)" :key="'hemo-' + String(h.id ?? h.name ?? 'x') + '-' + String(slot) + '-' + String(slotIdx)">
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                    <span x-text="slot"></span>
                                </span>
                            </template>
                        </div>
                        <p x-show="h.observation" x-text="h.observation"
                           class="text-[10px] text-gray-500 mt-1 pl-2 border-l-2 border-red-200 leading-snug"></p>
                    </div>
                    <div class="text-right flex-shrink-0">
                        <p x-show="h.prescriber" x-text="h.prescriber" class="text-[10px] text-gray-400"></p>
                        <p x-show="h.dt_start" class="text-[10px] text-gray-400">
                            <span x-text="h.dt_start"></span>
                            <template x-if="h.dt_end"><span x-text="' → ' + h.dt_end"></span></template>
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>{{-- /tab-hemo --}}
