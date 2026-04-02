<div x-show="activeRecomendacaoTab === 'tab-rec'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Recomendações</p>

    <div x-show="ord.items.length === 0" class="bg-white rounded-lg border border-gray-200 py-8 text-center">
        <p class="text-xs text-gray-400">Sem informações de recomendações.</p>
    </div>

    <div x-show="ord.items.length > 0" class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        <template x-for="o in ord.items" :key="String(o.id ?? o.text ?? 'ord') + '-' + String(o.dt_start ?? '')">
            <div class="px-2.5 py-2">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[11px] font-semibold text-gray-800 leading-tight" x-text="o.text || 'Recomendação não especificada'"></p>
                    <span x-show="o.type" class="shrink-0 text-[9px] font-bold text-violet-700 bg-violet-50 border border-violet-200 px-1.5 py-0.5 rounded" x-text="o.type"></span>
                </div>

                <div class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[10px] leading-tight">
                    <span x-show="o.schedule" class="text-gray-600"><span class="font-semibold text-gray-500">Horario:</span> <span x-text="o.schedule"></span></span>
                    <span x-show="o.prescriber" class="text-gray-500"><span class="font-semibold text-gray-500">Prescritor:</span> <span x-text="o.prescriber"></span></span>
                    <span x-show="o.dt_start" class="text-gray-500"><span class="font-semibold text-gray-500">Vigencia:</span> <span x-text="o.dt_start"></span><span x-show="o.dt_end"> ate <span x-text="o.dt_end"></span></span></span>
                </div>

                <p x-show="o.observation" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Observacao:</span> <span x-text="o.observation"></span>
                </p>
            </div>
        </template>
    </div>

</div>{{-- /tab-rec --}}
