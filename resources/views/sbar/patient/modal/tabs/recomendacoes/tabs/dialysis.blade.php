<div x-show="activeRecomendacaoTab === 'tab-dial'" style="display:none;" class="pt-3">
    <div x-show="{{ $dialCount }} > 0" class="space-y-2">
        <div class="flex items-center justify-between mb-1">
            <span class="text-[10px] font-bold text-gray-400 uppercase tracking-wider">Diálise</span>
            <span class="text-[10px] text-gray-400" x-text="dial.items.length + ' item(s)'"></span>
        </div>
        <template x-for="d in dial.items" :key="d.id">
            <div class="bg-white rounded-lg border border-gray-200 px-3 py-2.5 shadow-sm transition-colors">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <i class="fa-solid fa-circle-dot text-cyan-500" style="font-size:12px;"></i>
                            <p class="text-xs font-semibold text-gray-800 leading-snug" x-text="d.modalidade"></p>
                        </div>

                        {{-- Sessões / dias / duração --}}
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="d.sessoes_por_semana">
                                <span class="text-[10px] px-1.5 py-0.5 rounded bg-cyan-50 text-cyan-700 border border-cyan-200 font-medium"
                                      x-text="d.sessoes_por_semana + 'x/semana'"></span>
                            </template>
                            <template x-if="d.duracao_sessao">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    <i class="fa-regular fa-clock" style="font-size:8px;"></i>
                                    <span x-text="d.duracao_sessao + ' h'"></span>
                                </span>
                            </template>
                            <template x-if="d.dias_semana">
                                <span class="text-[10px] text-gray-600 bg-gray-100 border border-gray-200 px-1.5 py-0.5 rounded"
                                      x-text="d.dias_semana"></span>
                            </template>
                        </div>

                        {{-- Parâmetros --}}
                        <div class="flex flex-wrap items-center gap-1.5 mt-1.5">
                            <template x-if="d.fluxo_sangue">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    QB <span x-text="d.fluxo_sangue + ' mL/min'"></span>
                                </span>
                            </template>
                            <template x-if="d.ktv">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    Kt/V <span x-text="d.ktv"></span>
                                </span>
                            </template>
                            <template x-if="d.ultrafiltracao">
                                <span class="text-[10px] font-mono text-gray-600 bg-gray-50 border border-gray-200 px-1.5 py-0.5 rounded">
                                    UF <span x-text="d.ultrafiltracao + ' mL'"></span>
                                </span>
                            </template>
                        </div>

                        {{-- Horários --}}
                        <div x-show="d.horarios" class="flex flex-wrap gap-1 mt-1.5">
                            <template x-for="(slot, slotIdx) in (d.horarios || '').split(' ').filter(s => s)" :key="'dial-' + String(d.id ?? d.modalidade ?? 'x') + '-' + String(slot) + '-' + String(slotIdx)">
                                <span class="inline-flex items-center gap-0.5 text-[10px] font-mono font-semibold px-1.5 py-0.5 rounded bg-gray-100 text-gray-600 border border-gray-200">
                                    <i class="fa-regular fa-clock" style="font-size:9px;"></i>
                                    <span x-text="slot"></span>
                                </span>
                            </template>
                        </div>

                        <p x-show="d.observacao"
                           class="text-[10px] text-gray-500 mt-1.5 pl-2 border-l-2 border-cyan-200 leading-snug"
                           x-text="d.observacao"></p>
                    </div>

                    <div class="text-right flex-shrink-0">
                        <p x-show="d.prescriber" x-text="d.prescriber" class="text-[10px] text-gray-400"></p>
                        <p x-show="d.dt_inicio" class="text-[10px] text-gray-400">
                            <span x-text="d.dt_inicio"></span>
                            <template x-if="d.dt_fim"><span x-text="' → ' + d.dt_fim"></span></template>
                        </p>
                    </div>
                </div>
            </div>
        </template>
    </div>
</div>{{-- /tab-dial --}}
