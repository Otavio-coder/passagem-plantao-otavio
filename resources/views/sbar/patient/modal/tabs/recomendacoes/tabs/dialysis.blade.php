<div x-show="activeRecomendacaoTab === 'tab-dial'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-gray-400 uppercase tracking-wider mb-2">Diálise</p>

    <div x-show="dial.items.length === 0" class="bg-white rounded-lg border border-gray-200 py-8 text-center">
        <p class="text-xs text-gray-400">Sem informações de diálise.</p>
    </div>

    <div x-show="dial.items.length > 0" class="bg-white rounded-lg border border-gray-200 divide-y divide-gray-100">
        <template x-for="d in dial.items" :key="String(d.id ?? d.modalidade ?? 'dial') + '-' + String(d.dt_inicio ?? '')">
            <div class="px-2.5 py-2">
                <p class="text-[11px] font-semibold text-gray-800 leading-tight" x-text="d.modalidade || 'Diálise'"></p>

                <div class="mt-1.5 flex flex-wrap gap-x-2 gap-y-1 text-[10px] leading-tight text-gray-600">
                    <span x-show="d.sessoes_por_semana"><span class="font-semibold text-gray-500">Sessões:</span> <span x-text="d.sessoes_por_semana + 'x/semana'"></span></span>
                    <span x-show="d.duracao_sessao"><span class="font-semibold text-gray-500">Duração:</span> <span x-text="d.duracao_sessao + ' h'"></span></span>
                    <span x-show="d.dias_semana"><span class="font-semibold text-gray-500">Dias:</span> <span x-text="d.dias_semana"></span></span>
                    <span x-show="d.fluxo_sangue"><span class="font-semibold text-gray-500">QB:</span> <span x-text="d.fluxo_sangue + ' mL/min'"></span></span>
                    <span x-show="d.ktv"><span class="font-semibold text-gray-500">Kt/V:</span> <span x-text="d.ktv"></span></span>
                    <span x-show="d.ultrafiltracao"><span class="font-semibold text-gray-500">UF:</span> <span x-text="d.ultrafiltracao + ' mL'"></span></span>
                    <span x-show="d.horarios"><span class="font-semibold text-gray-500">Horarios:</span> <span x-text="d.horarios"></span></span>
                    <span x-show="d.prescriber"><span class="font-semibold text-gray-500">Prescritor:</span> <span x-text="d.prescriber"></span></span>
                    <span x-show="d.dt_inicio"><span class="font-semibold text-gray-500">Vigencia:</span> <span x-text="d.dt_inicio"></span><span x-show="d.dt_fim"> ate <span x-text="d.dt_fim"></span></span></span>
                </div>

                <p x-show="d.observacao" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Observacao:</span> <span x-text="d.observacao"></span>
                </p>
                <p x-show="d.justificativa" class="mt-1 text-[10px] text-gray-600 leading-tight">
                    <span class="font-semibold text-gray-500">Justificativa:</span> <span x-text="d.justificativa"></span>
                </p>
            </div>
        </template>
    </div>
</div>{{-- /tab-dial --}}
