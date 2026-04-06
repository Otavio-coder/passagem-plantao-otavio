# Pending Items Display Analysis - Complete Breakdown

## Overview
This document shows **how pending items are currently displayed** in SbarReport (card + modal) and PatientModal components, along with the key differences and what needs synchronization.

---

## Data Flow Architecture

```
PatientPendingEventsService
    ↓ (getPendingEventsForSector)
[nr_atendimento => ['events' => [...], 'discharge' => null]]
    ↓
TasyService.fetchBatchData()
    ↓ (organizes by attendance)
$batchData['pending_events'][nr_atendimento]
    ↓
SbarFormatter.formatPatientForSbar()
    ↓ (injects into patient array)
'pending_events' => $batchData['pending_events'][$attendanceNumber] ?? []
    ↓
Views render with different presentations
```

---

## 1. SBAR Report - Card Display (Inline)

**Location**: `resources/views/sbar/patient/index.blade.php` : Lines 1049-1270

### Strategy: Show FIRST Event Only (Most Urgent/Nearest)

```blade
{{-- Pending Events Section --}}
@php
    $pendingEvents = $patient['pending_events'] ?? [];
    
    // ✓ Filter to events from today onwards
    $futurePendingEvents = array_values(array_filter($pendingEvents, function ($ev) use ($todayStart) {
        $dtEvento = $ev['dt_evento'] ?? null;
        if (empty($dtEvento)) {
            return false;
        }
        try {
            return \Carbon\Carbon::parse($dtEvento)->greaterThanOrEqualTo($todayStart);
        } catch (\Exception $e) {
            return false;
        }
    }));

    $firstEvent = $futurePendingEvents[0] ?? null; // ← FIRST EVENT ONLY
    $hasPendingCard = $firstEvent !== null;
@endphp

@if($hasPendingCard)
    @php
        $fIcon   = $firstEvent['icone'] ?? 'alert-circle.svg';
        $fUrgent = $firstEvent['urgente'] ?? false;
        $fTipo   = $firstEvent['tipo'] ?? 'outros';

        // ✓ Dynamic color coding by tipo
        [$fBg, $fTxtDesc, $fTxtTime, $fPulseColor] = match(true) {
            in_array($fTipo, ['alta', 'aviso', 'obito'])
                => ['bg-[#E8E8E8] border border-gray-300', 'text-gray-700 font-bold', 'text-gray-600 font-semibold', 'bg-gray-400'],
            $fTipo === 'cirurgia' && $fUrgent
                => ['bg-[#7712C7]/10 border border-[#7712C7]', 'text-[#7712C7] font-bold', 'text-[#7712C7] font-semibold', 'bg-[#7712C7]'],
            $fTipo === 'hemoterapia'
                => ['bg-red-50/70 border border-red-300', 'text-red-700 font-semibold', 'text-red-600 font-medium', 'bg-red-500'],
            in_array($fTipo, ['proc_exame', 'exame'])
                => ['bg-blue-50/60 border border-blue-200', 'text-blue-700 font-semibold', 'text-blue-600 font-medium', 'bg-blue-400'],
            $fTipo === 'procedimento'
                => ['bg-indigo-50/60 border border-indigo-200', 'text-indigo-700 font-semibold', 'text-indigo-600 font-medium', 'bg-indigo-400'],
            // ... more types
        };
        
        $showPulse = $fUrgent || in_array($fTipo, ['alta', 'aviso']);
    @endphp

    <div class="rounded-lg p-2 {{ $fBg }}">
        <div class="flex items-start gap-2">
            <img src="{{ asset('images/icons/patient-card/' . $fIcon) }}"
                 class="w-4 h-4 flex-shrink-0 mt-0.5 opacity-90" alt="">
            <div class="flex-1 min-w-0">
                <div class="text-[11px] {{ $fTxtDesc }} leading-tight line-clamp-2">
                    {{ $firstEvent['descricao'] ?? 'Sem descrição' }}
                </div>
                <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                    @if(!empty($firstEvent['dt_evento_formatted']))
                        <span class="text-[9px] {{ $fTxtTime }}">
                            {{ $firstEvent['dt_evento_formatted'] }}
                        </span>
                    @endif
                    @if(!empty($firstEvent['tempo_pendente']))
                        <span class="text-[9px] text-gray-500">
                            · {{ $firstEvent['tempo_pendente'] }}
                        </span>
                    @endif
                </div>
            </div>
            @if($showPulse)
                <span class="w-2 h-2 rounded-full {{ $fPulseColor }} animate-pulse flex-shrink-0 mt-1"></span>
            @endif
        </div>
    </div>
@endif
```

