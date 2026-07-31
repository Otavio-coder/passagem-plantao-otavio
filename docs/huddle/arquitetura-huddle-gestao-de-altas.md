# Módulo Huddle — Gestão de Altas
## Documento de Arquitetura Técnica

> **Status:** Proposta para alinhamento (pré-desenvolvimento)
> **Projeto base:** Sistema de Passagem de Plantão (SBAR) — Santa Casa de Porto Alegre
> **Setor piloto:** 5º andar — Hospital Santa Clara (HSC)
> **Condução clínica:** Enfermagem (Unidade de Internação)
> **Fontes:** Documento de Levantamento de Requisitos (Projetos de Inovação/SC) + artigo *Benevides Santos Paiva M. et al. "Reduction of hospital length of stay through the implementation of SAFER patient flow bundle and Red2Green days tool". BMJ Open Quality 2024;13:e002399.*

---

## Índice

1. [Objetivo do módulo](#1-objetivo-do-módulo)
2. [Fundamento metodológico (SAFER + Red2Green)](#2-fundamento-metodológico-safer--red2green)
3. [Reaproveitamento da plataforma existente](#3-reaproveitamento-da-plataforma-existente)
4. [Modelo de dados](#4-modelo-de-dados)
5. [Arquitetura de aplicação](#5-arquitetura-de-aplicação)
6. [Integração com o Tasy (autopreenchimento)](#6-integração-com-o-tasy-autopreenchimento)
7. [Autorização e regras operacionais](#7-autorização-e-regras-operacionais)
8. [Métricas e indicadores de ganho](#8-métricas-e-indicadores-de-ganho)
9. [Estratégia de testes](#9-estratégia-de-testes)
10. [Roadmap de entregas](#10-roadmap-de-entregas)
11. [Decisões pendentes com a área de negócio](#11-decisões-pendentes-com-a-área-de-negócio)

---

## 1. Objetivo do módulo

Digitalizar e padronizar o **Huddle de Gestão de Altas** — hoje realizado de forma heterogênea e sem método na instituição (ora como "round clínico" complexo, ora como reunião administrativa de unidade). O módulo substitui dois artefatos manuais:

- a evolução **"Round Multidisciplinar Unidade de Internação"** registrada no Tasy;
- o **quadro de parede** (Kanban físico) usado hoje no 5º andar do HSC.

Substitui esses dois artefatos por um **painel web, conduzido pela enfermagem, focado exclusivamente no que libera ou bloqueia a alta**, com **autopreenchimento a partir do Tasy** e **codificação por cores** para leitura rápida e priorização.

**Objetivo de negócio primário:** reduzir o **tempo médio de permanência (LOS)** e garantir alta no momento adequado, com segurança — controlando reinternações e aderência à previsão de alta.

O módulo vive **dentro da Passagem de Plantão** (não é aplicação separada) e, numa fase futura, os cards devem poder ser **refletidos em monitor** na unidade e **exportados como evolução no PEP do Tasy**.

---

## 2. Fundamento metodológico (SAFER + Red2Green)

O artigo não é leitura de apoio — é a **especificação funcional** do módulo. Ele descreve dois frameworks combinados, com evidência de redução de LOS de **19 → 14,2 dias (p<0.001)** sem piorar mortalidade nem reinternação.

### 2.1 SAFER patient flow bundle

Cinco elementos aplicados diariamente por paciente:

| Elemento | Significado clínico | Tradução no sistema |
|----------|---------------------|---------------------|
| **S** — Senior review before midday | Revisão pelo sênior antes do meio-dia | `senior_reviewed_at` + métrica de horário |
| **A** — All patients have EDD + CCD | **Previsão de alta (EDD)** e **critérios clínicos de alta (CCD)** definidos já no 1º dia | Campos centrais do card |
| **F** — Flow | Admissão do paciente na unidade o quanto antes | Métrica de fluxo (fase futura) |
| **E** — Early discharge | Alta pela manhã (meta institucional: **até 10h**) | Métrica "% alta antes das 10h / meio-dia" |
| **R** — Review >6 dias | Revisão de internações prolongadas | Alerta visual no card |

> **EDD** = *Expected Date of Discharge* (previsão de alta).
> **CCD** = *Clinical Criteria for Discharge* (critérios clínicos que, cumpridos, autorizam a alta).

### 2.2 Red2Green days

A mecânica visual central — o que o quadro de parede faz hoje:

- **Todo paciente começa o dia em RED.**
- O dia vira **GREEN** somente se o plano do dia **avançou a jornada de alta** *e* o paciente **precisava de leito agudo** naquele dia.
- Dia que permanece **RED** registra **até 2 motivos**.
- Contabiliza-se **quantos dias RED e GREEN** o paciente acumulou na internação — indicador direto de "dias que geraram valor vs. dias desperdiçados".

### 2.3 Catálogo de motivos de RED day (baseline: Tabela 3 do artigo)

```
Relacionado à equipe multidisciplinar (59,5% no estudo)
  - Falta de revisão sênior            (42,4% — maior causa isolada; concentra-se em fins de semana)
  - Espera de parecer/especialidade
  - Espera de decisão de conduta
  - Falta de plano de alta
  - Atitude conservadora quanto à alta
  - Falha de comunicação entre equipes

Relacionado a exames/laudos (26,5%)
  - Radiologia · Cardiologia · Endoscopia/Colonoscopia · Patologia · Laboratório

Externo à instituição (8,5%)
  - Aguardando nível alternativo de cuidado / família
  - Diálise ambulatorial
  - Outros

Relacionado a cirurgia/procedimentos invasivos (5,1%)

Diversos (2,3%)
```

> **Insight de implementação:** 42% dos red days do estudo vêm da **ausência de revisão sênior no fim de semana**. Isso implica que o sistema **precisa materializar o dia como RED mesmo quando não houve huddle** (senão os contadores por internação ficam furados). Ver [§5.4](#54-job-diário-de-materialização).

---

## 3. Reaproveitamento da plataforma existente

O módulo **não é greenfield**. A infraestrutura mais cara (carregamento de dados de paciente do Oracle, cache, tempo real, autorização) já existe e será reutilizada. Já há inclusive scaffolding do Huddle:

| Já existe no código | Reuso no Huddle |
|---------------------|-----------------|
| Rota `/huddle` (`huddle.report`) → `coming-soon` | Vira o painel Huddle |
| `App\Livewire\HuddlePatientModal` | Shell de navegação já pronto; recebe os campos de alta |
| View `huddle/patient-modal/index.blade.php` | Base do modal de detalhe |
| `PatientDataLoader` + loaders (demografia, pendências, multidisciplinar) | Carregamento em batch do Oracle com cache granular — reuso direto |
| `PatientPendingEventsService` (exames/procedimentos pendentes por setor) | **Fonte de autoderivação de motivos de red day** |
| Flag `is_nurse` em `users` + Spatie Permission | Base de "só enfermagem conduz" |
| Config de setor/leito por usuário | Configuração do setor-piloto |
| `SbarReport` (estado `#[Computed(persist:true)]`) | Padrão de estado a ser espelhado pelo painel Huddle |

**O que falta** é o núcleo de domínio da gestão de altas: persistência da cor do dia, EDD/CCD, motivos, contadores e a leitura dos campos de alta do Tasy.

---

## 4. Modelo de dados

Todo o **estado do Huddle é dado do sistema** (não do prontuário) e, portanto, mora no **MySQL** — respeitando a separação `EMR` (Oracle, read-only) vs. `System` (MySQL) já adotada no projeto.

### 4.1 Tabelas

```
huddle_patient_days
  id                        bigint PK
  nr_atendimento            bigint        -- chave da internação (Tasy); única por internação
  sector_id                 int
  huddle_date               date
  color                     enum('red','green')  DEFAULT 'red'   -- regra metodológica
  expected_discharge_date   date NULL     -- EDD (autopreenchido do Tasy, sobrescrevível)
  clinical_criteria         text NULL     -- CCD
  senior_reviewed_at        timestamp NULL         -- elemento S do SAFER
  created_by / updated_by   bigint FK users
  timestamps
  UNIQUE (nr_atendimento, huddle_date)
  INDEX (sector_id, huddle_date)

huddle_red_reasons
  id                        bigint PK
  huddle_patient_day_id     bigint FK
  category                  enum(...)     -- RedReasonCategory
  reason_code               enum(...)     -- RedReason (catálogo §2.3)
  auto_derived              boolean DEFAULT false   -- sugerido pelo sistema vs. manual
  timestamps
  -- máximo de 2 registros por dia é garantido na Action, não no schema
```

### 4.2 Contadores RED/GREEN por internação

Não é tabela nova — é **agregação** de `huddle_patient_days` agrupada por `nr_atendimento`:

```sql
SELECT color, COUNT(*)
FROM huddle_patient_days
WHERE nr_atendimento = ?
GROUP BY color;
```

O `nr_atendimento` já é único por internação, o que o torna a chave natural do contador. Exibido no card como **"X red / Y green"**.

### 4.3 Enums (PHP 8.2)

Modelar como enums nativos, casando com o padrão moderno do projeto e dando type-safety ao domínio:

- `App\Enums\Huddle\DayColor` — `Red`, `Green`
- `App\Enums\Huddle\RedReasonCategory` — `MultidisciplinaryTeam`, `Tests`, `External`, `Surgery`, `Misc`
- `App\Enums\Huddle\RedReason` — itens do catálogo §2.3, cada um com método `category()` e `label()`

---

## 5. Arquitetura de aplicação

Segue os padrões já estabelecidos no projeto: **Loaders plugáveis** para dados de Oracle, **Services** para orquestração, **Actions** para escrita, **Livewire** espelhando o SBAR, **Policies** para autorização.

### 5.1 Camada de dados de alta (Oracle)

```
App\Services\PatientData\Loaders\DischargeLoader implements SectorLoader
```

Novo loader plugável em `PatientDataLoader->include('discharge')`. Puxa do Tasy **em batch** (mesmo padrão anti-N+1 dos demais loaders), com cache próprio:

- previsão de alta (EDD),
- prescrição de alta médica (existe / não existe),
- solicitação de transporte,
- orientação de alta.

> Os campos exatos do Tasy dependem do mapeamento a ser fornecido pela área — ver [§11](#11-decisões-pendentes-com-a-área-de-negócio).

### 5.2 Camada de orquestração e escrita

```
App\Services\Huddle\HuddleBoardService
   → monta o board do setor: demografia + pendências (reuso PatientDataLoader)
     + dados de alta (DischargeLoader) + estado persistido (huddle_patient_days)

App\Actions\Huddle\SetDayColorAction
App\Actions\Huddle\RegisterRedReasonsAction   (aplica o cap de 2 motivos/dia)
App\Actions\Huddle\SetExpectedDischargeAction
   → escritas isoladas, testáveis, DRY — evita Fat Livewire
```

### 5.3 Camada de UI (Livewire)

```
App\Livewire\HuddleBoard          → o painel; card por leito, cor red/green,
                                    contador "X red / Y green", EDD e alertas.
                                    Espelha SbarReport, reusando o padrão de estado
                                    #[Computed(persist:true)] para não inflar o snapshot.

App\Livewire\HuddlePatientModal   → já existe; adicionar: setar cor, EDD/CCD,
                                    motivos de red day, checklist SAFER.

Modo Monitor (kiosk)              → render read-only para a TV da unidade
                                    ("refletir cards em monitor"); rota separada,
                                    sem capacidade de edição.
```

### 5.4 Job diário de materialização

Comando agendado (mesmo padrão de `routes/console.php`) que, no início do dia/turno, cria a linha `huddle_patient_days` **como RED** para cada leito ocupado dos setores-piloto.

**Por que é obrigatório:** sem materializar o dia, os contadores RED/GREEN por internação ignoram os dias sem huddle (fins de semana) — exatamente onde se concentra a maior causa de red day (falta de revisão sênior, 42% no estudo). A metodologia exige que o dia **comece** vermelho.

---

## 6. Integração com o Tasy (autopreenchimento)

O **autopreenchimento** é o principal diferencial pedido no documento ("automatização de preenchimento do que for possível", "sem gerar retrabalho para a assistência"). A estratégia:

1. `DischargeLoader` traz EDD, status de prescrição de alta, transporte e orientação de alta.
2. `PatientPendingEventsService` (já existente) diz quais exames/procedimentos estão pendentes.
3. Com essas duas fontes, o sistema **sugere automaticamente os motivos de red day** ("aguardando exame de radiologia", "sem prescrição de alta") marcando-os como `auto_derived = true`.
4. A enfermeira **confirma ou sobrescreve** — trabalho mínimo, decisão sempre humana.

**Direção do fluxo hoje (MVP):** Tasy → Huddle (somente leitura do prontuário).
**Fase futura:** Huddle → Tasy (write-back como evolução/árvore de cuidado no PEP) — fora do MVP, conforme o próprio documento.

---

## 7. Autorização e regras operacionais

### 7.1 Autorização

- **`App\Policies\HuddlePolicy`** + permissões Spatie novas: **`conduzir huddle`** (edição — enfermagem) e **`ver huddle`** (visualização — equipe multidisciplinar, gestão de leitos, médicos).
- A flag `is_nurse` já existente reforça a regra "a enfermagem é a condutora do Huddle".
- Middleware `verify.authorization` (já global nas rotas autenticadas) permanece.

### 7.2 Regras operacionais → validações/alertas

As regras vigentes descritas no documento viram comportamento de sistema:

| Regra de negócio | Comportamento no sistema |
|------------------|--------------------------|
| Previsão de alta (EDD) até 24h após internação | Alerta no card se EDD ausente após 24h; autofill do Tasy quando disponível |
| Meta de alta até as 10h | Destaque/métrica quando EDD é hoje e a alta não ocorreu até 10h |
| Não orientar alta antes da prescrição de alta | Dependência no checklist (orientação bloqueada até prescrição existir) |
| Transporte só após prescrição de alta médica | Dependência no checklist |
| SLAs de CDI e laboratório | Candidatos a motivos de red day / alertas (a confirmar com a área) |

---

## 8. Métricas e indicadores de ganho

Alinhados aos desfechos do artigo e às metas institucionais. Integráveis à área **Panorama** (admin) já existente:

- **Tempo médio de permanência (LOS)** — antes/depois, por setor.
- **% de altas antes das 10h / meio-dia** (desfecho secundário do artigo: subiu de 20% → 29%).
- **Proporção RED/GREEN** por setor e período (no estudo: 44% green / 56% red).
- **Distribuição de causas de red day** — para direcionar ação de melhoria (ex.: fim de semana sem sênior).
- **Aderência ao preenchimento** do huddle por enfermeiro/turno.
- **Controle de reinternação** e **aderência à previsão de alta**.

---

## 9. Estratégia de testes

Lógica de negócio pura, sem dependência de Oracle — testável desde a Fase 1 (Pest/PHPUnit):

- **Máquina de estado do dia**: começa RED; transição para GREEN apenas sob condições; reset diário.
- **Cap de 2 motivos** por red day (`RegisterRedReasonsAction`).
- **Contadores por internação** (agregação por `nr_atendimento`).
- **Policy**: enfermagem edita, multidisciplinar apenas visualiza.
- **Autoderivação de motivos** a partir de um conjunto de pendências mockado.

---

## 10. Roadmap de entregas

| Fase | Entrega | Depende de |
|------|---------|-----------|
| **0 — Descoberta técnica** | Mapear campos de alta no Tasy (EDD, prescrição de alta, transporte, orientação); fechar catálogo de red reasons com o negócio | Decisões §11.1 e §11.3 |
| **1 — MVP piloto (5º HSC)** | Board + modal com cor RED/GREEN, EDD/CCD manuais, motivos, contadores por internação, persistência MySQL, Policy de enfermagem, job diário de materialização, métricas básicas. Reusa loaders do SBAR | Fase 0 |
| **2 — Automação** | `DischargeLoader` (autofill), sugestão automática de red reasons via pending events, modo monitor (TV), alertas das regras operacionais (10h, 24h), aderência no Panorama | Fase 1 |
| **3 — Integração/rollout** | Write-back Tasy (evolução PEP / árvore de cuidado); expansão para outras unidades com suas particularidades | Fase 2 |

---

## 11. Decisões pendentes com a área de negócio

Pontos com ambiguidade no levantamento que **alteram a implementação** e precisam de definição antes/durante a Fase 0:

1. **Mapeamento de campos do Tasy (bloqueante técnico).** Quais colunas guardam a **previsão de alta (EDD)**, como detectar **prescrição de alta médica**, **solicitação de transporte** e **orientação de alta**. Sem isso o autopreenchimento não sai do papel.

2. **Quem define a cor e sob qual condição.** O documento diz "enfermagem conduz", mas a metodologia amarra o GREEN à **presença do médico sênior no round**. A enfermeira registra, mas o GREEN depende do round médico ter ocorrido? Definir o fluxo exato.

3. **Catálogo de red reasons.** Adotar a Tabela 3 do artigo como está, ou adaptar para a realidade da SC (incluir, por exemplo, SLA de CDI e laboratório citados no documento)?

4. **Modo monitor/TV.** A TV da unidade opera com usuário de serviço read-only autenticado, ou exige modo kiosk sem login? Há implicação de **segurança clínica** (dados de paciente em tela de área comum).

5. **Write-back no Tasy (PEP).** Confirmar que a exportação para evolução/árvore de cuidado é **Fase 3**, fora do MVP.

6. **Escopo de setores no piloto.** Confirmar que o piloto é exclusivamente o **5º andar do HSC** e que a configuração de leitos reutiliza o mecanismo já existente da Passagem de Plantão.

---

> **Referências**
> - Documento de Levantamento de Requisitos — Projeto SBAR/Huddle, Projetos de Inovação, Santa Casa de Porto Alegre.
> - Benevides Santos Paiva M, de Gouvêa Viana L, Melo de Andrade MV. *Reduction of hospital length of stay through the implementation of SAFER patient flow bundle and Red2Green days tool: a pre–post study.* BMJ Open Qual. 2024;13(1):e002399. doi:10.1136/bmjoq-2023-002399.
> - Mathews KS, et al. *Using the red/yellow/green discharge tool to improve the timeliness of hospital discharges.* Jt Comm J Qual Patient Saf. 2014;40(6):243-52.
> - NHS Improvement. *Guide to reducing long hospital stays.* London, 2018.
