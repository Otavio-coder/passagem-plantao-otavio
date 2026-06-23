<div x-show="activeRecomendacaoTab === 'tab-nut'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-emerald-700 mb-2">Plano Nutricional</p>

    <div x-show="nut.items.length === 0" class="bg-white rounded-lg border border-emerald-200 py-8 text-center">
        <p class="text-xs text-emerald-700/70">Sem informações de nutrição.</p>
    </div>

    <div x-show="nut.items.length > 0" class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-3 auto-rows-fr">
        <template x-for="(nutItem, idx) in nut.items" :key="idx + '-' + (nutItem.id || nutItem.name || 'nutrition')">
            <div class="bg-white rounded-xl border border-emerald-200 shadow-sm p-3 sm:p-3.5 flex flex-col gap-2">

                {{-- Header: nome + badge de tipo --}}
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[13px] font-semibold text-gray-800 leading-snug" x-text="nutItem.name || 'Plano nutricional'"></p>
                    <span class="shrink-0 text-[10px] font-bold px-2 py-0.5 rounded border"
                          :class="{
                              'bg-rose-50 text-rose-700 border-rose-200':   String(nutItem.type || '').toLowerCase() === 'fasting',
                              'bg-violet-50 text-violet-700 border-violet-200': String(nutItem.type || '').toLowerCase() === 'enteral',
                              'bg-teal-50 text-teal-700 border-teal-200':   String(nutItem.type || '').toLowerCase() === 'special',
                              'bg-emerald-50 text-emerald-700 border-emerald-200': !['fasting','enteral','special'].includes(String(nutItem.type || '').toLowerCase())
                          }"
                          x-text="nutritionTypePt(nutItem.type || 'Diet')"></span>
                </div>

                {{-- Dados principais --}}
                <div class="grid grid-cols-2 gap-x-3 gap-y-1 text-[11px] leading-snug">
                    <div x-show="nutItem.interval_description || nutItem.interval_code" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Frequência:</span>
                        <span x-text="nutItem.interval_description || nutItem.interval_code"></span>
                    </div>
                    <div x-show="nutItem.route" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Via:</span>
                        <span x-text="nutItem.route"></span>
                    </div>
                    <div x-show="nutItem.volume" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Volume:</span>
                        <span x-text="nutItem.volume + ' mL'"></span>
                    </div>
                    <div x-show="nutItem.total_volume" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Vol. total:</span>
                        <span x-text="nutItem.total_volume + ' mL'"></span>
                    </div>
                    <div x-show="nutItem.infusion_speed" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Velocidade:</span>
                        <span x-text="nutItem.infusion_speed + ' mL/h'"></span>
                    </div>
                    <div x-show="nutItem.total_kcal" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Kcal/dia:</span>
                        <span x-text="nutItem.total_kcal"></span>
                    </div>
                    <div x-show="nutItem.schedule" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Horários:</span>
                        <span x-text="nutItem.schedule"></span>
                    </div>
                    <div x-show="nutItem.dt_start" class="text-gray-600 col-span-2">
                        <span class="font-semibold text-gray-500">Vigência:</span>
                        <span x-text="nutItem.dt_start"></span><span x-show="nutItem.dt_end"> até <span x-text="nutItem.dt_end"></span></span>
                    </div>
                </div>

                {{-- Modo de entrega --}}
                <div x-show="(nutItem.delivery_mode || []).length > 0" class="flex flex-wrap gap-1">
                    <template x-for="mode in (nutItem.delivery_mode || [])" :key="mode">
                        <span class="text-[10px] px-1.5 py-0.5 rounded bg-emerald-50 text-emerald-700 border border-emerald-200 font-medium" x-text="mode"></span>
                    </template>
                </div>

                {{-- Dados do Jejum --}}
                <div x-show="nutItem.is_fasting && (nutItem.fasting_type || nutItem.fasting_goal || nutItem.fasting_start || nutItem.fasting_end)"
                     class="bg-amber-50 border border-amber-200 rounded-lg px-2 py-1.5 text-[11px] text-amber-800 leading-snug space-y-0.5">
                    <p class="font-semibold text-amber-900 text-[10px]">Jejum</p>
                    <p x-show="nutItem.fasting_type"><span class="font-semibold">Tipo:</span> <span x-text="nutItem.fasting_type"></span></p>
                    <p x-show="nutItem.fasting_goal"><span class="font-semibold">Objetivo:</span> <span x-text="nutItem.fasting_goal"></span></p>
                    <p x-show="nutItem.fasting_start || nutItem.fasting_end">
                        <span class="font-semibold">Período:</span>
                        <span x-show="nutItem.fasting_start" x-text="nutItem.fasting_start"></span>
                        <span x-show="nutItem.fasting_start && nutItem.fasting_end"> até </span>
                        <span x-show="nutItem.fasting_end" x-text="nutItem.fasting_end"></span>
                    </p>
                </div>

                {{-- Composição (produtos) --}}
                <div x-show="(nutItem.products || []).length > 0" class="border-t border-emerald-100 pt-2">
                    <p class="text-[10px] font-semibold text-gray-500 mb-1">Composição</p>
                    <ul class="space-y-0.5">
                        <template x-for="(prod, pi) in (nutItem.products || [])" :key="pi">
                            <li class="text-[11px] text-gray-700 leading-snug flex items-baseline gap-1">
                                <span class="text-emerald-600 shrink-0">·</span>
                                <span x-text="prod.name"></span>
                                <span x-show="prod.dose" class="text-gray-500 shrink-0" x-text="'— ' + prod.dose + ' mL'"></span>
                            </li>
                        </template>
                    </ul>
                </div>

                {{-- Notas --}}
                <div x-show="nutItem.allergies || nutItem.guidance || nutItem.observation || nutItem.justification"
                     class="border-t border-emerald-100 pt-2 space-y-1 text-[11px] leading-snug">
                    <p x-show="nutItem.allergies" class="text-amber-700">
                        <span class="font-semibold">Alergias:</span> <span x-text="nutItem.allergies"></span>
                    </p>
                    <p x-show="nutItem.guidance" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Orientação:</span> <span x-text="nutItem.guidance"></span>
                    </p>
                    <p x-show="nutItem.observation" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Observação:</span> <span x-text="nutItem.observation"></span>
                    </p>
                    <p x-show="nutItem.justification" class="text-gray-600">
                        <span class="font-semibold text-gray-500">Justificativa:</span> <span x-text="nutItem.justification"></span>
                    </p>
                </div>

                {{-- Rodapé: nutricionista / prescritor --}}
                <div x-show="nutItem.nutritionist || nutItem.prescriber"
                     class="border-t border-emerald-100 pt-1.5 text-[10px] text-gray-500 leading-snug">
                    <span x-show="nutItem.nutritionist">
                        <span class="font-semibold">Nutricionista:</span> <span x-text="nutItem.nutritionist"></span>
                    </span>
                    {{-- Só mostra prescritor se for diferente do nutricionista --}}
                    <span x-show="nutItem.prescriber && nutItem.prescriber !== nutItem.nutritionist">
                        <span x-show="nutItem.nutritionist"> · </span>
                        <span class="font-semibold">Prescritor:</span> <span x-text="nutItem.prescriber"></span>
                    </span>
                </div>

            </div>
        </template>
    </div>
</div>{{-- /tab-nut --}}
