# Módulo de Pendências de Paciente

Diretório: `app/Services/PendingEvents/`

---

## Arquitetura

```
PatientPendingEventsService          ← coordenador; cache 3 min por setor
│
├── AbstractPendingHandler           ← base: chunking, timming, log
│   ├── PrescriptionPendingHandler   ← proc/exames prescritos
│   ├── HemotherapyPendingHandler    ← hemoterapia 48h
│   ├── AntibioticPendingHandler     ← antimicrobianos em uso
│   ├── ChemotherapyPendingHandler   ← quimioterapia 30 dias
│   └── AgendaPendingHandler         ← cirurgias + exames agendados (AGENDA_PACIENTE)
│
└── Contracts/PendingEventHandler    ← interface handle(array &$results, array $attendances)
```

Cache key: `sector_pending_fast_{sectorId}` — TTL 3 minutos.

---

## Critérios por Handler

### 1. PrescriptionPendingHandler
**Tabelas:** `prescr_medica`, `prescr_procedimento`, `proc_interno` (PK: `nr_sequencia`, desc: `ds_proc_exame`), `procedimento`, `valor_dominio` (colunas: `cd_dominio`, `vl_dominio`, `ds_valor_dominio`)

**Quando aparece:**
- `pp.ie_status_execucao = '10'` (pendente)
- `pp.dt_coleta IS NULL` e `pp.dt_baixa IS NULL` (não executado)
- `pm.dt_liberacao IS NOT NULL` e `pm.dt_suspensao IS NULL` (prescrição ativa)
- `pp.ie_origem_proced <> 4` (exclui origem interna de sistema)

**Excluídos:**
- Procedimentos internos 5970, 1341, 5927 (itens de sistema Tasy)
- Descrições contendo "VISITA HOSPITALAR" ou "CULTURA AUTOMATIZADA"

**Tipo de evento:** `proc_exame`
**Janela:** sem limite de data (qualquer pendente não executado)

---

### 2. HemotherapyPendingHandler
**Tabela:** `cpoe_hemoterapia`

**Quando aparece:**
- `dt_programada BETWEEN SYSDATE AND SYSDATE + 2` (próximas 48h)
- `dt_suspensao IS NULL`

**Tipo de evento:** `hemoterapia`
**Urgência:** `ie_urgencia = 'S'`

**Tipos mapeados:**
| Código | Descrição |
|--------|-----------|
| 0 | Hemocomponente |
| 1 | Concentrado de Hemácias |
| 2 | Concentrado de Plaquetas |
| 3 | Plasma Fresco Congelado |
| 4 | Crioprecipitado |
| 5 | Concentrado de Granulócitos |

---

### 3. AntibioticPendingHandler
**Tabelas:** `cpoe_material`, `material`, `medic_ficha_tecnica`, `prescr_medica`

**Quando aparece:**
- `medic_ficha_tecnica.ie_antimicrobiano = 'S'`
- `TRUNC(dt_inicio) <= TRUNC(SYSDATE)` (iniciado hoje ou antes)
- `(dt_fim IS NULL OR TRUNC(dt_fim) >= TRUNC(SYSDATE))` (não finalizado)
- `dt_suspensao IS NULL` e `dt_liberacao IS NOT NULL`
- `nr_dia_util IS NOT NULL` (dia de uso calculado)
- EXISTS prescrição ativa para o atendimento

**Deduplicação:** ROW_NUMBER por (atendimento, nome do material) — mantém o dia de uso mais recente.

**Tipo de evento:** `antibiotico`
**Campo `ds_complemento`:** `"Dia N · DOSEunidade · VIA · H/Hh"` (ex: `"Dia 3 · 500mg · IV · 8/8h"`)

---

### 4. ChemotherapyPendingHandler
**Tabela/View:** `agenda_quimioterapia_pep_v`, `atendimento_paciente`

**Quando aparece:**
- `dt_agenda BETWEEN SYSDATE AND SYSDATE + 30`

**Tipo de evento:** `quimioterapia`
**Campos extras:** `ds_protocolo_medic`, `nr_ciclo`, `ds_local`, `nm_medico_resp`

> **Nota:** A view `agenda_quimioterapia_pep_v` é indexada por `cd_pessoa_fisica` (não `nr_atendimento`).
> O JOIN com `atendimento_paciente` resolve o mapeamento em uma única query.

---

