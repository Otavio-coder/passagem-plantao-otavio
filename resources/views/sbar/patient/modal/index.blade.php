<div x-data="{
    showAlertsModal: @entangle('showAlertsModal'),
    showModal: @entangle('showModal'),
    init() {
        this.$watch('showAlertsModal', (value) => {
            if (value) {
                document.body.style.overflow = 'hidden';
            } else if (!this.showModal) {
                document.body.style.overflow = '';
            }
        });

        this.$watch('showModal', (value) => {
            if (value) {
                document.body.style.overflow = 'hidden';
                document.body.classList.add('modal-active');

                if (window.innerWidth < 640) {
                    document.documentElement.style.position = 'fixed';
                    document.documentElement.style.width = '100%';
                    document.documentElement.style.height = '100%';
                    document.documentElement.style.top = '0';
                }
            } else {
                document.body.style.overflow = '';
                document.body.classList.remove('modal-active');
                document.documentElement.style.position = '';
                document.documentElement.style.width = '';
                document.documentElement.style.height = '';
                document.documentElement.style.top = '';
            }
        });
    }
}">

    {{-- Modal de Alertas --}}
    <x-patient-modal::alerts-modal
        :showAlertsModal="$showAlertsModal"
        :patientAlerts="$patientAlerts"
        :currentPatient="$currentPatient"
    />

    {{-- Modal Principal --}}
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
        {{-- Backdrop --}}
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showModal = false; $wire.closeModal();"></div>

        {{-- Container do Modal --}}
        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div
                class="relative bg-white flex flex-col overflow-hidden
                       w-full h-full
                       sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl
                       lg:w-[85vw] lg:h-[90vh] lg:max-w-[1600px]
                       shadow-2xl modal-patient-container"
                wire:key="modal-{{ $currentPatient['nr_atendimento'] ?? 'empty' }}"
                x-data="{
                    activeTab: 'tab-s',
                    activeRecomendacaoTab: 'tab-med',
                    currentTabIndex: 0,
                    tabs: ['tab-s', 'tab-b', 'tab-a', 'tab-r'],
                    isSwipeEnabled: window.innerWidth < 1024,
                    swipeStartX: null,
                    swipeStartY: null,
                    isTransitioning: false,

                    init() {
                        this.updateSwipeEnabled();
                        window.addEventListener('resize', () => this.updateSwipeEnabled());
                    },

                    updateSwipeEnabled() {
                        this.isSwipeEnabled = window.innerWidth < 1024;
                    },

                    switchTab(tabName) {
                        if (this.isTransitioning) return;

                        this.isTransitioning = true;
                        this.activeTab = tabName;
                        this.currentTabIndex = this.tabs.indexOf(tabName);

                        // Haptic feedback
                        if (navigator.vibrate) {
                            navigator.vibrate(10);
                        }

                        // Scroll chat to bottom when switching to the chat tab
                        if (tabName === 'tab-a') {
                            this.$nextTick(() => {
                                window.dispatchEvent(new Event('scroll-to-bottom'));
                            });
                        }

                        setTimeout(() => {
                            this.isTransitioning = false;
                        }, 300);
                    },

                    handleSwipe(direction) {
                        if (!this.isSwipeEnabled || this.isTransitioning) return;

                        const newIndex = direction === 'left'
                            ? Math.min(this.currentTabIndex + 1, this.tabs.length - 1)
                            : Math.max(this.currentTabIndex - 1, 0);

                        if (newIndex !== this.currentTabIndex) {
                            this.switchTab(this.tabs[newIndex]);
                        }
                    }
                }"
                @click.stop
                @touchstart.passive="
                    if (!isSwipeEnabled) return;
                    const touch = $event.touches[0];
                    swipeStartX = touch.clientX;
                    swipeStartY = touch.clientY;
                "
                @touchend.passive="
                    if (!isSwipeEnabled || swipeStartX === null) return;

                    const touch = $event.changedTouches[0];
                    const deltaX = touch.clientX - swipeStartX;
                    const deltaY = touch.clientY - swipeStartY;

                    if (Math.abs(deltaX) > Math.abs(deltaY) + 30 && Math.abs(deltaX) > 80) {
                        handleSwipe(deltaX > 0 ? 'right' : 'left');
                    }

                    swipeStartX = null;
                    swipeStartY = null;
                "
            >
                {{-- Loading Overlay --}}
                @if($loadingPatient)
                    <div class="absolute inset-0 z-50 flex items-center justify-center bg-white/95 backdrop-blur-sm">
                        <div class="flex flex-col items-center gap-4">
                            <div class="relative">
                                <div class="w-12 h-12 border-4 border-[#004D9D] border-t-transparent rounded-full animate-spin"></div>
                                <div class="absolute inset-0 w-12 h-12 border-4 border-[#004D9D]/20 border-t-transparent rounded-full animate-pulse"></div>
                            </div>
                            <span class="text-[#004D9D] font-medium text-sm">Carregando dados do paciente...</span>
                        </div>
                    </div>
                @endif

                {{-- Header - ALTURA FIXA --}}
                <div class="flex-shrink-0">
                    <x-patient-modal::header
                        :currentHospitalName="$currentHospitalName"
                        :currentPatient="$currentPatient"
                        :patientDetails="$patientDetails"
                    />
                </div>

                {{-- Tabs Navigation - ALTURA FIXA --}}
                <div class="flex-shrink-0 border-b border-gray-200">
                    <x-patient-modal::tabs />
                </div>

                {{-- Content Area - FLEX-1 COM OVERFLOW --}}
                <div class="flex-1 bg-gray-50 relative overflow-hidden min-h-0">
                    {{-- Container com position relative para as tabs absolutas --}}
                    <div class="absolute inset-0">
                        {{-- Situação --}}
                        <div x-show="activeTab === 'tab-s'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <x-sbar::situacao
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Background --}}
                        <div x-show="activeTab === 'tab-b'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <x-sbar::background
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Avaliação (Chat) --}}
                        <div x-show="activeTab === 'tab-a'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0"
                             style="display: none;">
                            <x-sbar::avaliacao
                                :loadingPatient="$loadingPatient"
                                :currentPatient="$currentPatient"
                                :patientDetails="$patientDetails"
                            />
                        </div>

                        {{-- Therapeutic Plan (tab-r) --}}
                        <div x-show="activeTab === 'tab-r'"
                             x-transition:enter="transition-opacity ease-out duration-300"
                             x-transition:enter-start="opacity-0"
                             x-transition:enter-end="opacity-100"
                             x-transition:leave="transition-opacity ease-in duration-200"
                             x-transition:leave-start="opacity-100"
                             x-transition:leave-end="opacity-0"
                             class="absolute inset-0 overflow-y-auto overflow-x-hidden"
                             style="display: none;">
                            <x-sbar::recomendacoes
                                :planLoaded="$planLoaded"
                                :planError="$planError"
                                :therapeuticPlan="$therapeuticPlan"
                                :scheduleDate="$scheduleDate"
                                :medicationSchedule="$medicationSchedule"
                            />
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        /* Body modal state */
        body.modal-active {
            overflow: hidden !important;
            position: fixed;
            width: 100%;
            height: 100%;
        }

        /* Landscape mobile/tablet: full-screen modal to avoid cramped content */
        @media screen and (orientation: landscape) and (max-height: 550px) {
            .modal-patient-container {
                width: 100% !important;
                height: 100% !important;
                max-height: 100% !important;
                border-radius: 0 !important;
            }
            body.modal-active {
                position: static;
            }
        }

        /* Scrollbar customizado */
        .overflow-y-auto {
            scrollbar-width: thin;
            scrollbar-color: #cbd5e1 #f1f5f9;
        }

        .overflow-y-auto::-webkit-scrollbar {
            width: 6px;
        }

        .overflow-y-auto::-webkit-scrollbar-track {
            background: #f1f5f9;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 3px;
        }

        .overflow-y-auto::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }

        /* Mobile optimizations */
        @media (max-width: 640px) {
            .overflow-y-auto {
                -webkit-overflow-scrolling: touch;
                overscroll-behavior: contain;
            }

            .overflow-y-auto::-webkit-scrollbar {
                width: 3px;
            }
        }

        /* Prevent zoom on inputs in mobile */
        @media (max-width: 640px) {
            input[type="text"],
            input[type="email"],
            input[type="number"],
            textarea,
            select {
                font-size: 16px !important;
            }
        }

        /* Transitions suaves */
        .transition-opacity {
            transition-property: opacity;
        }

        /* Garantir que tabs ocultas não ocupem espaço e não interfiram */
        [x-show][style*="display: none"] {
            display: none !important;
            pointer-events: none !important;
            visibility: hidden !important;
        }
    </style>