### Card Output Example
```
┌─────────────────────────────────┐
│ Pendências                  ↗    │  (expand icon)
│                                  │
│ 🧪 Exame de sangue              │  (icon + descricao in blue)
│    15/04/2026 · 3 dias          │  (date + duration)
└─────────────────────────────────┘
```

---

## 2. SBAR Report - Modal Display (All Events, Grouped & Paginated)

**Location**: `resources/views/sbar/patient/index.blade.php` : Lines 1280-1500+

### Strategy: Group by Tipo, Filter by Date Range, Paginate (8 items/page)

#### Header with Filter Controls
```blade
{{-- Barra de filtro --}}
<div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50/80">
    <span class="text-[10px] text-gray-500 font-semibold uppercase">Período:</span>
    
    {{-- Toggle between date-filtered vs all events --}}
    <button @click="$dispatch('pending-filter', { v: false })"
            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all"
            :class="!pendingShowAll ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200'">
        Ontem – Hoje – Amanhã  {{-- Only show events within yesterday-today-tomorrow --}}
    </button>
    
    <button @click="$dispatch('pending-filter', { v: true })"
            class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all"
            :class="pendingShowAll ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200'">
        Todas as Pendências  {{-- Show all events regardless of date --}}
    </button>
</div>
```

#### Grouping and Pagination Logic
```blade
@php
    // ✓ Group by tipo with specific ordering
    $grouped = [];
    $groupOrder = ['alta', 'alta_medica', 'aviso', 'exame', 'procedimento', 'cirurgia', 'hemoterapia', 'quimioterapia', 'antibiotico', 'previsao_alta', 'outros'];
    
    foreach ($pendingEvents as $ev) {
        $tipo = $ev['tipo'] ?? 'outros';
        if ($tipo === 'proc_exame') {
            $tipo = 'exame'; // ✓ Normalize legacy type names
        }
        $grouped[$tipo][] = $ev;
    }
    
    // ✓ Sort groups by predefined order
    uksort($grouped, fn($a,$b) =>
        (array_search($a, $groupOrder) ?: 99) - (array_search($b, $groupOrder) ?: 99)
    );
@endphp

@foreach($grouped as $groupTipo => $groupEvents)
    @php
        $groupJson = json_encode(array_values($groupEvents), JSON_HEX_QUOT | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
        
        // ✓ Group label mapping
        $groupLabel = match($groupTipo) {
            'exame','proc_exame' => 'Exames/Laboratório',
            'procedimento'  => 'Procedimentos',
            'cirurgia'      => 'Cirurgias Agendadas',
            'hemoterapia'   => 'Hemoterapia',
            'quimioterapia' => 'Quimioterapia',
            'antibiotico'   => 'Antimicrobianos Ativos',
            'aviso'         => 'Avisos',
            'alta'          => 'Alta Efetivada',
            'alta_medica'   => 'Alta Médica',
            'previsao_alta' => 'Previsão de Alta',
            default         => ucfirst($groupTipo),
        };
        
        // ✓ Color scheme per group tipo
        [$gBorderHdr, $gBgHdr, $gTxtHdr, $gBorderCard, $gBgCard] = match($groupTipo) {
            'aviso','alta','obito','alta_medica'
                            => ['border-gray-300',     'bg-[#E8E8E8]',    'text-gray-700',  'border-gray-200',    'bg-[#E8E8E8]/80'],
            'cirurgia'      => ['border-[#7712C7]/30', 'bg-[#7712C7]/10', 'text-[#7712C7]', 'border-[#7712C7]/20','bg-[#7712C7]/5'],
            'hemoterapia'   => ['border-red-300',      'bg-red-50/70',    'text-red-700',   'border-red-200',     'bg-red-50/40'],
            'quimioterapia' => ['border-[#0A4700]/30', 'bg-[#0A4700]/10', 'text-[#0A4700]', 'border-[#0A4700]/20','bg-[#0A4700]/5'],
            'antibiotico'   => ['border-[#BDAD02]/50', 'bg-[#BDAD02]/10', 'text-[#5C5300]', 'border-[#BDAD02]/30','bg-[#BDAD02]/5'],
            'exame','proc_exame'
                            => ['border-blue-200',     'bg-blue-50/60',   'text-blue-700',  'border-blue-200',    'bg-blue-50/40'],
            'procedimento'  => ['border-indigo-200',   'bg-indigo-50/60', 'text-indigo-700','border-indigo-200',  'bg-indigo-50/40'],
            default         => ['border-gray-200',     'bg-white/30',     'text-[#062047]', 'border-gray-200',    'bg-gray-50/50'],
        };
    @endphp

    {{-- Alpine.js component with its own pagination --}}
    <div x-data="{
            allItems: {{ $groupJson }},
            showAll: false,
            page: 1,
            perPage: 8,
            
            // ✓ Dynamic pagination: calculate items per page based on modal height
            calcPerPage() {
                const modal = document.querySelector('[data-pending-modal-panel]');
                const modalHeight = modal ? modal.clientHeight : window.innerHeight;
                const reservedSpace = 430; // header + filter + spacing
                const itemHeight = 96;
                const computed = Math.floor((modalHeight - reservedSpace) / itemHeight);
                this.perPage = Math.max(3, Math.min(10, computed || 8));
                if (this.page > this.pages) this.page = this.pages;
            },
            
            // ✓ Filter logic: either show all or only 'is_near' items
            get items() {
                return this.showAll
                    ? this.allItems
                    : this.allItems.filter(i => i.is_near);
            },
            
            // ✓ Slice items for current page
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
         class="rounded-xl border {{ $gBorderHdr }} overflow-hidden">

        {{-- Group header --}}
        <div class="flex items-center justify-between px-3 py-2 {{ $gBgHdr }} border-b {{ $gBorderHdr }}">
            <span class="text-xs font-bold {{ $gTxtHdr }} uppercase tracking-wide">
                {{ $groupLabel }}
            </span>
        </div>

        {{-- Items list --}}
        <div class="divide-y divide-gray-100/80">
            <template x-for="(ev, idx) in paged" :key="idx">
                <div class="px-3 py-2.5 hover:brightness-95 transition-all {{ $gBgCard }}"
                     :class="{ 'bg-red-50/60': ev.urgente }">
                    
                    {{-- Icon + Description + Badge --}}
                    <div class="flex items-start gap-2">
                        <img :src="'/images/icons/patient-card/' + (ev.icone || 'alert-circle.svg')"
                             class="w-4 h-4 flex-shrink-0 mt-0.5 opacity-80" alt="">
                        <div class="flex-1 min-w-0">
                            <div class="text-xs font-semibold leading-snug"
                                 :class="ev.urgente ? 'text-red-700' : 'text-[#062047]'"
                                 x-text="ev.descricao || 'Sem descrição'"></div>
                            
                            {{-- Subtype + Prescriber --}}
                            <div x-show="ev.ds_subtipo || ev.nm_prescritor"
                                 class="text-[10px] text-gray-500 mt-0.5 flex flex-wrap gap-x-2">
                                <span x-show="ev.ds_subtipo" x-text="ev.ds_subtipo"></span>
                                <span x-show="ev.nm_prescritor" x-text="'· ' + ev.nm_prescritor" class="text-gray-400"></span>
                            </div>
                        </div>
                        
                        {{-- Status badge (e.g., "Liberado", "Aguardando") --}}
                        <span x-show="ev.status_laudo"
                              x-text="ev.status_laudo"
                              class="text-[9px] px-1.5 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap"
                              :class="ev.urgente ? 'bg-red-500 text-white' : 'bg-[#004D9D]/10 text-[#004D9D]'"></span>
                    </div>
                    
                    {{-- Dates and timing --}}
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
                        <template x-if="ev.dt_coleta">
                            <span>
                                <span class="font-medium text-gray-600">Coletado: </span>
                                <span x-text="ev.dt_coleta"></span>
                            </span>
                        </template>
                        
                        {{-- How long pending (colored by urgence) --}}
                        <span x-show="ev.tempo_pendente"
                              x-text="ev.tempo_pendente"
                              class="font-semibold"
                              :class="ev.urgente ? 'text-red-600' : 'text-[#0071B9]'"></span>
                        
                        {{-- Additional text (e.g., reason why pending) --}}
                        <span x-show="ev.ds_complemento"
                              x-text="ev.ds_complemento"
                              class="text-gray-500 italic"></span>
                    </div>
                    
                    {{-- Reason pending (if available) --}}
                    <template x-if="ev.motivo_pendente">
                        <div class="mt-1 flex items-center gap-1 text-[10px]">
                            <i class="fas fa-circle-info text-yellow-600 fa-xs"></i>
                            <span class="text-yellow-700 italic" x-text="ev.motivo_pendente"></span>
                        </div>
                    </template>
                </div>
            </template>
        </div>
        
        {{-- Pagination controls (if more than 1 page) --}}
        <template x-if="pages > 1">
            <div class="flex items-center justify-between px-3 py-2 bg-gray-50 border-t border-gray-100">
                <button @click="page = Math.max(1, page - 1)" :disabled="page === 1"
                        class="text-xs font-semibold text-[#004D9D] hover:text-[#003d7a] disabled:opacity-50">
                    ← Anterior
                </button>
                <span class="text-xs text-gray-600">
                    Página <span x-text="page"></span> de <span x-text="pages"></span>
                </span>
                <button @click="page = Math.min(pages, page + 1)" :disabled="page === pages"
                        class="text-xs font-semibold text-[#004D9D] hover:text-[#003d7a] disabled:opacity-50">
                    Próximo →
                </button>
            </div>
        </template>
    </div>
@endforeach
```

