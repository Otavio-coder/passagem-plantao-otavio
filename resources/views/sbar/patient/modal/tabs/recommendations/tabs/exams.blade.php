<div x-show="activeRecomendacaoTab === 'tab-exam'" style="display:none;" class="pt-3">
    <p class="text-[10px] font-bold text-blue-700 mb-2">Exames</p>
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
            <div class="bg-white rounded-lg border border-blue-200 px-3 py-2.5 shadow-sm transition-colors"
                 :class="exam.urgente ? 'border-[#7712C7]/40 bg-[#7712C7]/5' : ''">

                {{-- Linha principal: nome + badge --}}
                <div class="flex items-start justify-between gap-3">
                    <div class="flex-1 min-w-0">
                        <div class="flex flex-wrap items-baseline gap-1.5">
                            <p class="text-xs font-semibold leading-snug"
                               :class="exam.urgente ? 'text-[#7712C7]' : 'text-[#062047]'"
                               x-text="exam.name || 'Exame não identificado'"></p>
                            <span x-show="exam.classificacao"
                                  class="text-[10px] text-gray-500 font-medium leading-none"
                                  x-text="exam.classificacao"></span>
                        </div>

                        {{-- Prescritor --}}
                        <p x-show="exam.prescriber" class="text-[10px] text-gray-400 mt-0.5">
                            <i class="fa-regular fa-user mr-1"></i><span x-text="exam.prescriber"></span>
                        </p>

                        {{-- Datas em formato chave-valor --}}
                        <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1.5 text-[10px] text-gray-500">
                            <template x-if="exam.dt_solicitacao">
                                <span>
                                    <span class="font-medium text-gray-600">Prescrição: </span>
                                    <span x-text="exam.dt_solicitacao"></span>
                                </span>
                            </template>
                            <template x-if="exam.scheduled">
                                <span>
                                    <span class="font-medium text-gray-600">Prev. exec.: </span>
                                    <span x-text="exam.scheduled"></span>
                                </span>
                            </template>
                            <template x-if="exam.dt_coleta">
                                <span>
                                    <span class="font-medium text-gray-600">Coleta: </span>
                                    <span x-text="exam.dt_coleta"></span>
                                </span>
                            </template>
                            <template x-if="exam.material">
                                <span>
                                    <span class="font-medium text-gray-600">Material: </span>
                                    <span x-text="exam.material"></span>
                                </span>
                            </template>
                            <template x-if="exam.checklist_amostra !== null && exam.checklist_amostra !== undefined && exam.checklist_amostra !== ''">
                                <span>
                                    <span class="font-medium text-gray-600">Amostra: </span>
                                    <span x-text="['S','1','SIM'].includes(String(exam.checklist_amostra).toUpperCase()) ? 'Sim' : 'Não'"></span>
                                </span>
                            </template>
                            <template x-if="exam.nr_prescricao">
                                <span class="text-gray-400 font-mono">
                                    <span class="font-medium text-gray-600 font-sans">Nr.: </span>
                                    <span x-text="exam.nr_prescricao"></span>
                                </span>
                            </template>
                            <span x-show="exam.tempo_pendente"
                                  x-text="exam.tempo_pendente"
                                  class="font-semibold"
                                  :class="exam.urgente ? 'text-[#7712C7]' : 'text-[#0071B9]'"></span>
                        </div>

                        {{-- Resultado / SCOLA / Tasy --}}
                        <p x-show="exam.resultado_laudo"
                           class="text-[10px] text-emerald-700 font-semibold mt-1">
                            <i class="fa-solid fa-flask mr-1 opacity-70"></i>
                            <span x-text="exam.resultado_laudo"></span>
                        </p>

                        <template x-if="exam.motivo_pendente || exam.scola_status">
                            <div class="mt-1 space-y-0.5">
                                <template x-if="exam.motivo_pendente">
                                    <div class="flex items-center gap-1 text-[10px] text-gray-600">
                                        <x-healthicons-o-health-worker-form class="w-3 h-3 flex-shrink-0 opacity-70" />
                                        <span class="font-medium text-gray-500">Tasy:</span>
                                        <span x-text="exam.motivo_pendente"></span>
                                    </div>
                                </template>
                                <template x-if="exam.scola_status">
                                    <div class="flex items-center gap-1 text-[10px] text-gray-600">
                                        <x-healthicons-o-lab-search class="w-3 h-3 flex-shrink-0 opacity-70" />
                                        <span class="font-medium text-gray-500">SCOLA:</span>
                                        <span x-text="exam.scola_status"></span>
                                    </div>
                                </template>
                            </div>
                        </template>

                        {{-- Badges de situação especial --}}
                        <div class="flex flex-wrap gap-1 mt-1.5">
                            <span x-show="exam.foi_executado_sem_baixa"
                                  class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-amber-50 text-amber-700 ring-1 ring-amber-200">
                                <i class="fa-solid fa-triangle-exclamation" style="font-size:9px;"></i>
                                Executado sem baixa
                            </span>
                            <span x-show="exam.exame_coletado_em_prescricao_mais_nova"
                                  class="inline-flex items-center gap-1 text-[10px] font-semibold px-1.5 py-0.5 rounded bg-sky-50 text-sky-700 ring-1 ring-sky-200">
                                <i class="fa-solid fa-arrow-up-right-from-square" style="font-size:9px;"></i>
                                Coletado em solicitação mais recente
                            </span>
                        </div>
                    </div>

                    {{-- Badge de status --}}
                    <span x-show="exam.status_laudo || exam.status"
                          class="text-[9px] px-1.5 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap mt-0.5"
                          :class="exam.urgente ? 'bg-[#7712C7] text-white' : 'bg-[#004D9D]/10 text-[#004D9D]'"
                          x-text="exam.status_laudo || procStatusPt(exam.status)"></span>
                </div>
            </div>
        </template>
    </div>

    <div x-show="examPages > 1" class="flex items-center justify-center gap-1 pt-3">
        <button @click="examPage = Math.max(1, examPage-1)" :disabled="examPage===1" :class="examPage===1 ? 'opacity-40' : 'hover:bg-gray-100'" class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors"><i class="fa-solid fa-angle-left" style="font-size:10px;"></i></button>
        <template x-for="(p,i) in pageNums(examPages, examPage)" :key="i">
            <span class="contents">
                <template x-if="typeof p === 'number'">
                    <button @click="examPage = p" :class="examPage===p ? 'bg-blue-600 text-white border-blue-600' : 'bg-white text-gray-600 border-gray-200 hover:bg-blue-50'" class="w-8 h-8 rounded-lg border text-[11px] font-bold transition-colors" x-text="p"></button>
                </template>
                <template x-if="typeof p === 'string'">
                    <span class="w-8 text-center text-gray-400 text-sm leading-8">…</span>
                </template>
            </span>
        </template>
        <button @click="examPage = Math.min(examPages, examPage+1)" :disabled="examPage===examPages" :class="examPage===examPages ? 'opacity-40' : 'hover:bg-gray-100'" class="w-8 h-8 rounded-lg border border-gray-200 flex items-center justify-center transition-colors"><i class="fa-solid fa-angle-right" style="font-size:10px;"></i></button>
    </div>
</div>
