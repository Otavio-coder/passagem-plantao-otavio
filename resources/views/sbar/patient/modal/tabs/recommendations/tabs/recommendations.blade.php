<div x-show="activeRecomendacaoTab === 'tab-rec'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-slate-700 mb-2">Recomendações</p>

    <div x-show="rec.items.length === 0" class="bg-white rounded-lg border border-slate-200 py-8 text-center">
        <p class="text-xs text-slate-500">Sem informações de recomendações.</p>
    </div>

    <div x-show="rec.items.length > 0" class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-2">
        <template x-for="o in rec.items" :key="String(o.id ?? o.text ?? 'rec') + '-' + String(o.dt_start ?? '')">
            <div class="bg-white rounded-md border border-slate-200 px-2 py-1.5 min-h-auto xl:min-h-[80px] flex flex-col">
                <div class="flex items-start gap-1.5 min-w-0 mb-1">
                    <x-ui.patient-icon name="notes" class="w-3.5 h-3.5 object-contain flex-shrink-0 opacity-75 mt-0.5 text-gray-600" />
                    <div class="flex-1 min-w-0">
                        <p class="text-[12px] font-semibold text-gray-800 leading-snug" x-text="o.text || 'Recomendação não especificada'"></p>
                        <span x-show="o.type && o.type !== o.text" class="inline-block text-[10px] font-bold text-slate-700 bg-slate-100 border border-slate-300 px-1 py-0.5 rounded mt-0.5" x-text="o.type"></span>
                    </div>
                </div>

                <div class="flex flex-wrap gap-x-3 gap-y-0.5 text-[10px] leading-tight text-gray-600">
                    <span x-show="o.prescriber"><span class="font-semibold text-gray-500">Presc:</span> <span x-text="o.prescriber"></span></span>
                    <span x-show="o.dt_start" class="text-gray-500"><span class="font-semibold">Vigência:</span> <span x-text="o.dt_start"></span></span>
                </div>

                <p x-show="o.observation" class="mt-1 text-[10px] text-gray-600 leading-snug line-clamp-2 mt-auto">
                    <span class="font-semibold text-gray-500">Obs:</span> <span x-text="o.observation"></span>
                </p>
            </div>
        </template>
    </div>

</div>{{-- /tab-rec --}}