### Modal Output Example
```
┌─────────────────────────────────────────────────────┐
│ Pendências do Paciente                          ✕   │
│─────────────────────────────────────────────────────│
│ Período: [Ontem-Hoje-Amanhã] [Todas as Pendências] │
├─────────────────────────────────────────────────────┤
│ EXAMES/LABORATÓRIO                                  │
│ ├─ 🧪 Hemograma                  Liberado          │
│ │   Hemograma·Dr.Silva                             │
│ │   Previsto: 15/04/2026 · 3 dias                  │
│ │                                                  │
│ └─ 🧪 Glicemia                                      │
│    Glicemia·Dr.Silva                               │
│    Previsto: 16/04/2026 · 4 dias                   │
├─────────────────────────────────────────────────────┤
│ CIRURGIAS AGENDADAS                                 │
│ ├─ 🏥 Cirurgia de Apêndice        CONFIRMADA       │
│    Cirurgia de Apêndice                            │
│    Previsto: 20/04/2026 · 7 dias                   │
│                                                    │
└─────────────────────────────────────────────────────┘
   [← Anterior]  Página 1 de 2  [Próximo →]
```

---

## 3. PatientModal Display

**Location**: `app/Livewire/PatientModal.php` | `resources/views/sbar/patient/modal/`

### Current Status: **NOT DISPLAYING PENDING_EVENTS**

