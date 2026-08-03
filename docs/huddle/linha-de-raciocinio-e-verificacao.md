# Huddle — Gestão de Altas
## Linha de raciocínio do desenvolvimento + Quadro de verificação

> Documento de acompanhamento do módulo. Consolida a lógica de construção e o
> estado geral, para revisar e verificar tudo num lugar só.

---

# Parte 1 — Linha de raciocínio

A ordem de construção não foi aleatória. Cada etapa se apoia na anterior, do que é
mais estável (domínio) para o que é mais visível (tela), sempre reaproveitando o que
o sistema já tem.

### 1. Entender o problema antes de codar
Partimos de dois documentos: o levantamento de requisitos (Inovação/SC) e o artigo
científico (BMJ Open Quality 2024). O artigo não é leitura de apoio — é a
especificação da metodologia **SAFER + Red2Green**, que define o modelo de dados e o
fluxo. Conclusão que guiou tudo: o Huddle é um painel focado **exclusivamente no que
libera ou trava a alta**, conduzido pela enfermagem, com cores para leitura rápida.

### 2. Separar o que é "meu" do que é "do Tasy"
Decisão central: o **estado do Huddle** (cor do dia, previsão de alta, motivos) é dado
do sistema → mora no **MySQL**. Os **dados clínicos** (paciente, leito, pendências,
previsão de alta) vêm do **Oracle/Tasy**, somente leitura. Isso respeita a separação
EMR (Oracle) × System (MySQL) que o projeto já adota.

### 3. Construir de baixo para cima
Fomos do alicerce ao acabamento, para nada ficar apoiado no ar:

1. **Enums** (vocabulário do domínio) — `DayColor`, `RedReasonCategory`, `RedReason`.
   Puros, sem dependência. Tudo o mais referencia eles.
2. **Migrations** (as tabelas) — `huddle_patient_days` e `huddle_red_reasons`.
   A regra "todo dia começa vermelho" virou o `default 'red'` no schema.
3. **Models** (Eloquent) — casts de enum, relações, contadores red/green por internação.
4. **Service** (`HuddleBoardService`) — monta o board reaproveitando o `PatientDataLoader`
   do SBAR e fazendo merge com o estado do Huddle. Sem reinventar carregamento de paciente.
5. **Livewire + Views + Card** — a tela, espelhando o padrão de estado do `SbarReport`
   (dados pesados fora do snapshot).
6. **Acesso** — rota protegida, permissões (`ver huddle` / `conduzir huddle`) e link na navbar.
7. **Actions** (camada de escrita) — `SetDayColor`, `SetExpectedDischarge`, `RegisterRedReasons`,
   `OpenPatientDay`. Lógica isolada e testável, pronta para o modal de edição.

### 4. Reaproveitar em vez de reinventar
O carregamento de pacientes do Oracle, o cache, as preferências de setor e o padrão de
estado do Livewire já existiam no SBAR. O Huddle é um **novo painel sobre os mesmos
dados**, com uma camada de domínio de gestão de altas por cima. Isso reduz risco e
código novo.

### 5. Entregar em fatias verticais
Primeiro a **visualização** (painel + card read-only) — já funcionando no servidor.
Depois a **edição** (Actions + modal). Por último a **automação** (autofill do Tasy),
que depende de decisões da área. Cada fatia entrega valor sozinha.

### 6. O que depende de terceiros (e por isso ficou para depois)
- **Autofill de EDD/prescrição/transporte**: os campos de alta vêm do Tasy. A previsão de
  alta (`tasy.atend_previsao_alta.dt_previsto_alta`) já é consultada hoje; transporte e
  orientação ainda precisam do mapeamento da tabela (equipe do Tasy).
- **Regra do verde e catálogo de motivos**: pendentes de validação com a enfermagem.

---

# Parte 2 — Quadro de verificação geral

## 2.1 Estado dos componentes

