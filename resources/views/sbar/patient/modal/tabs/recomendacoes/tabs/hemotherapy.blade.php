<div x-show="activeRecomendacaoTab === 'tab-hemo'" style="display:none;" class="pt-3">
    <div x-show="{{ (int) ($planDisplayData['counts']['tab-hemo'] ?? 0) }} > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-red-700">Hemoterapia</span>
            <span class="text-[10px] text-gray-400" x-text="hemo.items.length + ' item(s)'"></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
            <template x-for="h in hemo.items" :key="h.id">
                <div class="bg-white rounded-md border border-red-200 px-2 py-2 min-h-[140px] flex flex-col"
                     :class="{ 'border-red-300 bg-red-50/20': h.is_urgent }">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <x-ui.patient-icon name="hemoterapia" class="w-3.5 h-3.5 object-contain flex-shrink-0 opacity-75 text-red-700" />
                                <p class="text-[12px] font-semibold text-gray-800 leading-snug" x-text="h.name"></p>
                                <span x-show="h.is_urgent"
                                      class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-700 ring-1 ring-red-300">
                                    Urgente
                                </span>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1.5 text-[11px]">
                                <span x-show="h.tipo_code" class="inline-flex items-center gap-1 font-semibold text-red-700 bg-red-50 border border-red-200 px-1.5 py-0.5 rounded">
                                    <span>Tipo</span>
                                    <span x-text="h.tipo_code"></span>
                                </span>
                                <span x-show="h.prescriber_id" class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <span class="font-semibold">ID</span>
                                    <span x-text="h.prescriber_id"></span>
                                </span>
                                <span x-show="h.sector_name || h.sector_code" class="inline-flex items-center gap-1 text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                    <span x-text="h.sector_name || ('Setor ' + h.sector_code)"></span>
                                </span>
                                <span x-show="h.volume" class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded" x-text="h.volume"></span>
                            </div>
                            <p x-show="h.schedule" class="mt-1 text-[11px] text-gray-600 leading-tight">
                                <span class="font-semibold text-gray-500">Horarios:</span>
                                <span x-text="h.schedule"></span>
                            </p>
                            <p x-show="h.observation" x-text="h.observation"
                               class="text-[11px] text-gray-600 mt-1 leading-snug"></p>
                        </div>
                    </div>
                    <div class="mt-auto pt-1 text-[11px] text-gray-500">
                        <p x-show="h.prescriber"><span class="font-semibold text-gray-500">Prescritor:</span> <span x-text="h.prescriber"></span></p>
                        <p x-show="h.dt_start">
                            <span class="font-semibold text-gray-500">Vigencia:</span>
                            <span x-text="h.dt_start"></span>
                            <template x-if="h.dt_end"><span x-text="' → ' + h.dt_end"></span></template>
                        </p>
                    </div>
                </div>
            </template>
        </div>
    </div>
</div>{{-- /tab-hemo --}}