#### What PatientModal Shows Instead
```blade
<!-- resources/views/sbar/patient/modal/tabs.blade.php -->

<!-- Tabs: Situação (S), Background (B), Avaliação (A), Recomendações (R) -->

<!-- Situação (tab-s): Clinical status -->
<!-- Background (tab-b): Medical history, allergies, isolation -->
<!-- Avaliação (tab-a): Chat and evaluations -->
<!-- Recomendações (tab-r): Therapeutic plan -->
```

#### Therapeutic Plan Structure (NOT pending events)
```php
// PatientModal receives therapeuticPlan via getTherapeuticPlan()
// Structure:
[
    'medications' => [...], // Current medications with schedule
    'procedures' => [...],  // Active procedures
    'surgeries' => [...],   // Scheduled surgeries
    'interventions' => [...], // Nursing interventions
    'orders' => [...],      // Medical orders
    'exams' => [...],       // Active exams
    'procedures' => [...],
    'nutrition' => [...],
    'hemotherapy' => [...],
    'chemotherapy' => [...],
    'dialysis' => [...],
    'gasotherapy' => [...],
]
```

**Key Difference**: The therapeutic plan and pending_events are **independent data streams**:
- **Therapeutic Plan**: Current active medical interventions
- **Pending Events**: Items awaiting results, authorization, execution, or scheduled for specific dates

---

## 4. Data Structure Comparison

### Event Data Fields (Available in pending_events array)