| # | Componente | Onde | Feito | No servidor | Testado |
|---|-----------|------|:-----:|:-----------:|:-------:|
| 1 | Enums (`DayColor`, `RedReasonCategory`, `RedReason`) | `app/Enums/Huddle/` | ✅ | ✅ | ⬜ |
| 2 | Migration `huddle_patient_days` | `database/migrations/` | ✅ | ✅ | — |
| 3 | Migration `huddle_red_reasons` | `database/migrations/` | ✅ | ✅ | — |
| 4 | Migration `add_huddle_permissions` | `database/migrations/` | ✅ | ✅ | — |
| 5 | Models (`HuddlePatientDay`, `HuddleRedReason`) | `app/Models/Huddle/` | ✅ | ✅ | ⬜ |
| 6 | `HuddleBoardService` | `app/Services/Huddle/` | ✅ | ✅ | ⬜ |
| 7 | `HuddleBoard` (Livewire) | `app/Livewire/` | ✅ | ✅ | ⬜ |
| 8 | Views (page, index, skeleton) | `resources/views/huddle/report/` | ✅ | ✅ | — |
| 9 | Card (`huddle-card`) | `resources/views/components/huddle-card/` | ✅ | ✅ | — |
| 10 | Acesso (rota + navbar + permissões) | `routes/web.php`, `navbar.blade.php` | ✅ | ✅ | — |
| 11 | Actions de escrita (4) | `app/Actions/Huddle/` | ✅ | ⬜ | ⬜ |
| 12 | Edição no modal (`HuddlePatientModal`) | `app/Livewire/` | ⬜ | ⬜ | ⬜ |
| 13 | Autofill da previsão de alta (EDD) | `HuddleBoardService` | ⬜ | ⬜ | ⬜ |
| 14 | `DischargeLoader` (transporte, orientação) | `app/Services/PatientData/Loaders/` | ⬜ | ⬜ | ⬜ |
| 15 | Job diário de materialização (dia nasce RED) | `app/Console/Commands/` | ⬜ | ⬜ | ⬜ |
| 16 | Testes automatizados (Actions/enums) | `tests/` | ⬜ | — | ⬜ |
| 17 | Filtros / ordenação do painel | `HuddleBoard` + view | ⬜ | ⬜ | ⬜ |
| 18 | Modo monitor/TV | rota + view | ⬜ | ⬜ | ⬜ |

Legenda: ✅ feito · ⬜ pendente · — não se aplica

## 2.2 Como verificar cada coisa (checklist prático)

- **Migrations aplicadas:** `php artisan migrate:status` — as 3 `huddle_*` devem estar em "Ran".
- **Permissão criada:** o link "Huddle" aparece no menu para Administrador/Coordenador.
- **Tela abre:** acessar `/huddle` → painel com seletor de hospital/setor.
- **Cards renderizam:** selecionar o setor → aparecem os leitos (dados vêm do Tasy).
- **Regra Red2Green:** paciente sem registro do dia aparece **vermelho** por padrão.
- **Card completo:** leito, nome, idade, dias de internação, badge da cor, previsão de
  alta, contador red/green e motivos.
- **Acesso restrito:** usuário sem `ver huddle` recebe 403 em `/huddle` e não vê o link.

## 2.3 Decisões pendentes com a área (bloqueiam a automação)

| Tema | Pergunta-chave | Impacto |
|------|----------------|---------|
| Regra do verde | O que faz o dia virar verde e quem decide? | Fluxo de edição |
| Catálogo de motivos | A lista cobre a realidade? Quantos por dia? | Motivos do card |
| Campos do Tasy | Onde ficam transporte, orientação e prescrição de alta? | Autofill |
| Fim de semana | Como tratar o dia sem huddle? | Contadores / job diário |
| Monitor/TV | Que dados podem aparecer em tela de área comum? | Privacidade |

## 2.4 Próximos passos (ordem sugerida)

1. **Autofill do EDD** — usar a previsão de alta que já vem do Tasy (desbloqueado).
2. **Edição no modal** — conectar as Actions (marcar verde, previsão, motivos).
3. **Testes** das Actions e da regra dos 2 motivos.
4. **Job diário** de materialização (dia nasce vermelho).
5. **Autofill dos campos restantes** (após mapeamento do Tasy).
6. **Filtros, ordenação e modo TV** (após feedback da equipe).

## 2.5 Origem dos dados (referência rápida)

| Dado do card | Fonte |
|--------------|-------|
| Leito, nome, idade, dias de internação | Tasy (via `PatientDataLoader`) |
| Previsão de alta (EDD) | Tasy — `atend_previsao_alta.dt_previsto_alta` |
| Alta médica / efetivada | Tasy — `atendimento_paciente.dt_alta_medico` / `dt_alta` |
| Pendências (exames, procedimentos) | Tasy (via `PatientPendingEventsService`) |
| Cor do dia, motivos, critérios | MySQL — `huddle_patient_days` / `huddle_red_reasons` |
| Contador red/green da internação | MySQL — agregação por `nr_atendimento` |
