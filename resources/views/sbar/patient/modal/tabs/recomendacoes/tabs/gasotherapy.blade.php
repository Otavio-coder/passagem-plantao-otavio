<div x-show="activeRecomendacaoTab === 'tab-gas'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Gasoterapia</p>

    <div x-show="gas.items.length === 0" class="bg-white rounded-lg border border-gray-200 py-8 text-center">
        <p class="text-xs text-gray-400">Sem informações de gasoterapia.</p>
    </div>

    <div x-show="gas.items.length > 0" class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        <template x-for="g in gas.items" :key="String(g.id ?? g.tipo_gas ?? 'gas') + '-' + String(g.dt_inicio ?? '')">
            <div class="px-2.5 py-2" :class="g.urgente ? 'bg-red-50/30' : ''">
                <div class="flex items-start justify-between gap-2">
                    <p class="text-[11px] font-semibold text-gray-800 leading-tight" x-text="g.tipo_gas || g.modalidade || 'Gasoterapia'"></p>
                    <div class="flex flex-wrap justify-end gap-1">
                        <span x-show="g.urgente" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-700 ring-1 ring-red-300">Urgente</span>
                        <span x-show="g.se_necessario" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 ring-1 ring-amber-200">Se Necessário</span>
                        <span x-show="g.a_criterio_medico" class="text-[9px] font-bold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 ring-1 ring-sky-200">A Crit. Médico</span>
                    </div>
                </div>

                <div class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[10px] leading-tight text-gray-600">
                    <span x-show="g.modalidade"><span class="font-semibold text-gray-500">Modalidade:</span> <span x-text="g.modalidade"></span></span>
                    <span x-show="g.modo_administracao"><span class="font-semibold text-gray-500">Modo:</span> <span x-text="g.modo_administracao"></span></span>
                    <span x-show="g.quantidade || g.unidade"><span class="font-semibold text-gray-500">Dose:</span> <span x-text="[g.quantidade, g.unidade].filter(Boolean).join(' ')"></span></span>
                    <span x-show="g.fio2"><span class="font-semibold text-gray-500">FiO2:</span> <span x-text="g.fio2 + '%' "></span></span>
                    <span x-show="g.fluxo_inspiratorio"><span class="font-semibold text-gray-500">Fluxo:</span> <span x-text="g.fluxo_inspiratorio + ' L/min'"></span></span>
                    <span x-show="g.peep"><span class="font-semibold text-gray-500">PEEP:</span> <span x-text="g.peep"></span></span>
                    <span x-show="g.pip"><span class="font-semibold text-gray-500">PIP:</span> <span x-text="g.pip"></span></span>
                    <span x-show="g.volume_corrente"><span class="font-semibold text-gray-500">VC:</span> <span x-text="g.volume_corrente + ' mL'"></span></span>
                    <span x-show="g.freq_ventilatoria"><span class="font-semibold text-gray-500">FR:</span> <span x-text="g.freq_ventilatoria + ' ipm'"></span></span>
                    <span x-show="g.pressao_suporte"><span class="font-semibold text-gray-500">PS:</span> <span x-text="g.pressao_suporte"></span></span>
                    <span x-show="g.horarios"><span class="font-semibold text-gray-500">Horarios:</span> <span x-text="g.horarios"></span></span>
                    <span x-show="g.prescriber"><span class="font-semibold text-gray-500">Prescritor:</span> <span x-text="g.prescriber"></span></span>
                    <span x-show="g.dt_inicio"><span class="font-semibold text-gray-500">Vigencia:</span> <span x-text="g.dt_inicio"></span><span x-show="g.dt_fim"> ate <span x-text="g.dt_fim"></span></span></span>
                </div>

                <p x-show="g.equipamento_1 || g.equipamento_2 || g.equipamento_3" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Equipamentos:</span>
                    <span x-text="[g.equipamento_1, g.equipamento_2, g.equipamento_3].filter(Boolean).join(', ')"></span>
                </p>

                <p x-show="g.observacao" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Observacao:</span> <span x-text="g.observacao"></span>
                </p>
                <p x-show="g.justificativa" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Justificativa:</span> <span x-text="g.justificativa"></span>
                </p>
            </div>
        </template>
    </div>
</div>{{-- /tab-gas --}}
