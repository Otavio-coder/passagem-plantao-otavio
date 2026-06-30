@props(['patient'])
{{--
    Safelist: mantém classes dinâmicas de grupos de pendências no CSS do Tailwind.
    Geradas por PendingEventHelper::groupStyle() — não remover.
--}}
@once
<div class="hidden" aria-hidden="true">
    <div class="border-gray-300 bg-[#E8E8E8] text-gray-700 bg-[#E8E8E8]/80 border-gray-200"></div>
    <div class="border-[#7712C7]/30 bg-[#7712C7]/10 text-[#7712C7] border-[#7712C7]/20 bg-[#7712C7]/5"></div>
    <div class="border-[#0A4700]/30 bg-[#0A4700]/10 text-[#0A4700] border-[#0A4700]/20 bg-[#0A4700]/5"></div>
    <div class="border-[#BDAD02]/50 bg-[#BDAD02]/10 text-[#5C5300] border-[#BDAD02]/30 bg-[#BDAD02]/5"></div>
    <div class="border-blue-200 bg-blue-50/60 text-blue-700 bg-blue-50/40 border-indigo-200 bg-indigo-50/60 text-indigo-700 bg-indigo-50/40"></div>
</div>
@endonce
<div class="flex-1 min-h-0 px-2 sm:px-2.5 lg:px-3 overflow-hidden flex flex-col"
     x-data="{
         showPendingModal: false,
         cardSlide: 0,
         pendingGroups: null,
         pendingLoading: false,
         sectorExecFallback: '{{ e($patient['sector_exec_fallback'] ?? 'Setor não informado') }}',
         async openPendingModal() {
             this.showPendingModal = true;
             document.body.style.overflow = 'hidden';
             if (this.pendingGroups === null) {
                 this.pendingLoading = true;
                 this.pendingGroups = await $wire.getPatientPendingGroups({{ (int) ($patient['nr_atendimento'] ?? 0) }});
                 this.pendingLoading = false;
             }
         }
     }">

    @if(!empty($patient['first_pending_event']) || !empty($patient['latest_evaluation']['content'] ?? null))
        <div class="rounded-lg p-1.5 border {{ !empty($patient['first_pending_event']) ? '' : 'bg-blue-50/60 border-blue-200' }}"
             @if(!empty($patient['first_pending_event']) && !empty($patient['first_pending_style']['card_style'])) style="{{ $patient['first_pending_style']['card_style'] }}" @endif>
            <div class="flex items-center justify-between mb-1">
                <span class="text-[11px] font-bold tracking-wide text-[#004D9D]">
                    @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                        Pendências / Avaliação
                    @elseif(!empty($patient['first_pending_event']))
                        Pendências
                    @else
                        Avaliação
                    @endif
                </span>
                <div class="flex items-center gap-1.5">
                    @if(($patient['pending_modal_meta']['all_count'] ?? 0) > 0)
                        <button
                            @click="openPendingModal()"
                            class="inline-flex items-center rounded-md border border-[#004D9D]/25 bg-[#004D9D]/10 px-2 py-0.5
                                   text-[10px] font-medium text-[#004D9D] hover:bg-[#004D9D]/20 transition-colors cursor-pointer"
                            title="Ver todas as pendências"
                        >
                            Ver todas
                        </button>
                    @endif

                    @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                        <button class="w-5 h-5 flex items-center justify-center rounded-full bg-[#004D9D]/10 text-[#004D9D] hover:bg-[#004D9D]/20 transition-colors"
                                title="Anterior"
                                @click="cardSlide = cardSlide === 0 ? 1 : 0">
                            <x-iconoir-nav-arrow-left class="h-3.5 w-3.5" />
                        </button>
                        <button class="w-5 h-5 flex items-center justify-center rounded-full bg-[#004D9D]/10 text-[#004D9D] hover:bg-[#004D9D]/20 transition-colors"
                                title="Próximo"
                                @click="cardSlide = cardSlide === 0 ? 1 : 0">
                            <x-iconoir-nav-arrow-right class="h-3.5 w-3.5" />
                        </button>
                    @endif
                </div>
            </div>

            @if(!empty($patient['first_pending_event']))
                <div x-show="{{ !empty($patient['latest_evaluation']['content'] ?? null) ? 'cardSlide === 0' : 'true' }}" class="flex items-start gap-1.5" x-transition>
                    <img
                        src="{{ asset('images/icons/patient-card/' . ($patient['first_pending_style']['icon'] ?? 'alert-circle.svg')) }}"
                        class="w-3.5 h-3.5 flex-shrink-0 mt-0.5 opacity-90"
                        alt=""
                    />
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] {{ $patient['first_pending_style']['description_class'] ?? 'text-[#062047] font-semibold' }} leading-tight line-clamp-2">
                            {{ $patient['first_pending_event']['descricao'] ?? 'Sem descrição' }}
                        </div>
                        <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                            @if(!empty($patient['first_pending_event']['dt_evento_formatted']))
                                <span class="text-[9px] {{ $patient['first_pending_style']['time_class'] ?? 'text-[#004D9D] font-medium' }}">
                                    {{ $patient['first_pending_event']['dt_evento_formatted'] }}
                                </span>
                            @elseif(!empty($patient['first_pending_event']['dt_solicitacao']))
                                <span class="text-[9px] text-gray-500">
                                    {{ $patient['first_pending_event']['dt_solicitacao'] }}
                                </span>
                            @endif
                            @if(!empty($patient['first_pending_event']['nr_prescricao']))
                                <span class="text-[9px] text-gray-400 font-mono">
                                    #{{ $patient['first_pending_event']['nr_prescricao'] }}
                                </span>
                            @endif
                            @if(!empty($patient['first_pending_event']['tempo_pendente']))
                                <span class="text-[9px] text-gray-500">
                                    · {{ $patient['first_pending_event']['tempo_pendente'] }}
                                </span>
                            @endif
                        </div>
                        @if(!empty($patient['first_pending_event']['motivo_pendente']))
                            <div class="flex items-center gap-1 mt-0.5 text-[9px] text-gray-400 leading-tight">
                                <x-heroicon-o-information-circle class="w-2.5 h-2.5 flex-shrink-0 opacity-60" />
                                <span>{{ $patient['first_pending_event']['motivo_pendente'] }}</span>
                            </div>
                        @endif
                        @if(!empty($patient['first_pending_event']['scola_status']) && empty($patient['first_pending_event']['scola_integration_issue']))
                            <div class="flex items-center gap-1 mt-0.5 text-[9px] text-teal-600 leading-tight">
                                <x-healthicons-o-lab-search class="w-2.5 h-2.5 flex-shrink-0 opacity-70" />
                                <span>Scola: {{ $patient['first_pending_event']['scola_status'] }}</span>
                            </div>
                        @endif
                    </div>
                    @if($patient['first_pending_style']['show_pulse'] ?? false)
                        <span class="w-1.5 h-1.5 rounded-full {{ $patient['first_pending_style']['pulse_color'] ?? 'bg-gray-400' }} animate-pulse flex-shrink-0 mt-1"></span>
                    @endif
                </div>
            @endif

            @if(!empty($patient['latest_evaluation']['content'] ?? null))
                <div x-show="{{ !empty($patient['first_pending_event']) ? 'cardSlide === 1' : 'true' }}" {{ !empty($patient['first_pending_event']) ? 'style="display:none"' : '' }} class="flex items-start gap-1.5" x-transition>
                    <x-ui.user-avatar
                        :photo="$patient['latest_evaluation']['photo'] ?? null"
                        :name="$patient['latest_evaluation']['user_name'] ?? 'U'"
                        class="w-4 h-4 flex-shrink-0 mt-0.5"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="text-[10px] text-blue-800 font-medium leading-tight line-clamp-2">
                            {{ $patient['latest_evaluation']['content'] ?? '-' }}
                        </div>
                        <div class="flex items-center gap-1 mt-0.5 flex-wrap">
                            @if(!empty($patient['latest_evaluation']['created_at_formatted']))
                                <span class="text-[9px] text-blue-700 font-medium">
                                    {{ $patient['latest_evaluation']['created_at_formatted'] }}
                                </span>
                            @endif
                            @if(!empty($patient['latest_evaluation']['user_name']))
                                <span class="text-[9px] text-blue-700">
                                    · {{ $patient['latest_evaluation']['user_name'] }}
                                </span>
                            @endif
                        </div>
                    </div>
                </div>
            @endif

            @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                <div class="mt-1 flex justify-center gap-1">
                    <span class="h-1 w-1 rounded-full" :class="cardSlide === 0 ? 'bg-[#004D9D]' : 'bg-gray-300'"></span>
                    <span class="h-1 w-1 rounded-full" :class="cardSlide === 1 ? 'bg-[#004D9D]' : 'bg-gray-300'"></span>
                </div>
            @endif
        </div>

    @else
        <div class="flex items-center justify-center h-full w-full">
            <div class="text-center py-1.5">
                <x-iconoir-walking class="text-gray-400 h-4 w-4 mx-auto" />
                <p class="text-[11px] text-gray-500 font-medium">Sem pendências</p>
                <p class="text-[9px] text-gray-400 mt-0.5">Nenhum evento pendente registrado para este paciente.</p>
            </div>
        </div>
    @endif

    {{-- Modal: todas as pendências --}}
    <div x-show="showPendingModal"
         x-cloak
         class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="showPendingModal = false; document.body.style.overflow = ''"
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showPendingModal = false; document.body.style.overflow = ''"></div>

        <div data-pending-modal-panel
             class="relative w-full h-full sm:w-[760px] sm:h-auto sm:max-w-[95vw] sm:max-h-[90vh] bg-white rounded-none sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
             @click.stop
             x-show="showPendingModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <x-heroicon-o-clipboard-document-list class="w-5 h-5 text-white flex-shrink-0" />
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-white leading-tight">Pendências do Paciente</h3>
                        <p class="text-white/70 text-xs leading-tight truncate">{{ $patient['nm_pessoa_fisica'] ?? '' }}</p>
                    </div>
                </div>
                <button @click="showPendingModal = false; document.body.style.overflow = ''"
                        title="Fechar"
                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>


            <div class="flex-1 overflow-y-auto min-h-0 p-3 space-y-3">

                {{-- Loading state --}}
                <div x-show="pendingLoading" class="flex flex-col items-center justify-center py-10 gap-3">
                    <div class="h-5 w-5 rounded-full bg-gray-200 animate-pulse"></div>
                    <div class="h-2 w-36 bg-gray-200 rounded animate-pulse"></div>
                    <div class="h-2 w-28 bg-gray-100 rounded animate-pulse"></div>
                </div>

                {{-- Empty state (always in DOM but hidden — contains Blade icon components) --}}
                <div x-show="!pendingLoading && pendingGroups !== null && pendingGroups.length === 0"
                     class="rounded-xl border border-gray-200 bg-gray-50/60 p-6 text-center">
                    <x-iconoir-walking class="text-gray-400 h-5 w-5 mx-auto" />
                    <p class="text-xs text-gray-500 font-medium mt-2">Nenhuma pendência registrada.</p>
                    <p class="text-[10px] text-gray-400 mt-1">Nenhum evento pendente para este paciente.</p>
                </div>

                {{-- Groups — loaded on demand when modal opens --}}
                <template x-if="!pendingLoading && pendingGroups !== null && pendingGroups.length > 0">
                    <div class="space-y-3">
                        <template x-for="(group, gi) in pendingGroups" :key="gi">
                            <div x-data="{
                                    allItems: group.events,
                                    page: 1,
                                    perPage: 8,
                                    calcPerPage() {
                                        const modal = document.querySelector('[data-pending-modal-panel]');
                                        const modalHeight = modal ? modal.clientHeight : window.innerHeight;
                                        const reservedSpace = 430;
                                        const itemHeight = 96;
                                        const computed = Math.floor((modalHeight - reservedSpace) / itemHeight);
                                        this.perPage = Math.max(3, Math.min(10, computed || 8));
                                        if (this.page > this.pages) this.page = this.pages;
                                    },
                                    get items() { return this.allItems; },
                                    get paged() { return this.items.slice((this.page-1)*this.perPage, this.page*this.perPage); },
                                    get pages() { return Math.max(1, Math.ceil(this.items.length / this.perPage)); }
                                 }"
                                 x-init="calcPerPage()"
                                 @resize.window="calcPerPage()"
                                 x-show="allItems.length > 0"
                                 :class="['rounded-xl border overflow-hidden', group.style.border_header]">

                                <div :class="['flex items-center justify-between px-3 py-2 border-b', group.style.bg_header, group.style.border_header]">
                                    <span :class="['text-xs font-bold', group.style.text_header]" x-text="group.label || 'Pendências'"></span>
                                </div>

                                <div class="divide-y divide-gray-100/80">
                                    <template x-for="(ev, idx) in paged" :key="idx">
                                        <div class="px-3 py-2.5 hover:brightness-95 transition-all"
                                             :class="[group.style.bg_card, ev.urgente ? 'bg-[#7712C7]/10' : '']">
                                            <div class="flex items-start gap-2">
                                                <img
                                                    :src="'/images/icons/patient-card/' + (ev.icone || 'alert-circle.svg')"
                                                    class="w-4 h-4 flex-shrink-0 mt-0.5 opacity-80"
                                                    alt=""
                                                >
                                                <div class="flex-1 min-w-0">
                                                    <div class="text-xs font-semibold leading-snug"
                                                         :class="ev.urgente ? 'text-[#7712C7]' : 'text-[#062047]'"
                                                         x-text="ev.descricao || 'Sem descrição'"></div>
                                                    <div x-show="ev.ds_subtipo || ev.nm_prescritor"
                                                         class="text-[10px] text-gray-500 mt-0.5 flex flex-wrap gap-x-2">
                                                        <span x-show="ev.ds_subtipo" x-text="ev.ds_subtipo"></span>
                                                        <span x-show="ev.nm_prescritor_display || ev.nm_prescritor" x-text="'· ' + (ev.nm_prescritor_display || ev.nm_prescritor)" class="text-gray-400"></span>
                                                    </div>
                                                </div>
                                                <span x-show="ev.status_laudo"
                                                      x-text="ev.status_laudo"
                                                      class="text-[9px] px-1.5 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap"
                                                      :class="ev.urgente ? 'bg-[#7712C7] text-white' : 'bg-[#004D9D]/10 text-[#004D9D]'"></span>
                                            </div>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1.5 text-[10px] text-gray-500">
                                                <template x-if="ev.dt_evento_formatted">
                                                    <span>
                                                        <span class="font-medium text-gray-600">Prev. exec.: </span>
                                                        <span x-text="ev.dt_evento_formatted"></span>
                                                    </span>
                                                </template>
                                                <template x-if="ev.dt_solicitacao">
                                                    <span>
                                                        <span class="font-medium text-gray-600">Prescrição: </span>
                                                        <span x-text="ev.dt_solicitacao"></span>
                                                    </span>
                                                </template>
                                                <template x-if="ev.dt_autorizacao">
                                                    <span>
                                                        <span class="font-medium text-gray-600">Lib. prescrição: </span>
                                                        <span x-text="ev.dt_autorizacao"></span>
                                                    </span>
                                                </template>
                                                <template x-if="['exame','proc_exame'].includes(ev.tipo) && ev.dt_liberacao_medico">
                                                    <span>
                                                        <span class="font-medium text-gray-600">Lib. médica: </span>
                                                        <span x-text="ev.dt_liberacao_medico"></span>
                                                    </span>
                                                </template>
                                                <template x-if="ev.nr_prescricao">
                                                    <span>
                                                        <span class="font-medium text-gray-600">Nr. prescrição: </span>
                                                        <span x-text="ev.nr_prescricao"></span>
                                                    </span>
                                                </template>
                                                <template x-if="ev.dt_coleta">
                                                    <span>
                                                        <span class="font-medium text-gray-600">Coleta: </span>
                                                        <span x-text="ev.dt_coleta"></span>
                                                    </span>
                                                </template>
                                                <span x-show="ev.tempo_pendente"
                                                      x-text="ev.tempo_pendente"
                                                      class="font-semibold"
                                                      :class="ev.urgente ? 'text-[#7712C7]' : 'text-[#0071B9]'"></span>
                                                <span x-show="['cirurgia','hemoterapia','quimioterapia'].includes(ev.tipo)"
                                                      class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                                    <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                                    <span x-text="ev.setor_execucao || sectorExecFallback"></span>
                                                </span>
                                                <span x-show="ev.ds_complemento && ev.tipo !== 'antibiotico'"
                                                      x-text="ev.ds_complemento"
                                                      class="text-gray-500 italic"></span>
                                            </div>
                                            <template x-if="(ev.motivo_pendente && !['alta','alta_medica'].includes(ev.tipo)) || ev.scola_status || (ev.tipo === 'alta_medica' && (ev.nm_prescritor_display || ev.nm_prescritor))">
                                                <div class="mt-1 space-y-0.5">
                                                    <template x-if="ev.motivo_pendente && !['alta','alta_medica'].includes(ev.tipo) && ev.motivo_pendente !== ev.status_laudo">
                                                        <div class="flex items-center gap-1 text-[10px] text-gray-600">
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                            <span x-text="ev.motivo_pendente"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="['exame','proc_exame'].includes(ev.tipo) && ev.scola_status && !ev.scola_integration_issue">
                                                        <div class="flex items-center gap-1 text-[10px] text-gray-600">
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg>
                                                            <span class="font-medium text-gray-500">SCOLA:</span>
                                                            <span x-text="ev.scola_status"></span>
                                                        </div>
                                                    </template>
                                                    <template x-if="ev.tipo === 'alta_medica' && (ev.nm_prescritor_display || ev.nm_prescritor)">
                                                        <div class="flex items-center gap-1 text-[10px] text-gray-600">
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                                            <span class="font-medium text-gray-500">Registrado por:</span>
                                                            <span x-text="ev.nm_prescritor_display || ev.nm_prescritor"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>
                                        </div>
                                    </template>
                                </div>

                                <div x-show="pages > 1"
                                     :class="['flex items-center justify-end px-3 py-2 border-t', group.style.border_header, group.style.bg_header]">
                                    <div class="flex items-center gap-1">
                                        <button @click="if(page > 1) page--"
                                                :disabled="page === 1"
                                                :class="['w-6 h-6 flex items-center justify-center rounded border disabled:opacity-30 hover:bg-black/10 transition-all', group.style.border_header]">
                                            <svg :class="['w-3 h-3', group.style.text_header]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M15 18l-6-6 6-6"/></svg>
                                        </button>
                                        <span :class="['text-[11px] tabular-nums font-medium px-1', group.style.text_header]" x-text="page + '/' + pages"></span>
                                        <button @click="if(page < pages) page++"
                                                :disabled="page >= pages"
                                                :class="['w-6 h-6 flex items-center justify-center rounded border disabled:opacity-30 hover:bg-black/10 transition-all', group.style.border_header]">
                                            <svg :class="['w-3 h-3', group.style.text_header]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M9 18l6-6-6-6"/></svg>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>
    </div>
</div>
