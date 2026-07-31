# Módulo Huddle — Plano de Desenvolvimento

> **Documento irmão:** [`arquitetura-huddle-gestao-de-altas.md`](./arquitetura-huddle-gestao-de-altas.md)
> **Status:** Plano de execução (Fase 1 pronta para iniciar)
> **Branch de trabalho:** `claude/php-laravel-senior-mentor-vkzjlj`

Este documento traduz a arquitetura em **incrementos executáveis**. Cada incremento é
pequeno o suficiente para virar um PR revisável e tem uma **Definition of Done (DoD)** clara.

---

## 1. Princípios de execução

1. **Desbloqueado primeiro.** Toda a camada de domínio (MySQL) é independente do Tasy.
   Construímos e testamos ela agora; o autopreenchimento via Oracle entra depois, sem
   retrabalho, porque o contrato já estará pronto.
2. **Reuso agressivo do SBAR.** Nada de reinventar carregamento de paciente, cache ou
   tempo real — reutilizamos `PatientDataLoader`, `PatientPendingEventsService` e o
   padrão de estado do `SbarReport`.
3. **Incrementos verticais e testáveis.** Cada PR entrega valor verificável e sobe com
   testes (Pest/PHPUnit) + `pint`. Sem PR "só de scaffolding morto".
4. **Domínio isolado.** Lógica de negócio em Enums, Actions e Services — Livewire e
   Controllers ficam finos.

---

## 2. Bloqueado vs. desbloqueado

| Tema | Estado | Motivo |
|------|--------|--------|
| Modelo de dados MySQL (`huddle_*`) | ✅ Desbloqueado | Não depende de Oracle |
| Enums, Actions, máquina de estado RED/GREEN | ✅ Desbloqueado | Lógica pura |
| Contadores por internação | ✅ Desbloqueado | Agregação MySQL |
| Policy + permissões (enfermagem) | ✅ Desbloqueado | Spatie + `is_nurse` já existem |
| Board Livewire (leitura de demografia + pendências) | ✅ Desbloqueado | Reusa loaders existentes |
| Modal: setar cor / EDD / motivos (manual) | ✅ Desbloqueado | Escrita em MySQL |
| Job diário de materialização | ✅ Desbloqueado | Usa demografia já disponível |
| **Autofill de EDD / prescrição / transporte** | ⛔ Bloqueado | Requer mapeamento de campos do Tasy (Decisão §11.1) |
| **Sugestão automática de red reasons** | 🟡 Parcial | Parte via `PatientPendingEventsService` (já existe); parte via Tasy (bloqueada) |
| **Modo monitor/TV** | 🟡 Parcial | Depende de decisão de segurança (§11.4) |
| **Write-back no PEP (Tasy)** | ⛔ Fase 3 | Fora do MVP |

**Conclusão:** a Fase 1 inteira (8 incrementos abaixo) pode começar imediatamente.

---

## 3. Fase 1 — MVP piloto (5º andar HSC)

Sequência recomendada. Cada incremento é um PR.

### Incremento 1 — Fundação de domínio (dados + enums)
**Objetivo:** persistência e vocabulário do domínio.

- `database/migrations/*_create_huddle_patient_days_table.php`
- `database/migrations/*_create_huddle_red_reasons_table.php`
- `App\Enums\Huddle\DayColor` · `RedReasonCategory` · `RedReason` (com `category()` e `label()`)
- `App\Models\Huddle\HuddlePatientDay` · `HuddleRedReason` (casts de enum, relações, `$fillable`)
- `database/factories/HuddlePatientDayFactory.php` + `HuddleRedReasonFactory.php`

**DoD:** `php artisan migrate` sobe e desce limpo; factories geram registros válidos;
`pint` passa. Sem UI.

---

### Incremento 2 — Máquina de estado e Actions (o coração metodológico)
**Objetivo:** as regras Red2Green como código testável.

- `App\Actions\Huddle\OpenPatientDayAction` — cria o dia como **RED** (idempotente por `nr_atendimento + date`)
- `App\Actions\Huddle\SetDayColorAction` — transição RED↔GREEN
- `App\Actions\Huddle\RegisterRedReasonsAction` — aplica o **cap de 2 motivos/dia**
- `App\Actions\Huddle\SetExpectedDischargeAction` — grava EDD/CCD

**Testes (Pest):**
- dia nasce RED; vira GREEN só sob condição; volta a RED zera/mantém motivos conforme regra
- rejeita o 3º motivo no mesmo dia
- contador RED/GREEN por internação (`nr_atendimento`) fecha certo

**DoD:** cobertura dos caminhos acima verde; nenhuma dependência de Oracle nos testes.

---

### Incremento 3 — Autorização
**Objetivo:** só a enfermagem conduz; multidisciplinar visualiza.