| Field | Type | SBAR Card | SBAR Modal | Purpose |
|-------|------|-----------|-----------|---------|
| `tipo` | string | ✓ (for color) | ✓ (for grouping) | Event type |
| `descricao` | string | ✓ | ✓ | Display text |
| `icone` | string | ✓ | ✓ | Icon filename |
| `dt_evento` | date | ✓ (filter) | ✓ (filter) | Scheduled/due date |
| `dt_evento_formatted` | string | ✓ | ✓ | Localized date display |
| `dt_solicitacao` | date | ✗ | ✓ | When ordered |
| `dt_autorizacao` | date | ✗ | ✓ | When approved |
| `dt_coleta` | date | ✗ | ✓ | When collected/done |
| `nr_prescricao` | string | ✗ | ✓ | Prescription ID |
| `ds_subtipo` | string | ✗ | ✓ | Sub-category |
| `nm_prescritor` | string | ✗ | ✓ | Prescriber name |
| `status_laudo` | string | ✗ | ✓ | Report status badge |
| `urgente` | bool | ✓ | ✓ | Urgent flag |
| `tempo_pendente` | string | ✓ | ✓ | Duration pending |
| `is_near` | bool | ✗ (implicit) | ✓ | Within yesterday-tomorrow |
| `motivo_pendente` | string | ✗ | ✓ | Why pending |
| `ds_complemento` | string | ✗ | ✓ | Additional info |

---

## 5. Key Filtering & Processing Logic

### From PatientPendingEventsService (app/Services/PatientPendingEventsService.php)

**How pending events are fetched**:
```php
// Main query gets basic context
$rows = DB::connection('tasy')->select("
    SELECT nr_atendimento, cd_pessoa_fisica, dt_alta, dt_alta_medico, ...
    FROM tasy.unidade_atendimento ua
    WHERE ua.cd_setor_atendimento = :sector_id
");

// 5 specialized handlers enrich the data:
$handlers = [
    new PrescriptionPendingHandler,      // Exams/procedures (ie_status_execucao='10')
    new HemotherapyPendingHandler,       // Transfusions within 48h
    new AntibioticPendingHandler,        // Active antimicrobials
    new ChemotherapyPendingHandler,      // Chemo appointments within 30d
    new AgendaPendingHandler,            // Scheduled surgeries/exams
];

return [
    'events' => [...],      // All pending items
    'discharge' => [...],   // Discharge info if applicable
];
```

### From PendingEventPresentation (app/Support/PendingEventPresentation.php)

**Formats display strings**:
- Generates `descricao` (what to display)
- Generates `motivo_pendente` (why it's pending)
- Normalizes tipo names (proc_exame → exame)
- Filters/excludes certain patterns

---

## 6. Synchronization Needs

### ✓ Already Synchronized
- Data source is unified (PatientPendingEventsService → TasyService)
- Color coding consistent across displays
- Date formatting consistent
- Event type ordering consistent

### ⚠ Requires Attention
1. **PatientModal Integration**: No pending_events tab currently
2. **Filtering Logic**: Both views filter by date, but PatientModal doesn't use this
3. **Type Normalization**: Conversion of `proc_exame` → `exame` happens in views, should centralize
4. **Cache Strategy**: SBAR modal caches at TasyService level (900s), PatientModal doesn't

### 📋 What Needs Synchronizing
```php
// Centralize in PatientPendingEventsService or separate utility:

// Type normalization
PendingEventTypeClassifier::normalize($tipo); // proc_exame → exame

// Date range filtering
PendingEventFilter::getEventsForDateRange($events, $startDate, $endDate);

// Grouping logic
PendingEventGrouper::groupByType($events, $precedenceOrder);

// Display string generation
PendingEventPresentation::formatForDisplay($event); // Returns formatted array
```

---

## Key Takeaway

**Current State**: Pending items are displayed **only in SBAR Report** (card + modal), NOT in PatientModal.

**Data Flow**: All displays receive data from the same source (PatientPendingEventsService), but present it differently:
- **Card**: First event only, inline, no grouping
- **Modal**: All events, grouped by tipo, paginated, filterable by date range
- **PatientModal**: Not used (focuses on therapeutic plan instead)

**To Add PatientModal Support**: Would need to render the pending_events tab alongside therapeutic plan tabs, using the same grouping/pagination logic as the SBAR modal.
