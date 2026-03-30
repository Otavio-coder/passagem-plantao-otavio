<div x-show="activeRecomendacaoTab === 'tab-gas'" style="display:none;" class="pt-3">
    <div x-show="{{ $gasCount }} > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Gasoterapia</span>
            <span class="text-[10px] text-gray-400" x-text="gas.items.length + ' item(s)'"></span>
        </div>
        <template x-for="g in gas.items" :key="g.id">
            <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5 shadow-sm transition-colors">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <i class="fa-solid fa-lungs text-blue-500" style="font-size:12px;"></i>
                            <p class="text-xs font-semibold text-gray-800 leading-snug"
                               x-text="g.tipo_gas || g.modalidade || 'Gasoterapia'"></p>
                            <template x-if="g.urgente">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-700 ring-1 ring-red-300">Urgente</span>
                            </template>
                            <template x-if="g.se_necessario">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 ring-1 ring-amber-200">Se Necessário</span>
                            </template>
                            <template x-if="g.a_criterio_medico">
                                <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 ring-1 ring-sky-200">A Crit. Médico</span>
                            </template>
                        </div>

                        {{-- Modalidade / modo --}}
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="g.modalidade">
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-blue-100 text-blue-700 font-medium"
                                      x-text="g.modalidade"></span>
                            </template>
                            <template x-if="g.modo_administracao">
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 font-medium"
                                      x-text="g.modo_administracao"></span>
                            </template>
                            <template x-if="g.quantidade && g.unidade">
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-700 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded font-mono">
                                    <span x-text="g.quantidade + ' ' + g.unidade"></span>
                                </span>
                            </template>
                        </div>

                        {{-- Parameters: fio2, fluxo, peep, pip, volume_corrente, freq --}}
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="g.fio2">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    FiO₂ <span x-text="g.fio2 + '%'"></span>
                                </span>
                            </template>
                            <template x-if="g.fluxo_inspiratorio">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    Fluxo <span x-text="g.fluxo_inspiratorio + ' L/min'"></span>
                                </span>
                            </template>
                            <template x-if="g.peep">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    PEEP <span x-text="g.peep"></span>
                                </span>
                            </template>
                            <template x-if="g.pip">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    PIP <span x-text="g.pip"></span>
                                </span>
                            </template>
                            <template x-if="g.volume_corrente">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    VC <span x-text="g.volume_corrente + ' mL'"></span>
                                </span>
                            </template>
                            <template x-if="g.freq_ventilatoria">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    FR <span x-text="g.freq_ventilatoria + ' ipm'"></span>
                                </span>
                            </template>
                            <template x-if="g.pressao_suporte">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    PS <span x-text="g.pressao_suporte"></span>
                                </span>
                            </template>
                        </div>

                        {{-- Equipamentos --}}
                        <div x-show="g.equipamento_1 || g.equipamento_2 || g.equipamento_3"
                             class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="g.equipamento_1">
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-plug text-gray-400" style="font-size:8px;"></i>
                                    <span x-text="g.equipamento_1"></span>
                                </span>
                            </template>
                            <template x-if="g.equipamento_2">
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-plug text-gray-400" style="font-size:8px;"></i>
                                    <span x-text="g.equipamento_2"></span>
                                </span>
                            </template>
                            <template x-if="g.equipamento_3">
                                <span class="inline-flex items-center gap-1 text-[10px] text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-solid fa-plug text-gray-400" style="font-size:8px;"></i>
                                    <span x-text="g.equipamento_3"></span>
                                </span>
                            </template>
                        </div>

                        {{-- Horários --}}
                        <div x-show="g.horarios" class="flex flex-wrap gap-1 mt-1.5">
                            <template x-for="(slot, slotIdx) in (g.horarios || '').split(' ').filter(s => s)" :key="'gas-' + String(g.id ?? g.tipo_gas ?? 'x') + '-' + String(slot) + '-' + String(slotIdx)">
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                    <span x-text="slot"></span>
                                </span>
                            </template>
                        </div>

                        <p x-show="g.observacao"
                           class="text-[10px] text-gray-500 mt-1.5 pl-2 border-l-2 border-blue-200 leading-snug"
                           x-text="g.observacao"></p>
                    </div>

                    <div class="text-right flex-shrink-0">
                        <p x-show="g.prescriber" x-text="g.prescriber" class="text-[10px] text-gray-400"></p>
                        <p x-show="g.dt_inicio" class="text-[10px] text-gray-400">
                            <span x-text="g.dt_inicio"></span>
                            <template x-if="g.dt_fim"><span x-text="' → ' + g.dt_fim"></span></template>
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>{{-- /tab-gas --}}
