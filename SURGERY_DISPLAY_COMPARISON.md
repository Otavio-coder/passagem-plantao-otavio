# Surgery (Cirurgia) Display Comparison Across 3 Contexts

This document compares where and how surgery event data is displayed in different parts of the application, with side-by-side code sections showing the inconsistencies.

---

## Summary Table

| Context | File | Data Source | Field Names | Room Info? | Observations Filtered? |
|---------|------|-------------|-------------|-----------|----------------------|
| **1. Tooltip** | `resources/views/sbar/patient/index.blade.php:463` | `$patient['procedimentos_cirurgicos']` | `descricao_padronizada`, `data_agenda`, `hora_agenda`, `carater_cirurgia`, `status`, `setor_execucao`, `observacoes` | ✗ NO | ✗ NO |
| **2. Pending Modal Table** | `resources/views/pendencias/index.blade.php:202` | `AgendaPendingHandler` + `PendingEventPresentation` | `descricao`, `dt_evento_formatted`, `carater`, `status_laudo`, `setor_execucao`, `local`, `sala` | ~ HIDDEN | ✗ NO |
| **3. Recommendations Tab** | `resources/views/sbar/patient/modal/tabs/recomendacoes/tabs/surgeries.blade.php:6` | `PatientTherapeuticPlanRepository::formatSurgery()` | `name`, `dt`, `carater`, `status`, `sector_name`, `local`, `sala`, `observacoes` | ✓ YES | ✓ YES |

---

## Context 1: Tooltip (Patient Card)

