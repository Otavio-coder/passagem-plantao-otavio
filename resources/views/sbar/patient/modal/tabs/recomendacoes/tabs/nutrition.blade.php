<div x-show="activeRecomendacaoTab === 'tab-nut'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-emerald-700 uppercase tracking-wider mb-2">Plano Nutricional</p>

    <div x-show="nut.items.length === 0" class="bg-white rounded-lg border border-emerald-200 py-8 text-center">
        <p class="text-xs text-emerald-700/70">Sem informações de nutrição.</p>
    </div>

    <div x-show="nut.items.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 auto-rows-fr">
        <template x-for="(nutItem, idx) in nut.items" :key="idx + '-' + (nutItem.id || nutItem.name || 'nutrition')">
            <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-3 sm:p-3.5 h-full min-h-[280px] sm:min-h-[300px] flex flex-col">
                <div class="flex items-start justify-between gap-2.5">
                    <p class="text-[13px] font-semibold text-gray-800 leading-snug" x-text="nutItem.name || 'Plano nutricional'"></p>
                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded border"
                          :class="{
                              'bg-rose-50 text-rose-700 border-rose-200': String(nutItem.type || '').toLowerCase() === 'fasting',
                              'bg-violet-50 text-violet-700 border-violet-200': String(nutItem.type || '').toLowerCase() === 'enteral',
                              'bg-teal-50 text-teal-700 border-teal-200': String(nutItem.type || '').toLowerCase() === 'special',
                              'bg-emerald-50 text-emerald-700 border-emerald-200': !['fasting','enteral','special'].includes(String(nutItem.type || '').toLowerCase())
                          }"
                          x-text="nutritionTypePt(nutItem.type || 'Diet')"></span>
                </div>

                <div class="mt-1.5 flex flex-wrap gap-1.5 text-[10px]">
                    <span x-show="nutItem.id" class="inline-flex items-center px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Prescrição #<span x-text="nutItem.id"></span>
                    </span>
                    <span x-show="nutItem.diet_code || nutItem.material_code" class="inline-flex items-center px-1.5 py-0.5 rounded bg-gray-50 text-gray-600 border border-gray-200">
                        <span x-show="nutItem.diet_code">Dieta <span x-text="nutItem.diet_code"></span></span>
                        <span x-show="nutItem.diet_code && nutItem.material_code"> · </span>
                        <span x-show="nutItem.material_code">Material <span x-text="nutItem.material_code"></span></span>
                    </span>
                </div>

                <div class="mt-2 grid grid-cols-1 sm:grid-cols-2 gap-x-3 gap-y-1.5 text-[11px] leading-snug">
                    <div x-show="nutItem.interval_description || nutItem.interval_code" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Intervalo:</span>
                        <span x-text="nutItem.interval_description || nutItem.interval_code"></span>
                    </div>
                    <div x-show="nutItem.route" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Via:</span>
                        <span x-text="nutItem.route"></span>
                    </div>
                    <div x-show="nutItem.volume" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Volume:</span>
                        <span x-text="nutItem.volume"></span>
                    </div>
                    <div x-show="nutItem.total_volume" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Total:</span>
                        <span x-text="nutItem.total_volume"></span>
                    </div>
                    <div x-show="nutItem.infusion_speed" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Velocidade:</span>
                        <span x-text="nutItem.infusion_speed"></span>
                    </div>
                    <div x-show="nutItem.total_kcal" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Kcal total:</span>
                        <span x-text="nutItem.total_kcal"></span>
                    </div>
                    <div x-show="nutItem.prescriber" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Prescritor:</span>
                        <span x-text="nutItem.prescriber"></span>
                    </div>
                    <div x-show="nutItem.nutritionist" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Nutricionista:</span>
                        <span x-text="nutItem.nutritionist"></span>
                    </div>
                    <div x-show="nutItem.route_code" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Via (cód.):</span>
                        <span x-text="nutItem.route_code"></span>
                    </div>
                    <div x-show="nutItem.dt_start" class="text-gray-600 sm:col-span-2">
                        <span class="font-semibold text-gray-500">Vigência:</span>
                        <span x-text="nutItem.dt_start"></span><span x-show="nutItem.dt_end"> até <span x-text="nutItem.dt_end"></span></span>
                    </div>
                </div>

                <div x-show="nutItem.is_fasting && (nutItem.fasting_type || nutItem.fasting_goal || nutItem.fasting_start || nutItem.fasting_end)"
                     class="mt-2 bg-amber-50/70 border border-amber-200 rounded-lg px-2 py-1.5 text-[11px] text-amber-800 leading-snug">
                    <p class="font-semibold text-amber-900">Dados do Jejum</p>
                    <p x-show="nutItem.fasting_type"><span class="font-semibold">Tipo:</span> <span x-text="nutItem.fasting_type"></span></p>
                    <p x-show="nutItem.fasting_goal"><span class="font-semibold">Objetivo:</span> <span x-text="nutItem.fasting_goal"></span></p>
                    <p x-show="nutItem.fasting_start || nutItem.fasting_end">
                        <span class="font-semibold">Período:</span>
                        <span x-show="nutItem.fasting_start" x-text="nutItem.fasting_start"></span>
                        <span x-show="nutItem.fasting_start && nutItem.fasting_end"> até </span>
                        <span x-show="nutItem.fasting_end" x-text="nutItem.fasting_end"></span>
                    </p>
                </div>

                <div class="mt-2 pt-2 border-t border-emerald-100 space-y-1.5 text-[11px] leading-snug flex-1 overflow-y-auto pr-1">
                <p x-show="nutItem.allergies" class="text-amber-700 leading-snug">
                    <span class="font-semibold">Alergias:</span> <span x-text="nutItem.allergies"></span>
                </p>
                <p x-show="nutItem.guidance" class="text-gray-600 leading-snug">
                    <span class="font-semibold text-gray-500">Orientacao:</span> <span x-text="nutItem.guidance"></span>
                </p>
                <p x-show="nutItem.observation" class="text-gray-600 leading-snug">
                    <span class="font-semibold text-gray-500">Observacao:</span> <span x-text="nutItem.observation"></span>
                </p>
                <p x-show="nutItem.justification" class="text-gray-600 leading-snug">
                    <span class="font-semibold text-gray-500">Justificativa:</span> <span x-text="nutItem.justification"></span>
                </p>

                <p x-show="(nutItem.delivery_mode || []).length > 0" class="text-gray-600 leading-snug">
                    <span class="font-semibold text-gray-500">Modo:</span>
                    <span x-text="(nutItem.delivery_mode || []).join(', ')"></span>
                </p>
                <p x-show="(nutItem.products || []).length > 0" class="text-gray-600 leading-snug">
                    <span class="font-semibold text-gray-500">Composicao:</span>
                    <span x-text="(nutItem.products || []).map(p => [p.name, p.dose].filter(Boolean).join(' - ')).join('; ')"></span>
                </p>
                </div>
            </div>
        </template>
    </div>
</div>{{-- /tab-nut --}}
