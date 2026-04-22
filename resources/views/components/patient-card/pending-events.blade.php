@props(['patient'])
<div class="flex-1 min-h-0 px-2 sm:px-2.5 lg:px-3 overflow-hidden flex flex-col"
     x-data="{ showPendingModal: false, pendingShowAll: false, cardSlide: 0 }"
     @pending-filter.window="pendingShowAll = $event.detail.v">

    @if(!empty($patient['first_pending_event']) || !empty($patient['latest_evaluation']['content'] ?? null))
        <div class="rounded-lg p-2 border {{ !empty($patient['first_pending_event']) ? '' : 'bg-blue-50/60 border-blue-200' }}"
             @if(!empty($patient['first_pending_event']) && !empty($patient['first_pending_style']['card_style'])) style="{{ $patient['first_pending_style']['card_style'] }}" @endif>
            <div class="flex items-center justify-between mb-1.5">
                <span class="text-[12px] font-bold tracking-wide text-[#004D9D]">
                    @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                        Pendências / Avaliação
                    @elseif(!empty($patient['first_pending_event']))
                        Pendências
                    @else
                        Avaliação
                    @endif
                </span>
                <div class="flex items-center gap-1.5">
                    @if(!empty($patient['pending_events'] ?? []))
                        <button
                            @click="showPendingModal = true; document.body.style.overflow = 'hidden'; $dispatch('pending-filter', { v: false })"
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
                <div x-show="{{ !empty($patient['latest_evaluation']['content'] ?? null) ? 'cardSlide === 0' : 'true' }}" class="flex items-start gap-2" x-transition>
                    <img
                        src="{{ asset('images/icons/patient-card/' . ($patient['first_pending_style']['icon'] ?? 'alert-circle.svg')) }}"
                        class="w-4 h-4 flex-shrink-0 mt-0.5 opacity-90"
                        alt=""
                    />
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] {{ $patient['first_pending_style']['description_class'] ?? 'text-[#062047] font-semibold' }} leading-tight line-clamp-2">
                            {{ $patient['first_pending_event']['descricao'] ?? 'Sem descrição' }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                            @if(!empty($patient['first_pending_event']['dt_evento_formatted']))
                                <span class="text-[9px] {{ $patient['first_pending_style']['time_class'] ?? 'text-[#004D9D] font-medium' }}">
                                    {{ $patient['first_pending_event']['dt_evento_formatted'] }}
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
                    </div>
                    @if($patient['first_pending_style']['show_pulse'] ?? false)
                        <span class="w-2 h-2 rounded-full {{ $patient['first_pending_style']['pulse_color'] ?? 'bg-gray-400' }} animate-pulse flex-shrink-0 mt-1"></span>
                    @endif
                </div>
            @endif

            @if(!empty($patient['latest_evaluation']['content'] ?? null))
                <div x-show="{{ !empty($patient['first_pending_event']) ? 'cardSlide === 1' : 'true' }}" {{ !empty($patient['first_pending_event']) ? 'style="display:none"' : '' }} class="flex items-start gap-2" x-transition>
                    <x-ui.user-avatar
                        :photo="$patient['latest_evaluation']['photo'] ?? null"
                        :name="$patient['latest_evaluation']['user_name'] ?? 'U'"
                        class="w-5 h-5 flex-shrink-0 mt-0.5"
                    />
                    <div class="flex-1 min-w-0">
                        <div class="text-[11px] text-blue-800 font-medium leading-tight line-clamp-2">
                            {{ $patient['latest_evaluation']['content'] ?? '-' }}
                        </div>
                        <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
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
                <div class="mt-1.5 flex justify-center gap-1">
                    <span class="h-1.5 w-1.5 rounded-full" :class="cardSlide === 0 ? 'bg-[#004D9D]' : 'bg-gray-300'"></span>
                    <span class="h-1.5 w-1.5 rounded-full" :class="cardSlide === 1 ? 'bg-[#004D9D]' : 'bg-gray-300'"></span>
                </div>
            @endif
        </div>

    @else
        <div class="flex items-center justify-center h-full w-full">
            <div class="text-center py-2">
                <x-iconoir-walking class="text-gray-400 h-5 w-5 mx-auto" />
                <p class="text-xs text-gray-500 font-medium">Sem pendências</p>
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

            <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50/80 flex-shrink-0">
                <span class="text-[10px] text-gray-500 font-semibold uppercase tracking-wide mr-1">Período:</span>
                <button @click="$dispatch('pending-filter', { v: false })"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap"
                        :class="!pendingShowAll ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'">
                    Ontem – Hoje – Amanhã
                </button>
                <button @click="$dispatch('pending-filter', { v: true })"
                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap"
                        :class="pendingShowAll ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'">
                    Todas as Pendências
                </button>
            </div>
            <div class="px-3 py-1.5 border-b border-gray-100 bg-gray-50/60 flex-shrink-0">
                <p class="text-[10px] text-gray-500 leading-tight">
                    Filtro padrão: janela de 1 dia (ontem, hoje e amanhã) + itens priorizados do sistema.
                </p>
            </div>

            <div class="flex-1 overflow-y-auto min-h-0 p-3 space-y-3">
                <div x-show="pendingShowAll ? {{ (($patient['pending_modal_meta']['all_count'] ?? 0) === 0) ? 'true' : 'false' }} : {{ (($patient['pending_modal_meta']['near_count'] ?? 0) === 0) ? 'true' : 'false' }}"
                     class="rounded-xl border border-gray-200 bg-gray-50/60 p-6 text-center">
                    <x-iconoir-walking class="text-gray-400 h-5 w-5 mx-auto" />
                    <p class="text-xs text-gray-500 font-medium mt-2">Nenhuma pendência para este filtro.</p>
                    <p class="text-[10px] text-gray-400 mt-1">Troque o período para visualizar outros itens.</p>
                </div>

                @foreach(($patient['pending_groups'] ?? []) as $group)
                    <div x-data="{
                            allItems: @js(array_values($group['events'] ?? [])),
                            showAll: false,
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
                            get items() {
                                return this.showAll
                                    ? this.allItems
                                    : this.allItems.filter(i => i.is_near);
                            },
                            get paged() {
                                return this.items.slice((this.page-1)*this.perPage, this.page*this.perPage);
                            },
                            get pages() {
                                return Math.max(1, Math.ceil(this.items.length / this.perPage));
                            }
                         }"
                         x-init="calcPerPage()"
                         @resize.window="calcPerPage()"
                         @pending-filter.window="showAll = $event.detail.v; page = 1"
                         x-show="items.length > 0"
                         class="rounded-xl border {{ $group['style']['border_header'] ?? 'border-gray-200' }} overflow-hidden">

                        <div class="flex items-center justify-between px-3 py-2 {{ $group['style']['bg_header'] ?? 'bg-white/30' }} border-b {{ $group['style']['border_header'] ?? 'border-gray-200' }}">
                            <span class="text-xs font-bold {{ $group['style']['text_header'] ?? 'text-[#062047]' }} uppercase tracking-wide">
                                {{ $group['label'] ?? 'Pendências' }}
                            </span>
                        </div>

                        <div class="divide-y divide-gray-100/80">
                            <template x-for="(ev, idx) in paged" :key="idx">
                                <div class="px-3 py-2.5 hover:brightness-95 transition-all {{ $group['style']['bg_card'] ?? 'bg-gray-50/50' }}"
                                     :class="{ 'bg-[#7712C7]/10': ev.urgente }">
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
                                                <span class="font-medium text-gray-600">Previsto: </span>
                                                <span x-text="ev.dt_evento_formatted"></span>
                                            </span>
                                        </template>
                                        <template x-if="ev.dt_solicitacao">
                                            <span>
                                                <span class="font-medium text-gray-600">Solicitado: </span>
                                                <span x-text="ev.dt_solicitacao"></span>
                                            </span>
                                        </template>
                                        <template x-if="ev.dt_autorizacao">
                                            <span>
                                                <span class="font-medium text-gray-600">Liberado: </span>
                                                <span x-text="ev.dt_autorizacao"></span>
                                            </span>
                                        </template>
                                        <template x-if="ev.nr_prescricao">
                                            <span>
                                                <span class="font-medium text-gray-600">Prescrição: </span>
                                                <span x-text="ev.nr_prescricao"></span>
                                            </span>
                                        </template>
                                        <template x-if="ev.dt_coleta">
                                            <span>
                                                <span class="font-medium text-gray-600">Coletado: </span>
                                                <span x-text="ev.dt_coleta"></span>
                                            </span>
                                        </template>
                                        <span x-show="ev.tempo_pendente"
                                              x-text="ev.tempo_pendente"
                                              class="font-semibold"
                                              :class="ev.urgente ? 'text-[#7712C7]' : 'text-[#0071B9]'"></span>
                                        <span x-show="['cirurgia','hemoterapia','quimioterapia'].includes(ev.tipo) && (ev.setor_execucao || true)"
                                              class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                            <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                            <span x-text="ev.setor_execucao || '{{ $patient['sector_exec_fallback'] ?? 'Setor não informado' }}'"></span>
                                        </span>
                                        <span x-show="ev.ds_complemento && ev.tipo !== 'antibiotico'"
                                              x-text="ev.ds_complemento"
                                              class="text-gray-500 italic"></span>
                                    </div>
                                    <template x-if="ev.motivo_pendente">
                                        <div class="mt-1 flex items-center gap-1 text-[10px]"
                                             :class="{
                                                'text-orange-700': ev.foi_executado_sem_baixa || ev.exame_coletado_em_prescricao_mais_nova,
                                                'text-gray-500': !(ev.foi_executado_sem_baixa || ev.exame_coletado_em_prescricao_mais_nova)
                                             }">
                                            <x-heroicon-o-information-circle class="w-3 h-3 flex-shrink-0" />
                                            <span x-text="ev.motivo_pendente"></span>
                                        </div>
                                    </template>
                                </div>
                            </template>

                            <div x-show="items.length === 0"
                                 class="px-3 py-4 text-center">
                                <p class="text-[11px] text-gray-400">Nenhum item nos próximos 3 dias.</p>
                                <button @click="$dispatch('pending-filter', { v: true })"
                                        class="text-[11px] text-[#004D9D] font-semibold underline mt-1">
                                    Ver todas
                                </button>
                            </div>
                        </div>

                        <div x-show="pages > 1"
                             class="flex items-center justify-between px-3 py-2 border-t {{ $group['style']['border_header'] ?? 'border-gray-200' }} {{ $group['style']['bg_header'] ?? 'bg-white/30' }}">
                            <button @click="if(page > 1) page--"
                                    :disabled="page === 1"
                                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-lg
                                           bg-white/70 border {{ $group['style']['border_header'] ?? 'border-gray-200' }} {{ $group['style']['text_header'] ?? 'text-[#062047]' }}
                                           disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white transition-colors">
                                <x-heroicon-o-chevron-left class="w-3 h-3" />
                                Anterior
                            </button>
                            <span class="text-[10px] {{ $group['style']['text_header'] ?? 'text-[#062047]' }} font-medium">
                                pág. <span x-text="page"></span> / <span x-text="pages"></span>
                            </span>
                            <button @click="if(page < pages) page++"
                                    :disabled="page >= pages"
                                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-lg
                                           bg-white/70 border {{ $group['style']['border_header'] ?? 'border-gray-200' }} {{ $group['style']['text_header'] ?? 'text-[#062047]' }}
                                           disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white transition-colors">
                                Próxima
                                <x-heroicon-o-chevron-right class="w-3 h-3" />
                            </button>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