</div>

@script
<script>
window.therapeuticPlan = function(meds, schedule, timeCols, currentHour, procs, ords, ints, hemo, surgs, chemos, gas, dial) {
    // Generic paginated-list helper used by every tab
    function listState(items, defaultExtra = 'all') {
        return {
            items,
            defaultExtra,
            q: '', page: 1, perPage: 10,
            extra: defaultExtra,
            expanded: null,
            get filtered() {
                const q = this.q.trim().toLowerCase();
                return this.items.filter(it =>
                    (!q || (it.name || it.text || '').toLowerCase().includes(q)) &&
                    (this.extra === 'all' || it._extra === this.extra)
                );
            },
            get paged()  { return this.filtered.slice((this.page-1)*this.perPage, this.page*this.perPage); },
            get pages()  { return Math.max(1, Math.ceil(this.filtered.length / this.perPage)); },
            get from()   { return this.filtered.length ? (this.page-1)*this.perPage+1 : 0; },
            get to()     { return Math.min(this.page*this.perPage, this.filtered.length); },
            setQ(v)      { this.q = v; this.page = 1; },
            setExtra(v)  { this.extra = v; this.page = 1; },
            setPer(n)    { this.perPage = n; this.page = 1; },
            clear()      { this.q = ''; this.extra = this.defaultExtra; this.page = 1; },
        };
    }

    procs = procs
        .map(p => ({ ...p, _extra: p.is_today ? 'today' : 'other' }))
        .sort((a, b) => {
            if (a._extra !== b._extra) return a._extra === 'today' ? -1 : 1;
            if (a.scheduled_raw && b.scheduled_raw) return a.scheduled_raw.localeCompare(b.scheduled_raw);
            if (a.scheduled_raw) return -1;
            if (b.scheduled_raw) return 1;
            return 0;
        });
    ords  = ords.map(o => ({ ...o, _extra: o.type || '' }));
    ints  = ints.map(i => ({ ...i, _extra: (i.labels || []).includes('Urgent') ? 'urgent' : 'normal' }));
    hemo  = hemo.map(h => ({ ...h, _extra: h.is_urgent ? 'urgent' : 'normal' }));
    surgs = surgs.map(s => ({ ...s, _extra: s.is_urgent ? 'urgent' : 'normal' }));
    chemos= chemos.map(c => ({ ...c, _extra: 'all' }));
    gas   = gas.map(g => ({ ...g, _extra: 'all' }));
    dial  = dial.map(d => ({ ...d, _extra: 'all' }));

    return {
        meds, schedule, timeCols, currentHour,
        q: '', antibioticoF: false,
        expandedMed: null,
        medPage: 1, medPerPage: 5, medSortDir: 'asc',
        marShadowLeft: false,
        marShadowRight: false,

        proc: listState(procs, 'today'),
        procType: 'all',
        ord:  listState(ords),
        int:  listState(ints),
        hemo: listState(hemo),
        surg: listState(surgs),
        chemo:listState(chemos),
        gas:  listState(gas),
        dial: listState(dial),
        ordTypes: [...new Set(ords.map(o => o._extra).filter(Boolean))].sort(),
        procTypes: [...new Set(procs.map(p => p.type || 'Sem tipo'))].sort(),

        init() {
            this.$nextTick(() => this.initMarScroll());
        },

        setSearch(val) { this.q = val; this.medPage = 1; },
        clearFilters() { this.q = ''; this.antibioticoF = false; this.medPage = 1; },
        clearProcFilters() {
            this.proc.clear();
            this.procType = 'all';
        },

        medById(medId) {
            const id = String(medId);
            return this.meds.find(m => String(m.id) === id) || null;
        },
        scheduleFallbackMap(medId) {
            const med = this.medById(medId);
            if (!med || !med.schedule) return {};

            const scheduleText = String(med.schedule);
            const matches = [...scheduleText.matchAll(/\b([01]\d|2[0-3]):([0-5]\d)\b/g)];
            if (!matches.length) return {};

            const fallback = {};
            for (const match of matches) {
                const hour = `${match[1]}:00`;
                if (this.timeCols.includes(hour)) {
                    fallback[hour] = 'scheduled';
                }
            }
            return fallback;
        },

        medSlots(medId) {
            const s = this.schedule[medId] || this.schedule[String(medId)] || {};
            const hasRealSlots = Object.keys(s).length > 0;
            const fallback = hasRealSlots ? {} : this.scheduleFallbackMap(medId);
            return this.timeCols.map(col => ({ col, time: col.slice(0,5), status: s[col] || fallback[col] || null }));
        },
        toMinutes(hhmm) {
            const [h, m] = String(hhmm || '00:00').split(':').map(v => Number(v));
            if (Number.isNaN(h) || Number.isNaN(m)) return 0;
            return (h * 60) + m;
        },
        medNextDueScore(med) {
            const nowMinutes = this.toMinutes(this.currentHour || '00:00');
            const slots = this.medSlots(med.id);

            const pendingTimes = slots
                .filter(slot => slot.status === 'scheduled' || slot.status === 'rescheduled')
                .map(slot => slot.col);

            const sourceTimes = pendingTimes.length
                ? pendingTimes
                : slots.filter(slot => slot.status !== null).map(slot => slot.col);

            if (!sourceTimes.length) return Number.MAX_SAFE_INTEGER;

            let bestDelta = Number.MAX_SAFE_INTEGER;
            for (const t of sourceTimes) {
                const mins = this.toMinutes(t);
                let delta = mins - nowMinutes;
                if (delta < 0) delta += 24 * 60;
                if (delta < bestDelta) bestDelta = delta;
            }

            return bestDelta;
        },
        hasAnySlot(medId) {
            return this.medSlots(medId).some(slot => slot.status !== null);
        },
        get filteredMeds() {
            const q = this.q.trim().toLowerCase();
            const sorted = this.meds
                .filter(m =>
                    (!q || m.name.toLowerCase().includes(q)) &&
                    (!this.antibioticoF || m.is_antibiotic)
                )
                .slice()
                .sort((a, b) => {
                    const da = this.medNextDueScore(a);
                    const db = this.medNextDueScore(b);
                    if (da !== db) return da - db;

                    const pa = a.nr_prescricao ?? 0;
                    const pb = b.nr_prescricao ?? 0;
                    if (pa === pb) return 0;
                    if (pa === 0) return 1;
                    if (pb === 0) return -1;
                    return this.medSortDir === 'asc' ? pa - pb : pb - pa;
                });
            return sorted;
        },
        get pagedMeds()  { return this.filteredMeds.slice((this.medPage-1)*this.medPerPage, this.medPage*this.medPerPage); },
        get medPages()   { return Math.max(1, Math.ceil(this.filteredMeds.length / this.medPerPage)); },
        get medFrom()    { return this.filteredMeds.length ? (this.medPage-1)*this.medPerPage+1 : 0; },
        get medTo()      { return Math.min(this.medPage*this.medPerPage, this.filteredMeds.length); },
        get procFiltered() {
            if (this.procType === 'all') return this.proc.filtered;
            return this.proc.filtered.filter(p => (p.type || 'Sem tipo') === this.procType);
        },
        get procPaged() {
            return this.procFiltered.slice((this.proc.page-1)*this.proc.perPage, this.proc.page*this.proc.perPage);
        },
        get procPages() {
            return Math.max(1, Math.ceil(this.procFiltered.length / this.proc.perPage));
        },
        get procFrom() {
            return this.procFiltered.length ? (this.proc.page-1)*this.proc.perPage+1 : 0;
        },
        get procTo() {
            return Math.min(this.proc.page*this.proc.perPage, this.procFiltered.length);
        },

        initMarScroll() {
            const el = this.$refs?.marScroll;
            if (!el) return;
            this.updateMarShadows(el);
            this.scrollToRelevantHour(el);
        },
        updateMarShadows(el) {
            this.marShadowLeft  = el.scrollLeft > 8;
            this.marShadowRight = el.scrollLeft + el.clientWidth < el.scrollWidth - 8;
        },
        scrollToRelevantHour(el) {
            if (!this.timeCols.length) return;
            const colWidth = 52;
            const idxCurrent = this.timeCols.indexOf(this.currentHour);
            let targetIdx = -1;

            if (idxCurrent >= 0) {
                const hasCurrent = this.filteredMeds.some(med => Boolean(this.slotStatus(med.id, this.currentHour)));
                if (hasCurrent) targetIdx = idxCurrent;
            }

            if (targetIdx === -1) {
                targetIdx = this.timeCols.findIndex(col => this.filteredMeds.some(med => Boolean(this.slotStatus(med.id, col))));
            }

            if (targetIdx === -1) {
                targetIdx = idxCurrent;
            }

            if (targetIdx >= 0) {
                const offset = Math.max(0, targetIdx * colWidth - 60);
                el.scrollTo({ left: offset, behavior: 'smooth' });
                this.updateMarShadows(el);
            }
        },

        slotStatus(medId, col) {
            const s = this.schedule[medId] || this.schedule[String(medId)] || {};
            if (s[col]) return s[col];
            if (Object.keys(s).length > 0) return null;

            const fallback = this.scheduleFallbackMap(medId);
            return fallback[col] || null;
        },
        isCurrentHour(col) { return col === this.currentHour; },
        cellClass(medId, col) {
            const s = this.slotStatus(medId, col);
            if (s === 'administered') return 'bg-emerald-500';
            if (s === 'conferido')    return 'bg-sky-400';
            if (s === 'coletado')     return 'bg-teal-400';
            if (s === 'refused')      return 'bg-red-400';
            if (s === 'undone')       return 'bg-orange-300';
            if (s === 'rescheduled')  return 'bg-yellow-100 border border-yellow-400';
            if (s === 'scheduled')    return 'bg-amber-50 border border-amber-300';
            return 'bg-gray-50';
        },
        statusPt(s) {
            return {
                active:'Ativo', administered:'Administrado', conferido:'Conferido',
                coletado:'Coletado', refused:'Recusado', undone:'Desfeito',
                rescheduled:'Reaprazado', scheduled:'Pendente', suspended:'Suspenso',
            }[s] || s;
        },
        statusBadge(s) {
            if (s === 'active')       return 'text-emerald-700 bg-emerald-50 ring-emerald-200';
            if (s === 'administered') return 'text-sky-700 bg-sky-50 ring-sky-200';
            if (s === 'suspended')    return 'text-gray-500 bg-gray-100 ring-gray-200';
            return 'text-gray-500 bg-gray-100 ring-gray-200';
        },
        pageNums(total, current) {
            if (total <= 7) return Array.from({ length: total }, (_, i) => i+1);
            const pages = [1];
            if (current > 3) pages.push('…');
            for (let p = Math.max(2, current-1); p <= Math.min(total-1, current+1); p++) pages.push(p);
            if (current < total-2) pages.push('…');
            pages.push(total);
            return pages;
        },
        procStatusPt(s) {
            if (!s) return 'Pendente';
            return {
                Done:'Realizado', Completed:'Concluído', Analyzing:'Em análise', Collected:'Coletado', Scheduled:'Agendado', Waiting:'Pendente'
            }[s] || s;
        },
        procBadge(s) {
            if ([
                'Atendido', 'Cirurgia realizada', 'Executada', 'Realizado', 'Concluído', 'Done', 'Completed'
            ].includes(s)) return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200';
            if ([
                'Cancelada', 'Suspenso', 'Bloqueada', 'Falta justificada', 'Falta não justificada', 'Inativo', 'Interrompida'
            ].includes(s)) return 'bg-rose-50 text-rose-700 ring-1 ring-rose-200';
            if ([
                'Em exame', 'Em preparo', 'Iniciada', 'Em Consulta', 'Paciente chamado', 'Paciente em sala', 'Pós-operatório', 'Collected', 'Analyzing'
            ].includes(s)) return 'bg-blue-50 text-blue-700 ring-1 ring-blue-200';
            if (s === 'Done' || s === 'Completed') return 'bg-sky-50 text-sky-700 ring-1 ring-sky-200';
            if (s === 'Analyzing')  return 'bg-yellow-50 text-yellow-700 ring-1 ring-yellow-200';
            if (s === 'Collected')  return 'bg-blue-50 text-blue-700 ring-1 ring-blue-200';
            if (s === 'Scheduled')  return 'bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200';
            return 'bg-gray-100 text-gray-500 ring-1 ring-gray-200';
        },
        labelPt(l) { return { Urgent:'Urgente', PRN:'Se Necessário', ACM:'A Crit. Médico' }[l] || l; },
    };
};
</script>
@endscript