### 5. AgendaPendingHandler
**Tabelas:** `agenda_paciente`, `proc_interno`, `procedimento`

**Quando aparece:**
- `dt_agenda >= TRUNC(SYSDATE)` e `dt_agenda <= SYSDATE + 30`
- `ie_status_agenda NOT IN ('C', 'S')` (não cancelado, não suspenso)
- `dt_executada IS NULL`

**Diferenciação:**
- **Cirurgia:** `ie_carater_cirurgia IS NOT NULL AND ie_carater_cirurgia <> 'X'`
- **Exame agendado:** `ie_carater_cirurgia IS NULL` + tem `nr_seq_proc_interno` ou `cd_procedimento`

**Caracteres de cirurgia:**
| Código | Tipo | Urgente |
|--------|------|---------|
| E | Eletiva | Não |
| U | Urgência | Sim |
| G | Emergência | Sim |
| X | Excluída (filtrada) | — |

**Resolução de descrição (NVL cascata):**
1. `proc_interno.ds_procedimento` (JOIN por nr_seq_proc_interno)
2. `procedimento.ds_procedimento` (JOIN por cd_procedimento quando sem proc_interno)
3. `agenda_paciente.ds_cirurgia` (fallback)

---

## Ordenação Final (PatientPendingEventsService)

Após todos os handlers executarem, os eventos são ordenados por:
1. **Urgentes primeiro** (`urgente = true`)
2. **Proximidade temporal** ao momento atual (`abs(dt_evento - SYSDATE)`)
3. Eventos sem `dt_evento` ficam por último

O **card do paciente** exibe apenas o **primeiro evento** da lista ordenada ("Pendências").
O **mini-modal de pendências** (acessível pelo botão de expansão no card) exibe **todos os grupos**.

---

## Eventos Adicionais (fora dos handlers)

Estes eventos são adicionados diretamente pelo `PatientPendingEventsService` antes dos handlers:

| Tipo | Origem | Urgente |
|------|--------|---------|
| `aviso/obito` | `pessoa_fisica.dt_obito` | Sim |
| `alta` | `atendimento_paciente.dt_alta` | Sim |
| `alta_medica` | `atendimento_paciente.dt_alta_medico` | Sim |
| `previsao_alta` | `atend_previsao_alta.dt_previsto_alta` | Não |

---

## View tasy.adep_v (candidata para otimizações futuras)

Colunas disponíveis:
```
CD_TIPO_ITEM, IE_TIPO_ITEM, DS_TIPO_ITEM
NR_ATENDIMENTO, CD_PACIENTE, NM_PACIENTE
NR_PRESCRICAO, CD_PRESCRITOR, NM_PRESCRITOR
NR_SEQ_ITEM, CD_ITEM, DS_ITEM
NR_SEQ_HORARIO, NR_SEQ_EXECUCAO
DT_HORARIO, DT_ADMINISTRACAO, DT_SUSPENSAO
CD_EXECUTOR, NM_EXECUTOR, IE_EXECUCAO, DS_EXECUCAO
```

`adep_v` agrega itens de administração de prescrições com horários programados e status de execução.
Potencialmente útil para:
- Substituir parte da lógica de `PrescriptionPendingHandler` (filtrando por `DT_ADMINISTRACAO IS NULL`)
- Obter o **próximo horário agendado** de um item pendente (`DT_HORARIO`)
- Verificar status de administração via `IE_EXECUCAO`/`DS_EXECUCAO`

Requer amostragem em produção para confirmar os valores de `IE_EXECUCAO` e `IE_TIPO_ITEM`.

---

## Performance por Handler (referência)

| Handler | Tabela principal | Chunk | JOINs |
|---------|-----------------|-------|-------|
| PrescriptionPendingHandler | prescr_medica+prescr_procedimento | 200 | proc_interno, procedimento (MIN), dominio_valor |
| HemotherapyPendingHandler | cpoe_hemoterapia | 200 | — |
| AntibioticPendingHandler | cpoe_material | 200 | material×2, medic_ficha_tecnica, EXISTS prescr_medica |
| ChemotherapyPendingHandler | atendimento_paciente + agenda_quimioterapia_pep_v | 200 | JOIN direto (1 query) |
| AgendaPendingHandler | agenda_paciente | 200 | proc_interno, procedimento (MIN) |

Todos herdam de `AbstractPendingHandler` que gerencia chunking, timming e log.
