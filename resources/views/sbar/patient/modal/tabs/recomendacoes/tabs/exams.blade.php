<div x-show="activeRecomendacaoTab === 'tab-exam'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-blue-700 uppercase tracking-wider mb-2">Exames</p>
    <div class="flex flex-wrap items-center gap-2 mb-3">
        <div class="relative flex-1 min-w-[160px]">
            <i class="fa-solid fa-magnifying-glass absolute left-3 top-1/2 -translate-y-1/2 text-gray-400" style="font-size:11px;"></i>
            <input type="search" :value="examQ" @input.debounce.250ms="setExamQ($event.target.value)"
                   placeholder="Buscar exame..."
                   class="w-full pl-8 pr-7 py-2 text-xs rounded-lg border border-gray-200 bg-white focus:outline-none focus:ring-2 focus:ring-blue-200 focus:border-blue-500 transition-all placeholder-gray-400" style="font-size:16px;">
            <button x-show="examQ" @click="setExamQ('')" class="absolute right-2.5 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600">
                <i class="fa-solid fa-xmark" style="font-size:11px;"></i>
            </button>
        </div>
    </div>
    <div x-show="filteredExams.length === 0" class="bg-white rounded-xl border border-gray-200 py-10 flex flex-col items-center gap-2">
        <i class="fa-solid fa-filter-circle-xmark text-gray-200" style="font-size:28px;"></i>
        <p class="text-sm text-gray-400">Nenhum exame encontrado.</p>
        <button @click="clearExamFilters()" class="text-xs font-semibold text-blue-700 hover:underline">Limpar filtros</button>
    </div>
    <div x-show="filteredExams.length > 0" class="space-y-2">
        <template x-for="(exam, idx) in pagedExams" :key="(exam.id || 'noid') + '-' + (exam.scheduled_raw || exam.scheduled || '') + '-' + idx">
            <div class="bg-white rounded-lg border border-blue-200 px-3 py-2.5 shadow-sm transition-colors">
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-baseline gap-1.5">
                            <p class="text-xs font-semibold text-gray-800 leading-snug"
                               x-text="exam.name || 'Exame não identificado'"></p>
                            <span x-show="exam.classificacao"
                                  class="text-[10px] text-gray-500 font-medium leading-none"
                                  x-text="exam.classificacao"></span>
                        </div>
                        <p class="mt-1 text-[10px] text-gray-500 leading-snug">
                            <span x-show="exam.material">
                                Material: <span x-text="exam.material"></span>
                            </span>
                            <span x-show="exam.checklist_amostra !== null && exam.checklist_amostra !== undefined && exam.checklist_amostra !== ''">
                                · Amostra:
                                <span x-text="['S', '1', 'SIM'].includes(String(exam.checklist_amostra).toUpperCase()) ? 'Sim' : 'Não'"></span>
                            </span>
                            <span x-show="exam.dt_solicitacao">
                                · Solicitação: <span x-text="exam.dt_solicitacao"></span>
                            </span>
                            <span x-show="exam.scheduled">
                                · Realização: <span x-text="exam.scheduled"></span>
                            </span>
                            <span x-show="exam.dt_coleta">
                                · Coleta: <span x-text="exam.dt_coleta"></span>
                            </span>
                            <span x-show="exam.tempo_pendente">
                                · Pendente há: <span x-text="exam.tempo_pendente"></span>
                            </span>
                            <span x-show="exam.status_laudo">
                                · Laudo: <span x-text="exam.status_laudo"></span>
                            </span>
                            <span x-show="exam.nr_prescricao" class="text-gray-400 font-mono">
                                · #<span x-text="exam.nr_prescricao"></span>
                            </span>
                        </p>
                        <p x-show="exam.prescriber" class="text-[10px] text-gray-400 mt-0.5"><i class="fa-regular fa-user mr-1"></i><span x-text="exam.prescriber"></span></p>
                        <p x-show="exam.resultado_laudo" class="text-[10px] text-emerald-700 font-semibold mt-0.5"><i class="fa-solid fa-flask mr-1 opacity-70"></i><span x-text="exam.resultado_laudo"></span></p>
                        <div class="flex flex-wrap gap-1 mt-1">
                            <span x-show="exam.foi_executado_sem_baixa"
                                  class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                                <i class="fa-solid fa-triangle-exclamation" style="font-size:9px;"></i>
                                Executado sem baixa
                            </span>
                            <span x-show="exam.exame_coletado_em_prescricao_mais_nova"
                                  class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 ring-1 ring-sky-200"
                                  :title="exam.prescricao_mais_nova_pendente_info ? 'Pedido mais recente em aberto: ' + exam.prescricao_mais_nova_pendente_info : 'Pedido mais recente em aberto'">
                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:9px;"></i>
                                Pedido mais recente em aberto
                            </span>
                            <span x-show="exam.prescricao_mais_nova_pendente_info && !exam.exame_coletado_em_prescricao_mais_nova"
                                  class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-indigo-50 text-indigo-700 ring-1 ring-indigo-200">
                                <i class="fa-solid fa-rotate" style="font-size:9px;"></i>
                                Renovado: <span class="font-mono ml-0.5" x-text="exam.prescricao_mais_nova_pendente_info"></span>
                            </span>
                            <span x-show="exam.motivo_pendente"
                                  class="inline-flex items-center gap-1 text-[10px] font-medium px-1.5 py-0.5 rounded bg-gray-50 text-gray-600 ring-1 ring-gray-200">
                                <i class="fa-solid fa-circle-info" style="font-size:9px;"></i>
                                <span x-text="exam.motivo_pendente"></span>
                            </span>
                        </div>
                    </div>
                      <span class="flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded"
                          :class="examBadge(exam.status)"
                          x-text="examStatusPt(exam.status)"></span>
                </div>
            </div>
        </template>
    </div>
    <div x-show="examPages > 1" class="flex items-center justify-center gap-1 pt-3">
        <button @click="examPage = Math.max(1, examPage-1)" :disabled="examPage===1" :class="examPage===1 ? 'opacity-40' : 'hover:bg-gray-100'" class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors"><i class="fa-solid fa-angle-left" style="font-size:10px;"></i></button>
        <template x-for="(p,i) in pageNums(examPages, examPage)" :key="i">
            <template x-if="typeof p === 'number'">
                <button @click="examPage = p" :class="examPage===p ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-blue-50'" class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors" x-text="p"></button>
            </template>
            <template x-if="typeof p === 'string'">
                <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
            </template>
        </template>
        <button @click="examPage = Math.min(examPages, examPage+1)" :disabled="examPage===examPages" :class="examPage===examPages ? 'opacity-40' : 'hover:bg-gray-100'" class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors"><i class="fa-solid fa-angle-right" style="font-size:10px;"></i></button>
    </div>
</div>