- `App\Policies\HuddlePolicy` (`view`, `conduct`)
- Seeder de permissões Spatie: `ver huddle`, `conduzir huddle`
- Gate ligado a `is_nurse` + permissão

**Testes:** enfermeiro edita; perfil multidisciplinar recebe 403 na escrita e 200 na leitura.

**DoD:** matriz de acesso coberta por teste.

---

### Incremento 4 — Serviço de montagem do board (read)
**Objetivo:** juntar dados clínicos (reuso) + estado do Huddle (MySQL).

- `App\Services\Huddle\HuddleBoardService::forSector($sectorId)`
  - demografia + pendências via `PatientDataLoader` (reuso)
  - faz *merge* com `huddle_patient_days` do dia
  - devolve estrutura pronta para o card (cor, EDD, contadores, resumo de pendências)

**DoD:** teste de integração com loaders mockados; retorna cor RED default para paciente sem registro do dia.

---

### Incremento 5 — Painel Livewire (`HuddleBoard`)
**Objetivo:** a tela do setor.

- `App\Livewire\HuddleBoard` espelhando `SbarReport` (estado `#[Computed(persist:true)]` — não inflar snapshot)
- View do grid + card por leito: cor RED/GREEN, contador "X red / Y green", EDD, alertas
- Ativar rota `/huddle` (hoje `coming-soon`)

**DoD:** painel renderiza o setor-piloto; troca de setor funciona; `pint` + testes Livewire básicos.

---

### Incremento 6 — Modal de detalhe (escrita manual)
**Objetivo:** onde o enfermeiro conduz o huddle.

- Enriquecer `App\Livewire\HuddlePatientModal` (shell já existe): setar cor, EDD/CCD,
  motivos de red day, checklist SAFER — chamando as Actions do Incremento 2
- Navegação anterior/próximo já pronta

**DoD:** fluxo completo manual funcionando; autorização respeitada; teste de que salvar cor persiste e reflete no board.

---

### Incremento 7 — Job diário de materialização
**Objetivo:** garantir que todo dia "começa RED" (sustenta os contadores nos dias sem huddle).

- `App\Console\Commands\OpenHuddleDay` — cria o dia RED para leitos ocupados dos setores-piloto
- Agendamento em `routes/console.php` (padrão já usado no projeto), no início do dia/turno

**DoD:** comando idempotente (rodar 2x não duplica); teste do comando; documentado no README de operação.

---

### Incremento 8 — Métricas básicas
**Objetivo:** primeiro sinal de ganho.

- Proporção RED/GREEN por setor/período e distribuição de causas de red day
- Integrar ao **Panorama** (admin) existente

**DoD:** números batem com dados de teste; exportação CSV se o padrão do Panorama já oferecer.

---

## 4. Fase 2 — Automação (inicia após Decisão §11.1)

Depende do mapeamento de campos do Tasy:

- `App\Services\PatientData\Loaders\DischargeLoader` — autofill de EDD, prescrição de
  alta, transporte, orientação (batch + cache, padrão dos outros loaders)
- Sugestão automática de red reasons (`auto_derived = true`) a partir de pendências + Tasy
- Modo monitor/TV (após Decisão §11.4)
- Alertas das regras operacionais: EDD ≤24h, meta de alta 10h, dependências de transporte/orientação
- Aderência de preenchimento no Panorama

## 5. Fase 3 — Integração e rollout

- Write-back para evolução/árvore de cuidado no PEP (Tasy)
- Expansão para outras unidades, respeitando particularidades por hospital

---

## 6. Quality gates (todos os PRs)

- `./vendor/bin/pint` sem alterações pendentes
- Testes verdes (`php artisan test`)
- Sem lógica de negócio em Livewire/Controller (fica em Action/Service/Enum)
- Migrations reversíveis (`down()` testado)

---

## 7. Riscos e mitigação

| Risco | Mitigação |
|-------|-----------|
| Mapeamento Tasy atrasa | Fase 1 inteira independe dele; entregamos MVP manual e plugamos autofill depois |
| Contadores furados em fins de semana | Job diário de materialização (Incremento 7) — dia sempre nasce RED |
| Retrabalho para a assistência | Autofill (Fase 2) + sugestão automática de motivos reduz digitação ao mínimo |
| Dados de paciente em TV de área comum | Decisão de segurança §11.4 antes de liberar modo monitor |
| Divergência de processo entre hospitais | Piloto único (5º HSC) valida o modelo antes do rollout |

---

## 8. Primeiro passo recomendado

Iniciar pelo **Incremento 1 (Fundação de domínio)** — é 100% desbloqueado, não toca em
Oracle, e destrava os Incrementos 2 e 4. Entregável concreto: migrations `huddle_*`,
Enums, Models e Factories, com `migrate` limpo e `pint` verde.
