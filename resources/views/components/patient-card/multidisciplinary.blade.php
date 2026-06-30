@props(['patient'])
@php
    $hasAnyTeam = !empty(array_filter($patient['multidisciplinary'] ?? []));
@endphp
@if($hasAnyTeam || !empty($patient['multidisciplinary_other'] ?? null))
    <div x-data="{
            showMdModal: false,
            requests: null,
            loading: false,
            async openModal() {
                this.showMdModal = true;
                document.body.style.overflow = 'hidden';
                if (this.requests === null) {
                    this.loading = true;
                    this.requests = await $wire.getPatientMultiRequests({{ (int) ($patient['nr_atendimento'] ?? 0) }});
                    this.loading = false;
                }
            }
         }"
         class="flex-shrink-0 px-2 sm:px-2.5 md:px-3 lg:px-3 pb-1.5 md:pb-2 lg:pb-1.5">
        <div
            class="bg-white/70 rounded-lg px-2 py-1 shadow-sm cursor-pointer hover:bg-blue-50 transition-colors"
            @click="openModal()"
            title="Clique para ver solicitações multidisciplinares"
        >
            <div class="flex flex-wrap justify-center text-[10px] md:text-xs lg:text-[10px] text-gray-700 gap-x-2 md:gap-x-3 lg:gap-x-2 gap-y-0.5 items-center">
                @if(($patient['multidisciplinary']['fisioterapia'] ?? false))
                    <span class="flex items-center gap-0.5 text-green-700 font-bold">
                        <x-ui.patient-icon name="fisioterapia" class="w-3.5 h-3.5 md:w-4 md:h-4 lg:w-3.5 lg:h-3.5 text-black" />
                        Fisio
                    </span>
                @else
                    <span class="text-gray-400">Fisio(–)</span>
                @endif
                @if(($patient['multidisciplinary']['psicologia'] ?? false))
                    <span class="flex items-center gap-0.5 text-green-700 font-bold">
                        <x-ui.patient-icon name="psicologia" class="w-3.5 h-3.5 md:w-4 md:h-4 lg:w-3.5 lg:h-3.5 text-black" />
                        Psico
                    </span>
                @else
                    <span class="text-gray-400">Psico(–)</span>
                @endif
                @if(($patient['multidisciplinary']['nutricao'] ?? false))
                    <span class="flex items-center gap-0.5 text-green-700 font-bold">
                        <x-ui.patient-icon name="nutricao" class="w-3.5 h-3.5 md:w-4 md:h-4 lg:w-3.5 lg:h-3.5 text-black" />
                        Nutri
                    </span>
                @else
                    <span class="text-gray-400">Nutri(–)</span>
                @endif
                @if(($patient['multidisciplinary']['fonoaudiologia'] ?? false))
                    <span class="flex items-center gap-0.5 text-green-700 font-bold">
                        <x-ui.patient-icon name="fonoaudiologia" class="w-3.5 h-3.5 md:w-4 md:h-4 lg:w-3.5 lg:h-3.5 text-black" />
                        Fono
                    </span>
                @else
                    <span class="text-gray-400">Fono(–)</span>
                @endif
                @if(($patient['multidisciplinary']['servico_social'] ?? false))
                    <span class="flex items-center gap-0.5 text-green-700 font-bold">
                        <x-ui.patient-icon name="servico-social" class="w-3.5 h-3.5 md:w-4 md:h-4 lg:w-3.5 lg:h-3.5 text-black" />
                        SS
                    </span>
                @else
                    <span class="text-gray-400">SS(–)</span>
                @endif
                @if(($patient['multidisciplinary']['acessos_vasculares'] ?? false))
                    <span class="flex items-center gap-0.5 text-green-700 font-bold">
                        <x-ui.patient-icon name="catheter-svgrepo-com" class="w-3.5 h-3.5 md:w-4 md:h-4 lg:w-3.5 lg:h-3.5 text-black" />
                        Time
                    </span>
                @else
                    <span class="text-gray-400">Time(–)</span>
                @endif
            </div>
            @if(!empty($patient['multidisciplinary_other'] ?? null))
                <div class="text-[10px] text-gray-600 text-center mt-0.5">
                    Outro: <span class="text-gray-800 font-medium">{{ $patient['multidisciplinary_other'] }}</span>
                </div>
            @endif
            <div class="text-[9px] text-center text-blue-600 mt-0.5">Clique para ver solicitações</div>
        </div>

        <div x-show="showMdModal"
             x-cloak
             @click.self="showMdModal = false; document.body.style.overflow = ''"
             @keydown.escape.window="showMdModal = false; document.body.style.overflow = ''"
             class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
             style="margin: 0 !important;"
        >
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showMdModal = false; document.body.style.overflow = ''"></div>
            <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] sm:w-[650px] flex flex-col"
                 @click.stop
                 x-show="showMdModal"
                 x-transition:enter="transition ease-out duration-300"
                 x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-200"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-heroicon-o-user-group class="w-5 h-5 text-white flex-shrink-0" />
                        <h3 class="text-base font-bold text-white">Solicitações de Parecer / Consultorias</h3>
                    </div>
                    <button @click="showMdModal = false; document.body.style.overflow = ''" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>
                <div class="flex-1 overflow-y-auto min-h-0 max-h-[400px] md:max-h-[520px] p-4 bg-gray-50">

                    <div x-show="loading" class="flex flex-col items-center justify-center py-10 gap-3">
                        <div class="h-5 w-5 rounded-full bg-gray-200 animate-pulse"></div>
                        <div class="h-2 w-36 bg-gray-200 rounded animate-pulse"></div>
                    </div>

                    <div x-show="!loading && requests !== null && requests.length === 0"
                         class="flex flex-col items-center justify-center py-8 text-gray-500">
                        <x-heroicon-o-check-circle class="w-12 h-12 text-gray-300 mb-2" />
                        <p class="text-sm">Nenhuma solicitação registrada</p>
                    </div>

                    <template x-if="!loading && requests !== null && requests.length > 0">
                        <div class="space-y-3">
                            <template x-for="(request, i) in requests" :key="i">
                                <div :class="['border rounded-lg p-4', request.card_class ?? 'bg-amber-50 border-amber-200']">
                                    <div class="flex justify-between items-start mb-3">
                                        <div class="flex items-center gap-2 flex-1 min-w-0">
                                            <img x-show="request.team_icon"
                                                 :src="'/images/icons/patient-card/' + request.team_icon"
                                                 class="w-5 h-5 flex-shrink-0"
                                                 alt="" />
                                            <span class="text-sm font-semibold text-gray-800" x-text="request.ds_equipe_destino ?? 'Equipe não identificada'"></span>
                                        </div>
                                        <span :class="['text-xs px-2.5 py-1 rounded-full flex-shrink-0 ml-2', request.status_badge_class ?? 'bg-amber-500 text-white']"
                                              x-text="request.ds_status ?? request.ie_status ?? '-'"></span>
                                    </div>
                                    <div class="text-xs text-gray-600 mb-3 space-y-1">
                                        <div><strong>Profissional requisitante:</strong> <span x-text="request.nm_requisitante_display ?? request.nm_requisitante ?? 'Não informado'"></span></div>
                                        <div><strong>Data do registro:</strong> <span x-text="request.dt_registro_formatted ?? 'N/A'"></span></div>
                                        <template x-if="request.dt_liberacao_formatted">
                                            <div><strong>Data liberação:</strong> <span x-text="request.dt_liberacao_formatted"></span></div>
                                        </template>
                                    </div>
                                    <template x-if="request.ds_motivo_consulta">
                                        <div class="bg-white rounded-lg p-3 mb-3 border border-gray-200 shadow-sm">
                                            <div class="text-[10px] font-semibold text-gray-500 mb-1">Pedido / Motivo da Consulta:</div>
                                            <div class="text-sm text-gray-800 whitespace-pre-line" x-text="request.ds_motivo_consulta"></div>
                                        </div>
                                    </template>
                                    <template x-if="request.ds_parecer">
                                        <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                                            <div class="text-[10px] font-semibold text-green-700 mb-1">Resposta / Parecer:</div>
                                            <div class="text-sm text-gray-800 whitespace-pre-line" x-text="request.ds_parecer"></div>
                                            <template x-if="request.nm_responsavel_resposta">
                                                <div class="text-xs text-gray-600 mt-2 pt-2 border-t border-green-200">
                                                    <strong>Respondido por:</strong>
                                                    <span x-text="request.nm_responsavel_resposta_display ?? request.nm_responsavel_resposta"></span>
                                                    <template x-if="request.dt_resposta_formatted">
                                                        <span> em <span x-text="request.dt_resposta_formatted"></span></span>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>
                            </template>
                        </div>
                    </template>

                </div>
            </div>
        </div>
    </div>
@endif
