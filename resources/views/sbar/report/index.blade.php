<div class="w-full my-2 text-[#004D9D] relative font-montserrat" wire:init="loadPatients">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">

            @if($showSectorOnboarding ?? false)
                @include('configuration.system.sector-selector-modal', [
                    'autoOpen'        => true,
                    'mandatory'       => true,
                    'initialSelected' => [],
                    'sectorsData'     => $onboardingSectorsData ?? [],
                ])
                <div
                    x-data
                    @sectors-configured.window="$wire.onSectorOnboardingSaved()"
                ></div>
            @elseif(isset($errorMessage) && $errorMessage)
                <div class="flex items-center justify-center min-h-[60vh]">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 max-w-md text-center">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <x-heroicon-o-exclamation-triangle class="w-8 h-8 text-amber-600" />
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Erro</h2>
                        <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                    </div>
                </div>
            @else
                <div class="relative" x-data="sbarFilters()" x-init="init()">
                    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                        {{-- Header with controls and filters --}}
                        <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 top-0 z-50 shadow-lg font-montserrat">
                            <div class="flex flex-col space-y-3 sm:space-y-2 font-montserrat">

                                {{-- Title row --}}
                                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-2 font-montserrat">
                                    <div class="flex items-center justify-center lg:justify-start gap-2 lg:flex-1 lg:min-w-0">
                                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white font-montserrat text-center lg:text-left">SBAR - Passagem de Plantão</h1>
                                        <button
                                            onclick="window.dispatchEvent(new CustomEvent('open-sbar-legend'));"
                                            class="px-2 sm:px-3 py-1.5 sm:py-2 text-white text-lg sm:text-sm font-bold rounded hover:bg-white/20 transition-colors flex-shrink-0"
                                            title="Legenda e orientações"
                                        >
                                            <span class="hidden sm:inline">Legenda e orientações</span>
                                            <span class="sm:hidden">?</span>
                                        </button>
                                    </div>

                                    <div class="flex items-center justify-center lg:justify-end gap-2 flex-shrink-0 font-montserrat">
                                        @if($lastRefresh)
                                            <span class="hidden xl:block text-white/80 text-xs font-montserrat mr-1">
                                                Última atualização: {{ $lastRefresh }}
                                            </span>
                                        @endif

                                        <div class="hidden lg:flex items-center gap-2 flex-shrink-0">
                                            <button wire:click="refreshData"
                                                    wire:loading.attr="disabled"
                                                    wire:target="refreshData"
                                                    :disabled="isInitialLoading"
                                                    class="inline-flex items-center px-2.5 h-8 xl:px-3 xl:h-9 min-w-[110px] xl:min-w-[126px] rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs xl:text-sm font-medium leading-none">
                                                <span class="inline-flex items-center gap-1.5" :class="isInitialLoading ? 'inline-flex' : 'hidden'">
                                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 xl:h-4 xl:w-4 animate-spin" />
                                                    <span>Carregando...</span>
                                                </span>
                                                <span x-show="!isInitialLoading" x-cloak wire:loading.remove wire:target="refreshData" class="inline-flex items-center gap-1.5">
                                                    <x-iconoir-reload-window class="text-white h-3.5 w-3.5 xl:h-4 xl:w-4" />
                                                    <span>Atualizar</span>
                                                </span>
                                                <span x-show="!isInitialLoading" x-cloak wire:loading.inline-flex wire:target="refreshData" class="items-center gap-1.5">
                                                    <x-heroicon-o-arrow-path class="h-3.5 w-3.5 xl:h-4 xl:w-4 animate-spin" />
                                                    <span>Atualizando...</span>
                                                </span>
                                            </button>

                                            <button @click="$dispatch('openSbarExpiredScalesModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                    :disabled="isInitialLoading"
                                                    wire:loading.attr="disabled"
                                                    wire:target="changeHospital,changeSector,refreshData"
                                                    class="inline-flex items-center px-2.5 py-1.5 xl:px-3 xl:py-2 rounded-lg text-white bg-orange-500 hover:bg-orange-600 shadow-md text-xs xl:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-orange-500">
                                                <i class="fas fa-exclamation-triangle xl:mr-1.5"></i>
                                                <span class="hidden xl:inline">Escalas</span>
                                            </button>

                                            <button
                                                @click="$dispatch('openSbarEvaluationsModal', { sectorId: {{ $selectedSector ?? 0 }} })"
                                                :disabled="isInitialLoading"
                                                wire:loading.attr="disabled"
                                                wire:target="changeHospital,changeSector,refreshData"
                                                class="inline-flex items-center px-2.5 py-1.5 xl:px-3 xl:py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-xs xl:text-sm font-medium disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-[#0071B9]">
                                                <x-iconoir-chat-lines class="text-white h-3.5 w-3.5 xl:h-4 xl:w-4 xl:mr-1.5" />
                                                <span class="hidden xl:inline">Avaliações</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>

                                {{-- Filters --}}
                                <div class="w-full">
                                    <fieldset
                                        class="w-full border-0 p-0 m-0 min-w-0"
                                        disabled
                                        :disabled="isInitialLoading"
                                        wire:loading.attr="disabled"
                                        wire:target="changeHospital,changeSector,refreshData"
                                    >
                                        <div class="flex flex-col gap-3 sm:gap-4">
                                            <div class="w-full min-w-0">
                                                @include('sbar.report.partials.filters.mobile')
                                                @include('sbar.report.partials.filters.desktop')
                                            </div>
                                        </div>
                                    </fieldset>
                                </div>
                            </div>
                        </div>

                        {{-- Handover recovery banner --}}
                        @if($hasActiveHandoverSession)
                        <div class="flex items-center justify-between gap-3 px-4 py-2.5 bg-amber-50 border-b border-amber-200 text-amber-900 text-sm font-medium">
                            <div class="flex items-center gap-2">
                                <x-heroicon-o-arrow-path class="w-4 h-4 text-amber-600 flex-shrink-0" />
                                <span>Você tem uma passagem de plantão em andamento neste turno.</span>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0">
                                <button wire:click="resumeHandover"
                                        class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold rounded-md transition-colors">
                                    <x-heroicon-o-play class="w-3 h-3" />
                                    Retomar
                                </button>
                                <button wire:click="dismissHandoverRecovery"
                                        class="inline-flex items-center px-2.5 py-1 bg-amber-100 hover:bg-amber-200 text-amber-800 text-xs font-semibold rounded-md transition-colors">
                                    Ignorar
                                </button>
                            </div>
                        </div>
                        @endif

                        {{-- Patients container --}}
                        <div id="patientsContainer" class="relative p-2 sm:p-3 lg:p-4 bg-white min-h-[60vh]">

                            {{-- Overlay durante filtragem client-side (só existe após carregamento inicial) --}}
                            @if(!$isLoading)
                            <div x-show="isInitialLoading" x-cloak
                                 class="absolute inset-0 z-30 flex flex-col items-center justify-center rounded-b-xl bg-white/80 backdrop-blur-[2px] gap-4">
                                <div class="w-14 h-14 rounded-full border-4 border-[#004D9D]/20 border-t-[#004D9D] animate-spin"></div>
                                <p class="text-[#004D9D] font-semibold text-sm tracking-wide">Carregando pacientes...</p>
                            </div>
                            @endif

                            {{-- Loading inicial (wire:init ainda não disparou) --}}
                            @if($isLoading)
                                <div class="flex flex-col items-center justify-center py-24 gap-4">
                                    <div class="w-10 h-10 rounded-full border-4 border-[#004D9D]/20 border-t-[#004D9D] animate-spin"></div>
                                    <p class="text-[#004D9D] font-semibold text-sm tracking-wide">Carregando Leitos</p>
                                </div>
                            @elseif(isset($errorMessage) && $errorMessage)
                                <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg mb-6">
                                    <div class="flex items-center">
                                        <x-heroicon-o-exclamation-triangle class="w-6 h-6 mr-2 text-red-500" />
                                        <strong>Erro:</strong>
                                        <span class="ml-2">{{ $errorMessage }}</span>
                                    </div>
                                </div>
                            @elseif(empty($patients))
                                <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg">
                                    <div class="flex items-center">
                                        <x-heroicon-o-information-circle class="w-6 h-6 mr-2 text-yellow-600" />
                                        Nenhum paciente encontrado para o filtro aplicado.
                                    </div>
                                </div>
                            @else
                                @php
                                    $modalPatients = collect($patients)
                                        ->filter(fn ($item) => ($item['has_patient'] ?? false) && !empty($item['nr_atendimento']))
                                        ->map(fn ($item) => [
                                            'nr_atendimento'      => (int) $item['nr_atendimento'],
                                            'cd_pessoa_fisica'    => isset($item['cd_pessoa_fisica']) ? (int) $item['cd_pessoa_fisica'] : null,
                                            'nm_pessoa_fisica'    => $item['nm_pessoa_fisica'] ?? null,
                                            'nm_social'           => $item['nm_social'] ?? null,
                                            'cd_unidade_basica'   => $item['cd_unidade_basica'] ?? null,
                                            'ds_setor_atendimento'=> $item['ds_setor_atendimento'] ?? null,
                                            'ds_prescricao'       => $item['ds_prescricao'] ?? null,
                                        ])
                                        ->values()
                                        ->all();
                                @endphp
                                {{-- Emite a lista de navegação UMA vez como variável global.
                                     Cada card lê window.__sbarModalPatients em vez de receber
                                     @json($modalPatients) repetido N vezes (N×M objetos inline). --}}
                                <script>window.__sbarModalPatients = @json($modalPatients);</script>
                                <div id="patientCardsContainer" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                    @foreach($patients as $index => $patient)
                                        <div wire:key="patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}"
                                             class="relative patient-card"
                                             data-pid="{{ $index }}"
                                             data-has-patient="{{ ($patient['has_patient'] ?? false) ? '1' : '0' }}"
                                             data-mews="{{ $patient['mews_score'] ?? ($patient['pews_score'] ?? '') }}"
                                             data-has-surgery="{{ ($patient['has_surgery'] ?? false) ? '1' : '0' }}"
                                             data-has-isolation="{{ ($patient['has_isolation'] ?? false) ? '1' : '0' }}"
                                             data-pending-types="{{ $patient['pending_type_filter'] ?? '' }}"
                                             data-multi="{{ $patient['multi_team_filter'] ?? '' }}"
                                             data-bed="{{ $patient['cd_unidade_basica'] ?? '' }}"
                                             data-bed-seq="{{ $patient['bed_sequence'] ?? 0 }}"
                                             data-bed-order="{{ $patient['bed_display_order'] ?? $patient['bed_sequence'] ?? 0 }}"
                                             data-internment="{{ $patient['internment_days'] ?? -1 }}"
                                             data-age="{{ $patient['age'] ?? 0 }}"
                                             data-name="{{ strtolower($patient['nm_pessoa_fisica'] ?? 'zzz') }}"
                                             data-handover="{{ ($patient['handover_done'] ?? false) ? '1' : '0' }}"
                                             data-discharge="{{ $patient['discharge_info']['tipo'] ?? '' }}">
                                            <x-patient-card
                                                :patient="$patient"
                                                :current-hospital-name="$currentHospitalName"
                                                :current-shift-name="$currentShiftName"
                                                :modal-patients="$modalPatients"
                                                :show-handover="true"
                                                :show-alerts="true"
                                                :show-mews="true"
                                                :show-admin-data="true"
                                                :show-scales="true"
                                                :show-multidisciplinary="true"
                                                :show-pending-events="true"
                                            />
                                        </div>
                                    @endforeach
                                </div>

                                <div x-show="visibleCount === 0 && totalCount > 0"
                                     x-cloak
                                     class="mt-6 bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg flex items-center gap-2">
                                    <x-heroicon-o-information-circle class="w-5 h-5 text-yellow-600 flex-shrink-0" />
                                    Nenhum paciente encontrado com os filtros aplicados.
                                    <button @click="resetFilters()" class="ml-auto text-sm font-medium underline">Limpar filtros</button>
                                </div>

                                @if(!empty(collect($patients)->pluck('nr_atendimento')->filter()->values()->all()))
                                <script>
                                (function() {
                                    const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

                                    fetch('/patient-care/prescriptions/warm', {
                                        method:  'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ attendance_numbers: @json(collect($patients)->pluck('nr_atendimento')->filter()->values()->all()) }),
                                    }).catch(() => {});

                                    @if(!empty(collect($sectors)->pluck('cd_setor_atendimento')->filter(fn($id) => $id != $selectedSector)->values()->all()))
                                    fetch('/sectors/warm', {
                                        method:  'POST',
                                        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' },
                                        credentials: 'same-origin',
                                        body: JSON.stringify({ sector_ids: @json(collect($sectors)->pluck('cd_setor_atendimento')->filter(fn($id) => $id != $selectedSector)->values()->all()) }),
                                    }).catch(() => {});
                                    @endif
                                })();
                                </script>
                                @endif
                            @endif
                        </div>
                    </div>

                    @include('sbar.report.partials.legend')

                    @livewire('sbar-patient-modal', [], key('sbar-patient-modal'))
                    @livewire('nurse-handover-session', [], key('nurse-handover-session'))
                    @include('sbar.patient.expired-scales-modal', [
                        'expiredScalesPatients' => $expiredScalesPatients,
                        'sectorKey' => $selectedSector ?? 0,
                    ])
                    @livewire('sbar-shift-evaluations-modal', [], key('sbar-shift-evaluations-modal'))

                </div>
            @endif
        </div>
    </div>
