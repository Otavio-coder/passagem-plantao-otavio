<div x-show="activeRecomendacaoTab === 'tab-chemo'" style="display:none;" class="pt-3">
    <div x-show="{{ $chemoCount }} > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Quimioterapia</span>
            <span class="text-[10px] text-gray-400" x-text="chemo.items.length + ' item(s)'"></span>
        </div>
        <template x-for="c in chemo.items" :key="c.id">
            <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5 shadow-sm transition-colors">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <i class="fa-solid fa-flask-vial text-purple-500" style="font-size:11px;"></i>
                            <p class="text-xs font-semibold text-gray-800 leading-snug" x-text="c.name"></p>
                            <template x-if="c.cycle">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-purple-100 text-purple-700 ring-1 ring-purple-200"
                                      x-text="'Ciclo ' + c.cycle"></span>
                            </template>
                        </div>
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="c.sector_name || c.sector_code">
                                <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                    <span x-text="c.sector_name || ('Setor ' + c.sector_code)"></span>
                                </span>
                            </template>
                            <template x-if="c.scheduled">
                                <span class="inline-flex items-center gap-1 text-[10px] font-mono font-semibold
                                             px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-calendar" style="font-size:9px;"></i>
                                    <span x-text="c.scheduled"></span>
                                </span>
                            </template>
                            <template x-if="c.local">
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-location-dot text-gray-400" style="font-size:9px;"></i>
                                    <span x-text="c.local"></span>
                                </span>
                            </template>
                        </div>
                        <p x-show="c.prescriber" x-text="c.prescriber" class="text-[10px] text-gray-400 mt-1"></p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>{{-- /tab-chemo --}}
