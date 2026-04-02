<div x-show="activeRecomendacaoTab === 'tab-nut'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Plano Nutricional</p>

    <div x-show="nut.items.length === 0" class="bg-white rounded-lg border border-gray-200 py-8 text-center">
        <p class="text-xs text-gray-400">Sem informações de nutrição.</p>
    </div>

    <div x-show="nut.items.length > 0" class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        <template x-for="(nutItem, idx) in nut.items" :key="idx + '-' + (nutItem.id || nutItem.name || 'nutrition')">
            <div class="px-2.5 py-2">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[11px] font-semibold text-gray-800 leading-tight" x-text="nutItem.name || 'Plano nutricional'"></p>
                    <span class="shrink-0 text-[9px] font-bold px-1.5 py-0.5 rounded border"
                          :class="{
                              'bg-rose-50 text-rose-700 border-rose-200': String(nutItem.type || '').toLowerCase() === 'fasting',
                              'bg-violet-50 text-violet-700 border-violet-200': String(nutItem.type || '').toLowerCase() === 'enteral',
                              'bg-teal-50 text-teal-700 border-teal-200': String(nutItem.type || '').toLowerCase() === 'special',
                              'bg-emerald-50 text-emerald-700 border-emerald-200': !['fasting','enteral','special'].includes(String(nutItem.type || '').toLowerCase())
                          }"
                          x-text="nutritionTypePt(nutItem.type || 'Diet')"></span>
                </div>

                <div class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[10px] leading-tight">
                    <span x-show="nutItem.schedule" class="text-gray-600"><span class="font-semibold text-gray-500">Horario:</span> <span x-text="nutItem.schedule"></span></span>
                    <span x-show="nutItem.interval_description || nutItem.interval_code" class="text-gray-600"><span class="font-semibold text-gray-500">Intervalo:</span> <span x-text="nutItem.interval_description || nutItem.interval_code"></span></span>
                    <span x-show="nutItem.route" class="text-gray-600"><span class="font-semibold text-gray-500">Via:</span> <span x-text="nutItem.route"></span></span>
                    <span x-show="nutItem.volume" class="text-gray-600"><span class="font-semibold text-gray-500">Volume:</span> <span x-text="nutItem.volume"></span></span>
                    <span x-show="nutItem.total_volume" class="text-gray-600"><span class="font-semibold text-gray-500">Total:</span> <span x-text="nutItem.total_volume"></span></span>
                    <span x-show="nutItem.infusion_speed" class="text-gray-600"><span class="font-semibold text-gray-500">Velocidade:</span> <span x-text="nutItem.infusion_speed"></span></span>
                    <span x-show="nutItem.dt_start" class="text-gray-500"><span class="font-semibold text-gray-500">Vigencia:</span> <span x-text="nutItem.dt_start"></span><span x-show="nutItem.dt_end"> ate <span x-text="nutItem.dt_end"></span></span></span>
                    <span x-show="nutItem.prescriber" class="text-gray-500"><span class="font-semibold text-gray-500">Prescritor:</span> <span x-text="nutItem.prescriber"></span></span>
                </div>

                <p x-show="nutItem.allergies" class="mt-1 text-[10px] text-amber-700 leading-tight">
                    <span class="font-semibold">Alergias:</span> <span x-text="nutItem.allergies"></span>
                </p>
                <p x-show="nutItem.guidance" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Orientacao:</span> <span x-text="nutItem.guidance"></span>
                </p>
                <p x-show="nutItem.observation" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Observacao:</span> <span x-text="nutItem.observation"></span>
                </p>
                <p x-show="nutItem.justification" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Justificativa:</span> <span x-text="nutItem.justification"></span>
                </p>

                <p x-show="(nutItem.delivery_mode || []).length > 0" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Modo:</span>
                    <span x-text="(nutItem.delivery_mode || []).join(', ')"></span>
                </p>
                <p x-show="(nutItem.products || []).length > 0" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Composicao:</span>
                    <span x-text="(nutItem.products || []).map(p => [p.name, p.dose].filter(Boolean).join(' - ')).join('; ')"></span>
                </p>
            </div>
        </template>
    </div>
</div>{{-- /tab-nut --}}