</div>

@script
<script>
window.sbarFilters = function () {
    return {
        mewsFilter:        'all',
        surgicalFilter:    'all',
        isolationFilter:   'all',
        pendingTypeFilter: 'all',
        multiFilter:       'all',
        bedsFilter:        'all',
        handoverFilter:    'all',
        dischargeFilter:   'all',
        antibioticFilter:  'all',
        internmentFilter:  'all',
        orderBy:           'bed',
        orderDir:          'asc',
        visibleCount:      0,
        totalCount:        0,
        cards:             [],
        isInitialLoading:  true,

        init() {
            this.$nextTick(() => {
                this.buildCards();
                this.applyFilters();
                this.isInitialLoading = false;
            });

            ['mewsFilter','surgicalFilter','isolationFilter','pendingTypeFilter','multiFilter',
             'bedsFilter','orderBy','orderDir','handoverFilter','dischargeFilter','antibioticFilter','internmentFilter']
                .forEach(prop => this.$watch(prop, () => this.applyFilters()));

            Livewire.hook('commit', ({ component, succeed, fail }) => {
                if (component.name !== 'sbar-report') return;
                this.isInitialLoading = true;
                succeed(() => {
                    this.$nextTick(() => {
                        try { this.buildCards(); this.applyFilters(); } catch(e) { console.error('[SBAR]', e); }
                        this.isInitialLoading = false;
                    });
                });
                fail(() => { this.isInitialLoading = false; });
            });
        },

        buildCards() {
            this.cards = Array.from(
                document.querySelectorAll('#patientCardsContainer [data-pid]')
            ).map(el => ({
                el,
                index:        parseInt(el.dataset.pid),
                hasPatient:   el.dataset.hasPatient === '1',
                mews:         el.dataset.mews === '' ? null : parseFloat(el.dataset.mews),
                hasSurgery:   el.dataset.hasSurgery === '1',
                hasIsolation: el.dataset.hasIsolation === '1',
                pendingTypes: el.dataset.pendingTypes ? el.dataset.pendingTypes.split(',').filter(Boolean) : [],
                multi:        el.dataset.multi ? el.dataset.multi.split(',').filter(Boolean) : [],
                bed:          el.dataset.bed || '',
                bedSeq:       parseInt(el.dataset.bedSeq) || 0,
                bedOrder:     parseInt(el.dataset.bedOrder) || parseInt(el.dataset.bedSeq) || 0,
                internment:   parseFloat(el.dataset.internment) || -1,
                age:          parseInt(el.dataset.age) || 0,
                name:         el.dataset.name || 'zzz',
                handover:     el.dataset.handover === '1',
                discharge:    el.dataset.discharge || '',
                hasAntibiotic: (el.dataset.pendingTypes || '').split(',').filter(Boolean).includes('antibiotico'),
            }));
            this.totalCount = this.cards.length;
        },

        applyFilters() {
            const visible = this.cards.filter(c => {
                if (!c.hasPatient) return this.bedsFilter !== 'only_occupied';
                if (this.bedsFilter === 'only_empty') return false;

                if (this.mewsFilter !== 'all') {
                    const s = c.mews;
                    if (this.mewsFilter === 'critical' && (s === null || s < 5))          return false;
                    if (this.mewsFilter === 'warning'  && (s === null || s < 3 || s > 4)) return false;
                    if (this.mewsFilter === 'normal'   && s !== null && s > 2)             return false;
                }
                if (this.surgicalFilter === 'with_surgery'    && !c.hasSurgery)   return false;
                if (this.surgicalFilter === 'without_surgery' && c.hasSurgery)    return false;
                if (this.isolationFilter === 'with_isolation' && !c.hasIsolation) return false;
                if (this.pendingTypeFilter !== 'all') {
                    const normalizedPending = c.pendingTypes.map((t) => t === 'proc_exame' ? 'exame' : t);
                    if (!normalizedPending.includes(this.pendingTypeFilter)) return false;
                }
                if (this.multiFilter !== 'all' && !c.multi.includes(this.multiFilter)) return false;
                if (this.handoverFilter === 'done'     && !c.handover) return false;
                if (this.handoverFilter === 'not_done' && c.handover)  return false;
                if (this.dischargeFilter === 'today'   && !['alta','alta_medica','previsao_alta'].includes(c.discharge)) return false;
                if (this.antibioticFilter === 'active' && !c.hasAntibiotic) return false;
                if (this.internmentFilter === 'gt3'  && (c.internment < 0 || c.internment <= 3))  return false;
                if (this.internmentFilter === 'gt7'  && (c.internment < 0 || c.internment <= 7))  return false;
                if (this.internmentFilter === 'gt14' && (c.internment < 0 || c.internment <= 14)) return false;

                return true;
            });

            visible.sort((a, b) => {
                if (!a.hasPatient && !b.hasPatient) return a.bedOrder - b.bedOrder;

                let ka, kb;
                switch (this.orderBy) {
                    case 'mews':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        ka = a.mews ?? -1; kb = b.mews ?? -1;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    case 'name':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        return this.orderDir === 'asc'
                            ? a.name.localeCompare(b.name)
                            : b.name.localeCompare(a.name);
                    case 'internment':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        ka = a.internment; kb = b.internment;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    case 'age':
                        if (!a.hasPatient) return 1;
                        if (!b.hasPatient) return -1;
                        ka = a.age; kb = b.age;
                        return this.orderDir === 'asc' ? ka - kb : kb - ka;
                    default:
                        const diff = a.bedOrder - b.bedOrder;
                        return this.orderDir === 'asc' ? diff : -diff;
                }
            });

            const visibleSet = new Set(visible.map(c => c.index));
            this.cards.forEach(c => {
                c.el.style.display = visibleSet.has(c.index) ? '' : 'none';
                c.el.style.order   = '';
            });
            visible.forEach((c, i) => { c.el.style.order = i + 1; });

            this.visibleCount = visible.length;
        },

        resetFilters() {
            this.mewsFilter        = 'all';
            this.surgicalFilter    = 'all';
            this.isolationFilter   = 'all';
            this.pendingTypeFilter = 'all';
            this.multiFilter       = 'all';
            this.bedsFilter        = 'all';
            this.handoverFilter    = 'all';
            this.dischargeFilter   = 'all';
            this.antibioticFilter  = 'all';
            this.internmentFilter  = 'all';
            this.orderBy           = 'bed';
            this.orderDir          = 'asc';
            this.applyFilters();
        },

        hasActiveFilters() {
            return this.mewsFilter !== 'all'
                || this.surgicalFilter !== 'all'
                || this.isolationFilter !== 'all'
                || this.pendingTypeFilter !== 'all'
                || this.multiFilter !== 'all'
                || this.bedsFilter !== 'all'
                || this.handoverFilter !== 'all'
                || this.dischargeFilter !== 'all'
                || this.antibioticFilter !== 'all'
                || this.internmentFilter !== 'all'
                || this.orderBy !== 'bed'
                || this.orderDir !== 'asc';
        },
    };
};
</script>
@endscript
