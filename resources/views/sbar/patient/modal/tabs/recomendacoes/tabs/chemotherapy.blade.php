<div x-show="activeRecomendacaoTab === 'tab-chemo'" style="display:none;" class="pt-3">
    <div x-show="{{ $chemoCount }} > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-[#0A4700] uppercase tracking-wider">Quimioterapia</span>
            <span class="text-[10px] text-gray-400" x-text="chemo.items.length + ' item(s)'"></span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-2">
            <template x-for="c in chemo.items" :key="c.id">
                <div class="bg-white rounded-md border border-[#0A4700]/25 px-2 py-2 min-h-[132px] flex flex-col bg-[#0A4700]/[0.03]">
                    <div class="flex items-start justify-between gap-2">
                        <div class="flex-1 min-w-0">
                            <div class="flex items-center gap-1.5 flex-wrap">
                                <img src="{{ asset('images/icons/patient-card/quimioterapia.svg') }}" alt="" class="w-3.5 h-3.5 object-contain flex-shrink-0 opacity-75">
                                <p class="text-[12px] font-semibold text-gray-800 leading-snug" x-text="c.name"></p>
                                <template x-if="c.cycle">
                                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-[#0A4700]/15 text-[#0A4700] ring-1 ring-[#0A4700]/30"
                                          x-text="'Ciclo ' + c.cycle"></span>
                                </template>
                            </div>
                            <div class="flex flex-wrap items-center gap-1.5 mt-1.5 text-[11px]">
                                <span x-show="c.protocol" class="inline-flex items-center gap-1 text-[#0A4700] bg-[#0A4700]/10 border border-[#0A4700]/25 px-1.5 py-0.5 rounded">
                                    <span class="font-semibold">Protocolo</span>
                                    <span x-text="c.protocol"></span>
                                </span>
                                <span x-show="c.patient_person_id" class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <span class="font-semibold">PF</span>
                                    <span x-text="c.patient_person_id"></span>
                                </span>
                                <span x-show="c.sector_name || c.sector_code" class="inline-flex items-center gap-1 text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                    <span x-text="c.sector_name || ('Setor ' + c.sector_code)"></span>
                                </span>
                                <span x-show="c.scheduled" class="inline-flex items-center gap-1 text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded" x-text="c.scheduled"></span>
                            </div>
                            <p x-show="c.local" class="text-[11px] text-gray-600 mt-1">
                                <span class="font-semibold text-gray-500">Local:</span>
                                <span x-text="c.local"></span>
                            </p>
                        </div>
                    </div>
                    <p x-show="c.prescriber" x-text="c.prescriber" class="text-[11px] text-gray-500 mt-auto"></p>
                </div>
            </template>
        </div>
    </div>
</div>{{-- /tab-chemo --}}