### Location
**File**: [resources/views/sbar/patient/index.blade.php](resources/views/sbar/patient/index.blade.php#L420-L505)

### Where it comes from
```php
@php
    $firstSurgery = $patient['procedimentos_cirurgicos'][0] ?? null;
@endphp
```

### What fields are displayed
```blade
@if(!empty($firstSurgery))
    <div class="py-0.5">
        <span class="font-medium">{{ $firstSurgery['data_agenda'] ?? 'N/A' }}</span>
        @if(!empty($firstSurgery['hora_agenda']))
            <span class="text-white/70 ml-1">às {{ $firstSurgery['hora_agenda'] }}</span>
        @endif
    </div>
    @php
        $firstSurgeryDescription = (string) ($firstSurgery['descricao_padronizada'] ?? $firstSurgery['procedimento'] ?? 'Procedimento');
        $firstSurgeryDescription = preg_replace('/\s*\(\s*Cirurgia\s+agenda\s+para\s+[^\)]*\)\s*$/iu', '', $firstSurgeryDescription) ?: $firstSurgeryDescription;
    @endphp
    <div class="py-0.5 text-white/90">{{ $firstSurgeryDescription }}</div>
    @if(!empty($firstSurgery['carater_cirurgia']))
        <div class="text-white/80 text-[10px] mt-0.5">{{ $firstSurgery['carater_cirurgia'] }}</div>
    @endif
    @if(!empty($firstSurgery['status']))
        <div class="text-white/80 text-[10px] mt-0.5">{{ $firstSurgery['status'] }}</div>
    @endif
    @if(!empty($firstSurgery['setor_execucao']))
        <div class="text-white/80 text-[10px] mt-0.5">{{ $firstSurgery['setor_execucao'] }}</div>
    @endif
    @if(!empty($firstSurgery['observacoes']))
        <div class="text-white/80 text-[10px] mt-0.5">Obs: {{ $firstSurgery['observacoes'] }}</div>
    @endif
@endif
```

### Data Structure Example
```php
[
    'data_agenda' => '25/04/2026',
    'hora_agenda' => '10:30',
    'descricao_padronizada' => 'Apendicectomia',
    'procedimento' => 'Apendicectomia', // fallback
    'carater_cirurgia' => 'Eletiva',
    'status' => 'Aguardando',
    'setor_execucao' => 'Centro Cirúrgico',
    'observacoes' => 'Paciente em jejum',
]
```

### Issues
- ⚠️ Uses `data_agenda` + `hora_agenda` (two fields) instead of formatted datetime
- ⚠️ Uses `descricao_padronizada` field name (different from Pending Modal and Recommendations)
- ⚠️ Does NOT show room/sala information
- ⚠️ Does NOT filter sensitive data from observations
- ⚠️ No `local` field exposure

---

## Context 2: Pending Modal (Relatório de Pendências)

### Location
**File**: [resources/views/pendencias/index.blade.php](resources/views/pendencias/index.blade.php#L202-L250)

### How data flows
```
AgendaPendingHandler.php
    ↓
    Builds event array with fields:
      - descricao
      - dt_evento_formatted
      - carater
      - status_laudo
      - setor_execucao
      - local
      - sala
      - observacoes
    ↓
PendingEventsReportController.buildRows()
    ↓
    Uses PendingEventPresentation::surgeryDescription($event)
    Uses PendingEventPresentation::surgeryDiagnosticLabel($event)
    ↓
Passed to view as $rows collection
```

### What fields are in the table
```blade
<table id="pendencias-table">
    <thead>
        <tr>
            <th>Atend.</th>
            <th>Paciente</th>
            <th>UGB</th>
            <th>UGA</th>
            <th>Tipo</th>
            <th>Setor de execução</th>
            <th>Classif.</th>
            <th>Pendência</th>          <!-- Surgery description (from surgeryDescription()) -->
            <th>Solicitação</th>       <!-- Data solicitacao -->
            <th>Data Prev. Execução</th>  <!-- dt_evento_formatted -->
            <th>Pendente há</th>
            <th>Motivo</th>           <!-- "Aguardando cirurgia" -->
            <th>Desc.</th>
        </tr>
    </thead>
    <tbody>
        @foreach($rows as $row)
            <tr>
                ...
                <td>{{ $row['item'] }}</td>  <!-- Calls PendingEventPresentation::surgeryDescription() -->
                <td>{{ $row['data_solicitacao'] }}</td>
                <td>{{ $row['data_prev_execucao'] }}</td>  <!-- dt_evento_formatted -->
                <td>{{ $row['tempo_pendente'] }}</td>
                <td>{{ $row['motivo_pendente'] }}</td>  <!-- "Aguardando cirurgia" -->
                <td>{{ $row['laudo'] ?? '-' }}</td>  <!-- Always "-" for surgeries -->
            </tr>
        @endforeach
    </tbody>
</table>
```

### surgeryDescription() Method
**File**: [app/Support/PendingEventPresentation.php:44](app/Support/PendingEventPresentation.php#L44)

```php
public static function surgeryDescription(array $event): string
{
    $parts = [];

    $descricao = trim((string) ($event['descricao'] ?? $event['descricao_padronizada'] ?? 'Cirurgia'));
    $descricao = trim((string) (preg_replace('/\s*\(\s*Cirurgia[^\)]*\)\s*$/iu', '', $descricao) ?? $descricao));
    if ($descricao !== '') {
        $parts[] = $descricao;
    }

    $local = trim((string) ($event['local'] ?? ''));
    if ($local !== '' && mb_strtolower($local) !== mb_strtolower($descricao)) {
        $parts[] = $local;
    }

    $sala = trim((string) ($event['sala'] ?? ''));
    if ($sala !== '') {
        $parts[] = 'Sala: '.$sala;
    }

    return implode(' - ', array_filter($parts, static fn (string $value): bool => $value !== ''));
}
```

**Output**: `"Apendicectomia - Bloco A - Sala: 5"`

### Data Structure from AgendaPendingHandler
**File**: [app/Services/PendingEvents/Handlers/AgendaPendingHandler.php:175](app/Services/PendingEvents/Handlers/AgendaPendingHandler.php#L175)

```php
$results[$row->nr_atendimento]['events'][] = [
    'tipo' => 'cirurgia',
    'icone' => 'general-surgery.svg',
    'descricao' => $row->descricao_proc ?? 'Cirurgia Agendada',
    'ds_subtipo' => 'Cirurgia '.$carater,
    'dt_evento' => $row->dt_evento,
    'dt_evento_formatted' => $dtFormatted,  // d/m/Y H:i format
    'ds_complemento' => implode(' · ', $parts),  // "Local: ... · Sala: ..."
    'carater' => $carater,  // 'Eletiva', 'Urgência', or 'Emergência'
    'setor_execucao' => $sectorLabels[(int) ($row->cd_setor_execucao ?? 0)] ?? ($row->cd_setor_execucao ?? null),
    'local' => $localAgenda !== '' ? $localAgenda : null,
    'sala' => $row->nr_seq_sala ?? null,
    'tipo_cirurgia_codigo' => $surgeryTypeCode,
    'cd_tipo_cirurgia' => $surgeryTypeCode,
    'status_agenda_codigo' => $statusCode !== '' ? $statusCode : null,
    'status_laudo' => $statusLabel,
    'observacoes' => $row->ds_observacao ?? null,
    'urgente' => in_array($row->ie_carater_cirurgia, ['U', 'G']),
];
```

### Issues
- ⚠️ Uses `descricao` field name (different from Tooltip's `descricao_padronizada` and Recommendations' `name`)
- ⚠️ Room and location info is INCLUDED in event data but NOT directly displayed in table (hidden in `surgeryDescription()` combined output)
- ⚠️ Does NOT filter sensitive data from observations
- ⚠️ `status_agenda_codigo` available but not used in display

---

## Context 3: Recommendations Tab (Patient Modal)

### Location
**File**: [resources/views/sbar/patient/modal/tabs/recomendacoes/tabs/surgeries.blade.php](resources/views/sbar/patient/modal/tabs/recomendacoes/tabs/surgeries.blade.php)

### How data flows
```
TasyService::getTherapeuticPlan()
    ↓
PatientTherapeuticPlanRepository::getTherapeuticPlan()
    ↓
Queries AGENDA_PACIENTE for surgeries
    ↓
formatSurgery() for each row
    ↓
Returns:
  'surgery' => ['count' => N, 'items' => [...formatted surgeries...]]
```

### What fields are rendered
```blade
@foreach($plan['surgery']['items'] as $surg)
    <div class="bg-white rounded-lg border shadow-sm px-3 py-2.5
            {{ $surg['is_urgent'] ? 'border-red-200 bg-red-50/20' : 'border-[#7712C7]/25 bg-[#7712C7]/[0.06]' }}">
        <div class="flex items-start justify-between gap-3">
            <div class="flex-1 min-w-0">
                <!-- Name/Description -->
                <p class="text-xs font-semibold text-gray-800 leading-snug">
                    {{ $surg['descricao_padronizada'] ?? $surg['procedimento'] ?? $surg['name'] }}
                </p>
                
                <div class="flex flex-wrap items-center gap-1.5 mt-1">
                    <!-- Character Badge (Eletiva/Urgência/Emergência) -->
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded
                                 {{ $surg['is_urgent'] ? 'bg-red-100 text-red-700 ring-1 ring-red-300' : 'bg-[#7712C7]/15 text-[#7712C7] ring-1 ring-[#7712C7]/30' }}">
                        {{ $surg['carater'] }}
                    </span>
                    
                    <!-- Status Badge -->
                    @if(!empty($surg['status']))
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 text-blue-700 ring-1 ring-blue-200">
                        {{ $surg['status'] }}
                    </span>
                    @endif
                    
                    <!-- Sector Badge -->
                    @if(!empty($surg['sector_name']) || !empty($surg['sector_code']))
                    <span class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                        <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                        {{ !empty($surg['sector_name']) ? $surg['sector_name'] : ('Setor ' . $surg['sector_code']) }}
                    </span>
                    @endif
                    
                    <!-- Room Badge -->
                    @if($surg['sala'])
                    <span class="text-[10px] text-gray-500 font-medium">{{ $surg['sala'] }}</span>
                    @endif
                    
                    <!-- Date/Time Badge -->
                    @if($surg['dt'])
                    <span class="text-[10px] font-mono text-gray-400">
                        <i class="fa-regular fa-calendar mr-0.5"></i>{{ $surg['dt'] }}
                    </span>
                    @endif
                </div>
                
                <!-- Observations (with filtering) -->
                @php
                    $surgeryDetail = $surg['observacoes'] ?? $surg['observation'] ?? null;
                @endphp
                @if(!empty($surgeryDetail))
                <p class="text-[10px] text-gray-500 italic mt-1.5">Obs: {{ $surgeryDetail }}</p>
                @endif
            </div>
        </div>
    </div>
@endforeach
```

### formatSurgery() Method
**File**: [app/Repositories/EMR/PatientTherapeuticPlanRepository.php:1009](app/Repositories/EMR/PatientTherapeuticPlanRepository.php#L1009)

```php
public function formatSurgery(object $row): array
{
    $caracterMap = [
        'E' => 'Eletiva',
        'U' => 'Urgência',
        'G' => 'Emergência',
    ];

    $carater = $caracterMap[$row->flag1 ?? ''] ?? 'Não informado';
    $is_urgent = in_array($row->flag1 ?? '', ['U', 'G']);

    $name = $this->normalizeSurgeryDescription((string) ($row->name ?? 'Cirurgia não especificada'));

    $surgeryObservation = $this->filterSensitiveSurgeryObservation($row->observation ?? null);

    return [
        'id' => (int) ($row->id ?? 0),
        'name' => $name,  // Cleaned description
        'carater' => $carater,  // 'Eletiva', 'Urgência', 'Emergência'
        'status' => $this->agendaStatusLabel((string) ($row->status_raw ?? '')),
        'sector_code' => isset($row->setor_raw) ? (string) $row->setor_raw : null,
        'sector_name' => ! empty($row->setor_desc_raw) ? trim((string) $row->setor_desc_raw) : null,
        'is_urgent' => $is_urgent,  // Boolean flag
        'dt' => $row->schedule ?? null,  // e.g., "25/04/26 10:30"
        'sala' => ! empty($row->extra1) ? 'Sala '.$row->extra1 : null,
        'description' => ! empty($row->extra2) ? $row->extra2 : null,
        'tipo_cirurgia_codigo' => ! empty($row->extra3) ? (int) $row->extra3 : null,
        'local' => ! empty($row->extra4) ? trim((string) $row->extra4) : (! empty($row->extra2) ? trim((string) $row->extra2) : null),
        'observation' => ! empty($row->observation) ? $row->observation : null,
        'observacoes' => $surgeryObservation,  // Filtered version
        'has_details' => ! empty($row->observation) || ! empty($surgeryObservation),
    ];
}
```

### Sensitive Data Filtering
**Important**: Only the Recommendations tab filters observations

```php
private function filterSensitiveSurgeryObservation(?string $observation): ?string
{
    if (empty($observation)) {
        return null;
    }

    $patterns = [
        '/R\$\s*[\d.,]+/i',           // Removes "R$ 1.500,00"
        '/valor.*[\d.,]+/i',          // Removes "valor: 2.000"
        '/custo.*[\d.,]+/i',          // Removes "custo: 5.000"
        '/autorizado.*coordenação/i', // Removes "autorizado pela coordenação"
    ];

    $filtered = $observation;
    foreach ($patterns as $pattern) {
        $filtered = preg_replace($pattern, '', (string) $filtered);
    }

    $normalized = trim((string) $filtered);

    return $normalized !== '' ? $normalized : 'Informações disponíveis no prontuário';
}
```

### Data Structure from formatSurgery
```php
[
    'id' => 42,
    'name' => 'Apendicectomia',  // Normalized, regex cleaned
    'carater' => 'Eletiva',  // or 'Urgência'/'Emergência'
    'status' => 'Aguardando',  // Mapped label
    'sector_code' => '250',
    'sector_name' => 'Centro Cirúrgico',
    'is_urgent' => false,  // Boolean extracted from carater code
    'dt' => '25/04/26 10:30',  // Pre-formatted
    'sala' => 'Sala 5',  // Pre-formatted "Sala N"
    'description' => null,
    'tipo_cirurgia_codigo' => 123,
    'local' => 'Bloco A',
    'observation' => 'Paciente em jejum desde meia-noite - R$ 5.000,00 autorizado',
    'observacoes' => 'Paciente em jejum desde meia-noite',  // Sensitive data removed!
    'has_details' => true,
]
```

### Advantages
- ✅ Complete room information (`sala`)
- ✅ Complete location information (`local`)
- ✅ Boolean flag for urgency (`is_urgent`)
- ✅ **Sensitive data filtering** in observations
- ✅ All fields explicitly exposed and typed

---

## KEY INCONSISTENCIES SUMMARY

### 1. **Field Names for Description**
```
Tooltip:          descricao_padronizada / procedimento
Pending Modal:    descricao
Recommendations:  name
```

### 2. **Date/Time Format**
```
Tooltip:          data_agenda (string) + hora_agenda (string) — NOT combined
Pending Modal:    dt_evento_formatted (string) — "25/04/26 10:30" format
Recommendations:  dt (string) — "25/04/26 10:30" format
```

### 3. **Room/Sala Information**
```
Tooltip:          NOT displayed
Pending Modal:    Available (sala field) but NOT displayed in table
Recommendations:  ✅ Properly displayed as "Sala N"
```

### 4. **Location/Local Information**
```
Tooltip:          NOT displayed
Pending Modal:    Available (local field) but hidden in surgeryDescription()
Recommendations:  ✅ Explicitly shown
```

### 5. **Urgent Flag**
```
Tooltip:          NOT exposed
Pending Modal:    Available (urgente field) but NOT displayed
Recommendations:  ✅ Exposed as boolean is_urgent with color coding
```

### 6. **SECURITY - Observations Filtering** ⚠️
```
Tooltip:          ✗ NO filtering — Shows raw observations
Pending Modal:    ✗ NO filtering — Shows raw observations
Recommendations:  ✅ YES — Removes: R$ amounts, "valor", "custo", "autorizado"
```

### 7. **Status Field**
```
Tooltip:          status (pre-mapped)
Pending Modal:    status_laudo (pre-mapped)
Recommendations:  status (mapped via agendaStatusLabel())
```

---

## RECOMMENDATIONS

### High Priority
1. **Unify field names** across all three contexts (prefer `name` for description)
2. **Standardize datetime format** to pre-formatted combined string (like Pending Modal and Recommendations)
3. **Apply sensitive data filtering** to Tooltip and Pending Modal (not just Recommendations)

### Medium Priority
4. Display room/location info in Tooltip
5. Expose `is_urgent` boolean flag in Tooltip and Pending Modal
6. Standardize `status` field across all contexts

### Low Priority
7. Consider creating centralized `SurgeryDataTransformer` for consistency

