<div x-data="{
    showModal: @entangle('showModal'),
    scrollY: 0,
    init() {
        this.$watch('showModal', (value) => {
            if (value) {
                this.lockScroll();
            } else {
                this.unlockScroll();
            }
        });
    },
    lockScroll() {
        this.scrollY = window.pageYOffset || window.scrollY || 0;
        document.body.style.overflow = 'hidden';
        document.body.style.position = 'fixed';
        document.body.style.top = `-${this.scrollY}px`;
        document.body.style.left = '0';
        document.body.style.right = '0';
        document.body.style.width = '100%';
    },
    unlockScroll() {
        document.body.style.overflow = '';
        document.body.style.position = '';
        document.body.style.top = '';
        document.body.style.left = '';
        document.body.style.right = '';
        document.body.style.width = '';

        requestAnimationFrame(() => {
            window.scrollTo(0, this.scrollY);
            document.documentElement.scrollTop = this.scrollY;
            document.body.scrollTop = this.scrollY;
        });
    }
}">
    <div
        x-show="showModal"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0"
        x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        class="fixed inset-0 z-[9998]"
        style="display: none;"
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
               @click="showModal = false; $wire.closeModal();"></div>

        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-full
                       sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                       lg:w-[85vw] lg:h-[90vh] lg:max-w-[1600px]
                       shadow-2xl huddle-modal-container"
                wire:key="huddle-modal-{{ $currentPatient['nr_atendimento'] ?? 'empty' }}"
                @click.stop
            >
                {{-- Header --}}
                @php
                    $patientName = $currentPatient['nm_social'] ?? $currentPatient['nm_pessoa_fisica'] ?? null;
                    $bed = $currentPatient['cd_unidade_basica'] ?? null;
                    $sector = $currentPatient['ds_setor_atendimento'] ?? null;
                    $prontuario = $currentPatient['nr_prontuario'] ?? null;
                    $hasPatient = ! empty($currentPatient['nr_atendimento']);
                    $internmentLabel = ($currentPatient['is_new_patient'] ?? false)
                        ? 'Hoje'
                        : (isset($currentPatient['internment_days']) ? $currentPatient['internment_days'].'d' : null);
                @endphp
                <div class="flex-shrink-0 bg-[#004D9D] px-3 sm:px-6 py-2.5 sm:py-3 relative"
                     x-data="{
                        expanded: false,
                        tipDismissed: true,
                        init() {
                            const saved = localStorage.getItem('huddle_modal_header_expanded');
                            if (saved === 'true') { this.expanded = true; }
                            else if (saved === 'false') { this.expanded = false; }
                            else { this.expanded = window.innerHeight >= 720; }
                            this.tipDismissed = localStorage.getItem('huddle_modal_header_tip_seen') === '1';
                        },
                        toggle() {
                            this.expanded = !this.expanded;
                            localStorage.setItem('huddle_modal_header_expanded', this.expanded ? 'true' : 'false');
                            if (!this.tipDismissed) { this.dismissTip(); }
                        },
                        dismissTip() {
                            this.tipDismissed = true;
                            localStorage.setItem('huddle_modal_header_tip_seen', '1');
                        }
                     }">
                    {{-- Close / Cancel Button --}}
                    @if($handoverMode)
                        <button
                            wire:click="cancelHandover"
                            class="absolute top-2.5 right-3 sm:top-1/2 sm:-translate-y-1/2 sm:right-4 z-10 inline-flex items-center justify-center gap-1.5 h-9 px-3 text-white bg-rose-600/90 hover:bg-rose-600 transition-colors rounded-full focus:outline-none focus:ring-2 focus:ring-white/50 shadow-lg"
                            aria-label="Cancelar passagem"
                            title="Cancelar passagem"
                        >
                            <x-heroicon-o-x-mark class="w-4 h-4" />
                            <span class="text-xs font-semibold">Cancelar</span>
                        </button>
                    @else
                        <button
                            @click="showModal = false; $wire.closeModal();"
                            class="absolute top-2.5 right-3 sm:top-1/2 sm:-translate-y-1/2 sm:right-4 z-10 flex items-center justify-center w-8 h-8 text-white/70 hover:text-white transition-colors bg-white/10 hover:bg-white/20 rounded-full focus:outline-none focus:ring-2 focus:ring-white/50"
                            aria-label="Fechar modal"
                        >
                            <x-heroicon-o-x-mark class="w-5 h-5" />
                        </button>
                    @endif

                    <div class="pr-12 sm:pr-12">

                        {{-- ─── PEEK LINE (sempre visível) ─── --}}
                        <div class="flex items-center gap-2 sm:gap-3 min-w-0">
                            <img src="{{ asset('images/santacasa-horizontal-branco.svg') }}"
                                 alt="Santa Casa"
                                 class="h-5 sm:h-6 w-auto opacity-90 flex-shrink-0">

                            <div class="flex items-center flex-wrap gap-x-2 gap-y-1 text-white min-w-0 flex-1">
                                @if($bed)
                                    <span class="inline-flex items-center gap-1 text-xs sm:text-sm font-mono font-semibold whitespace-nowrap">
                                        <i class="fas fa-bed text-white/60 text-[10px]"></i>{{ $bed }}
                                    </span>
                                @endif

                                @if($patientName)
                                    @if($bed)<span class="text-white/30 hidden sm:inline">·</span>@endif
                                    <span class="text-xs sm:text-sm font-medium truncate max-w-[10rem] sm:max-w-[18rem] lg:max-w-[24rem]">{{ $patientName }}</span>
                                @endif

                                @if($hasPatient)
                                    <span class="text-white/30 hidden sm:inline">·</span>
                                    <span class="text-[11px] sm:text-xs font-mono text-white/80 whitespace-nowrap" title="Atendimento">
                                        #{{ $currentPatient['nr_atendimento'] }}
                                    </span>
                                @endif

                                @if($internmentLabel)
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] sm:text-[11px] font-semibold bg-white/15 text-white whitespace-nowrap">
                                        <i class="fas fa-hospital-user text-[9px]"></i>{{ $internmentLabel }}
                                    </span>
                                @endif
                            </div>

                            {{-- Toggle button --}}
                            <div class="relative flex-shrink-0">
                                <button
                                    type="button"
                                    @click="toggle()"
                                    :aria-expanded="expanded"
                                    aria-controls="huddle-modal-header-details"
                                    x-bind:class="!tipDismissed && !expanded ? 'ring-2 ring-amber-300 animate-pulse' : ''"
                                    class="inline-flex items-center gap-1 px-2.5 sm:px-3 py-1 sm:py-1.5 rounded-full bg-white/15 hover:bg-white/25 text-white text-[11px] sm:text-xs font-medium transition-all focus:outline-none focus:ring-2 focus:ring-white/50"
                                    title="Mostrar ou ocultar dados completos do paciente"
                                >
                                    <span x-show="!expanded" class="hidden sm:inline">Mais detalhes</span>
                                    <span x-show="expanded" class="hidden sm:inline">Menos detalhes</span>
                                    <span x-show="!expanded" class="sm:hidden">Detalhes</span>
                                    <span x-show="expanded" class="sm:hidden">Recolher</span>
                                    <x-heroicon-o-chevron-down class="w-3.5 h-3.5 transition-transform" x-bind:class="expanded ? 'rotate-180' : ''" />
                                </button>

                                {{-- Onboarding tooltip --}}
                                <div
                                    x-show="!tipDismissed && !expanded"
                                    x-transition.opacity.duration.300ms
                                    x-cloak
                                    class="absolute right-0 mt-2 w-64 sm:w-72 bg-amber-50 text-amber-900 text-xs rounded-lg shadow-xl ring-1 ring-amber-300 px-3 py-2.5 z-50"
                                    style="top: 100%;"
                                    role="tooltip"
                                >
                                    <div class="absolute -top-1.5 right-6 w-3 h-3 bg-amber-50 ring-1 ring-amber-300 rotate-45"></div>
                                    <div class="flex items-start gap-2">
                                        <i class="fas fa-circle-info text-amber-600 mt-0.5"></i>
                                        <div class="flex-1">
                                            <p class="font-semibold leading-snug">Toque aqui para ver mais</p>
                                            <p class="text-[11px] mt-1 leading-snug text-amber-800">
                                                Hospital, setor, prontuário e outras informações estão ocultos para liberar espaço da tela.
                                            </p>
                                            <button
                                                type="button"
                                                @click.stop="dismissTip()"
                                                class="mt-2 text-[11px] font-semibold text-amber-700 hover:text-amber-900 underline"
                                            >
                                                Entendi, não mostrar de novo
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- ─── Trocar atendimento (sempre visível, no topo) ─── --}}
                        @if(!empty($modalPatients))
                        <div class="mt-2 pt-2 border-t border-white/15"
                             x-data="{
                                open: false,
                                search: '',
                                patients: @js(collect($modalPatients)->map(fn ($p) => [
                                    'nr_atendimento' => (int) ($p['nr_atendimento'] ?? 0),
                                    'label' => (string) ($p['label'] ?? ''),
                                ])->values()->all()),
                                currentNr: {{ (int) ($currentPatient['nr_atendimento'] ?? 0) }},
                                get filtered() {
                                    const q = String(this.search || '').trim().toLowerCase();
                                    if (!q) return this.patients;
                                    return this.patients.filter(p =>
                                        String(p.label || '').toLowerCase().includes(q) ||
                                        String(p.nr_atendimento).includes(q)
                                    );
                                },
                                get currentLabel() {
                                    const c = this.patients.find(p => p.nr_atendimento === this.currentNr);
                                    return c ? c.label : 'Selecionar atendimento';
                                },
                                toggle() {
                                    this.open = !this.open;
                                    if (this.open) this.$nextTick(() => this.$refs.searchInput && this.$refs.searchInput.focus());
                                    else this.search = '';
                                },
                                select(nr) {
                                    this.open = false;
                                    this.search = '';
                                    if (nr === this.currentNr) return;
                                    this.$wire.goToPatientByAttendance(nr);
                                },
                                selectFirst() {
                                    if (this.filtered.length > 0) this.select(this.filtered[0].nr_atendimento);
                                }
                             }"
                             @keydown.escape.window="open = false"
                             @click.outside="open = false">
                            <div class="flex items-center gap-2 min-w-0">
                                <span class="hidden sm:inline-flex items-center gap-1 text-[10px] text-blue-100/80 font-medium whitespace-nowrap flex-shrink-0">
                                    <x-heroicon-o-arrows-up-down class="w-3 h-3" />
                                    Trocar
                                </span>

                                <button
                                    type="button"
                                    wire:click="goToPreviousPatient"
                                    @disabled(! $canGoPrevious)
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-white/15 text-white hover:bg-white/25 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50 disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0"
                                    aria-label="Atendimento anterior"
                                    title="Atendimento anterior"
                                >
                                    <x-heroicon-o-chevron-up class="w-4 h-4" />
                                </button>

                                <div class="relative min-w-0 flex-1">
                                    <button
                                        type="button"
                                        @click="toggle()"
                                        :aria-expanded="open"
                                        aria-haspopup="listbox"
                                        class="w-full flex items-center justify-between gap-2 bg-white/10 border border-white/25 text-white text-xs sm:text-sm rounded-md px-3 py-1.5 hover:bg-white/15 focus:outline-none focus:ring-2 focus:ring-white/40"
                                    >
                                        <span class="truncate text-left flex-1" x-text="currentLabel"></span>
                                        <x-heroicon-o-chevron-down class="w-4 h-4 flex-shrink-0 transition-transform" x-bind:class="open ? 'rotate-180' : ''" />
                                    </button>

                                    <div
                                        x-show="open"
                                        x-transition.opacity.duration.150ms
                                        x-cloak
                                        class="absolute z-50 mt-1 left-0 right-0 bg-white rounded-md shadow-xl ring-1 ring-black/10 overflow-hidden flex flex-col max-h-80"
                                    >
                                        <div class="p-2 border-b border-gray-100">
                                            <div class="relative">
                                                <x-heroicon-o-magnifying-glass class="absolute left-2.5 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400 pointer-events-none" />
                                                <input
                                                    x-ref="searchInput"
                                                    type="search"
                                                    x-model="search"
                                                    @keydown.enter.prevent="selectFirst()"
                                                    placeholder="Buscar por nome ou nº atendimento..."
                                                    class="w-full pl-8 pr-8 py-1.5 text-sm text-gray-900 placeholder-gray-400 bg-gray-50 border border-gray-200 rounded focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]/50"
                                                />
                                                <button
                                                    type="button"
                                                    x-show="search"
                                                    @click="search = ''; $refs.searchInput.focus()"
                                                    class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600"
                                                    aria-label="Limpar busca"
                                                >
                                                    <x-heroicon-o-x-mark class="w-4 h-4" />
                                                </button>
                                            </div>
                                        </div>
                                        <ul class="overflow-y-auto flex-1 py-1" role="listbox">
                                            <template x-for="p in filtered" :key="p.nr_atendimento">
                                                <li>
                                                    <button
                                                        type="button"
                                                        @click="select(p.nr_atendimento)"
                                                        x-bind:class="p.nr_atendimento === currentNr ? 'bg-blue-50 text-[#004D9D] font-medium' : 'text-gray-800 hover:bg-gray-50'"
                                                        class="w-full text-left text-sm px-3 py-2 truncate"
                                                        role="option"
                                                        x-bind:aria-selected="p.nr_atendimento === currentNr"
                                                        x-text="p.label"
                                                    ></button>
                                                </li>
                                            </template>
                                            <li x-show="filtered.length === 0" class="px-3 py-4 text-center text-xs text-gray-500">
                                                Nenhum paciente encontrado.
                                            </li>
                                        </ul>
                                    </div>
                                </div>

                                <button
                                    type="button"
                                    wire:click="goToNextPatient"
                                    @disabled(! $canGoNext)
                                    class="inline-flex items-center justify-center w-8 h-8 rounded-md bg-white/15 text-white hover:bg-white/25 transition-colors focus:outline-none focus:ring-2 focus:ring-white/50 disabled:opacity-40 disabled:cursor-not-allowed flex-shrink-0"
                                    aria-label="Próximo atendimento"
                                    title="Próximo atendimento"
                                >
                                    <x-heroicon-o-chevron-down class="w-4 h-4" />
                                </button>

                                @if($currentPatientIndex !== null)
                                    <span class="text-[10px] sm:text-[11px] text-blue-100/80 whitespace-nowrap font-mono flex-shrink-0">
                                        {{ $currentPatientIndex + 1 }}/{{ count($modalPatients) }}
                                    </span>
                                @endif
                            </div>
                        </div>
                        @endif

                        {{-- ─── EXPANDED DETAILS (toggle) ─── --}}
                        <div
                            id="huddle-modal-header-details"
                            x-show="expanded"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 -translate-y-1"
                            x-transition:enter-end="opacity-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            x-cloak
                            class="mt-2 pt-2 border-t border-white/15"
                        >
                            <div class="mb-2.5">
                                <h3 class="text-xs sm:text-sm text-blue-100 font-medium leading-tight">
                                    Huddle de Alta
                                </h3>
                            </div>

                            <div class="sm:hidden">
                                <div class="grid grid-cols-2 gap-2 text-[11px] text-blue-100">
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5">
                                        <span class="opacity-80 leading-tight block">Hospital</span>
                                        <span class="text-white font-semibold leading-tight truncate block">{{ $hospitalName ?: '—' }}</span>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5">
                                        <span class="opacity-80 leading-tight block">Setor</span>
                                        <span class="text-white font-semibold leading-tight truncate block">{{ $sector ?? '—' }}</span>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5">
                                        <span class="opacity-80 leading-tight block">Prontuário</span>
                                        <span class="text-white font-semibold font-mono leading-tight truncate block">{{ $prontuario ?? '—' }}</span>
                                    </div>
                                    <div class="rounded-lg border border-white/20 bg-white/10 px-2 py-1.5">
                                        <span class="opacity-80 leading-tight block">Internação</span>
                                        <span class="text-white font-semibold leading-tight block">{{ $internmentLabel ?? '—' }}</span>
                                    </div>
                                </div>
                            </div>

                            <div class="hidden sm:flex flex-wrap gap-x-6 gap-y-1.5 text-xs sm:text-sm text-blue-100">
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="opacity-80 whitespace-nowrap">Hospital:</span>
                                    <span class="text-white font-medium truncate max-w-[16rem] lg:max-w-xs">{{ $hospitalName ?: '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="opacity-80 whitespace-nowrap">Setor:</span>
                                    <span class="text-white font-medium truncate max-w-[16rem] lg:max-w-xs">{{ $sector ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="opacity-80 whitespace-nowrap">Prontuário:</span>
                                    <span class="text-white font-medium font-mono">{{ $prontuario ?? '—' }}</span>
                                </div>
                                <div class="flex items-center gap-1.5 min-w-0">
                                    <span class="opacity-80 whitespace-nowrap">Internação:</span>
                                    <span class="text-white font-medium">{{ $internmentLabel ?? '—' }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Content Area (em branco por enquanto) --}}
                <div class="flex-1 min-h-0 overflow-y-auto bg-slate-50">
                </div>
            </div>
        </div>
    </div>

    <style>
        body.modal-active {
            overflow: hidden !important;
            position: static;
        }

        @media screen and (orientation: landscape) and (max-height: 550px) {
            .huddle-modal-container {
                width: 100% !important;
                height: 100% !important;
                max-height: 100% !important;
                border-radius: 0 !important;
            }
            body.modal-active { position: static; }
        }
    </style>
</div>
