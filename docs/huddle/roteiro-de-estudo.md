# Huddle — Gestão de Altas
## Roteiro de estudo e revisão

> Um caminho didático para entender o módulo de ponta a ponta — do conceito clínico
> ao código e à integração com o Tasy. Feito para você estudar na ordem e conseguir
> manter, explicar e evoluir o módulo. Estude um bloco por vez; cada um tem
> "o que ler", "onde no código" e "checkpoint" (pergunta para você se testar).

---

## Bloco 1 — O porquê (fundamento clínico)

**Objetivo:** entender o problema e a metodologia antes do código.

- **Estude:** `docs/huddle/arquitetura-huddle-gestao-de-altas.md` (seções 1 e 2) e o artigo BMJ Open Quality 2024.
- **Conceitos-chave:**
  - **SAFER bundle** — as 5 ações diárias (revisão sênior, previsão de alta + critérios, fluxo, alta cedo, revisão de longa permanência).
  - **Red2Green** — todo dia começa **vermelho**; vira **verde** só quando o plano do dia avança a alta. Contar dias red/green = medir dias que "geraram valor".
- **Checkpoint:** por que um dia começa vermelho por padrão? O que faz ele virar verde?

## Bloco 2 — Como o projeto é organizado (Laravel + Livewire)

**Objetivo:** reconhecer as "peças" do framework usando o Huddle como exemplo real.

| Peça | O que é | Exemplo no Huddle |
|------|---------|-------------------|
| **Enum** | Lista fixa de valores com comportamento | `app/Enums/Huddle/DayColor.php` |
| **Migration** | Cria/altera tabelas do banco | `database/migrations/*huddle*` |
| **Model (Eloquent)** | Representa uma tabela em objeto PHP | `app/Models/Huddle/HuddlePatientDay.php` |
| **Service** | Regra de negócio/orquestração | `app/Services/Huddle/HuddleBoardService.php` |
| **Action** | Uma operação de escrita isolada | `app/Actions/Huddle/AnswerChecklistItemAction.php` |
| **Livewire** | Componente de tela reativo (sem JS manual) | `app/Livewire/HuddleBoard.php` |
| **Blade** | O HTML da tela | `resources/views/huddle/...` |
| **Policy/Permissão** | Quem pode o quê | `ver huddle` / `conduzir huddle` |

- **Checkpoint:** qual a diferença entre um **Service** e uma **Action**? Por que não colocar essa lógica direto no Livewire?

## Bloco 3 — O módulo Huddle por dentro (ordem de leitura do código)

**Objetivo:** ler o código na ordem em que ele foi construído (de baixo para cima).

1. **Enums** (`app/Enums/Huddle/`) — o vocabulário: `DayColor`, `RedReasonCategory`, `RedReason`, `HuddleChecklistItem`.
2. **Migrations** (`database/migrations/*huddle*`) — as tabelas: `huddle_patient_days`, `huddle_red_reasons`, `huddle_checklist_answers`.
3. **Models** (`app/Models/Huddle/`) — casts de enum, relações, `deriveColorFromChecklist()` (a regra "Red se qualquer item Red").
4. **Service** (`HuddleBoardService`) — monta o board reusando o `PatientDataLoader` do SBAR + o estado do Huddle.
5. **Livewire** (`HuddleBoard`, `HuddlePatientModal`) — a tela e o modal com o checklist.
6. **Views** (`resources/views/huddle/`, `resources/views/components/huddle-card/`) — o card e o modal.
7. **Actions** (`app/Actions/Huddle/`) — gravar resposta, gate 72h, previsão de alta.

- **Checkpoint:** abra o `HuddleBoardService` e siga: de onde vem cada dado do card? (dica: parte do Tasy, parte do MySQL).

## Bloco 4 — De onde vêm os dados (Tasy × MySQL)

**Objetivo:** entender a separação entre dado clínico (Oracle/Tasy, só leitura) e dado do sistema (MySQL).

- **Estude:** `docs/huddle/arquitetura-huddle-gestao-de-altas.md` (seção "origem dos dados") e o `HuddleBoardService`.
- **Regra mental:**
  - Vem do **Tasy**: paciente, leito, escalas, pendências (exames/procedimentos), **previsão de alta**.
  - Vem do **MySQL (Huddle)**: cor do dia, respostas do checklist, motivos, previsão de alta editada (DPA).
- **Checkpoint:** o "Prev. Alta" do card vem do Tasy ou do Huddle? (resposta: do Tasy — `atend_previsao_alta.dt_previsto_alta`).

## Bloco 5 — Garimpar dados no Oracle/Tasy (SQL Developer)

**Objetivo:** saber achar onde uma informação mora no Tasy — habilidade que você já praticou com o transporte.

- **A receita (4 passos):**
  1. Achar a tabela: `SELECT table_name FROM all_tables WHERE owner='TASY' AND table_name LIKE '%PALAVRA%'`
  2. Filtrar as "de paciente": as que têm `NR_ATENDIMENTO` (`all_tab_columns`).
  3. Ver as colunas da tabela escolhida.
  4. Espiar dados reais (`FETCH FIRST 15 ROWS ONLY`).
- **Convenção de nomes do Tasy** (ajuda a ler qualquer tabela):
  - `NR_` = número · `CD_` = código · `DT_` = data · `IE_` = indicador/status · `DS_` = descrição · `QT_` = quantidade · `NM_` = nome.
- **Exemplo que fizemos:** transporte → tabela `FORMULARIO_TRANSPORTE`, campos-chave `NR_ATENDIMENTO`, `IE_SITUACAO`, `DT_LIBERACAO`.
- **Checkpoint:** como você descobriria a tabela de "orientação de alta"? (dica: `LIKE '%ORIENTA%'`).

## Bloco 6 — Como o dado do Tasy vira código

**Objetivo:** ver o caminho de uma query validada até a tela.

- **Padrão:** `DB::connection('tasy')->select("SELECT ... WHERE cd_setor_atendimento = :sector", ['sector' => $id])`.
- **Onde ver pronto:** `app/Services/PendingEvents/PatientPendingEventsService.php` (a query de previsão de alta está lá).
- **Sempre com cache** por setor (3–15 min) para não sobrecarregar o Oracle.
- **Checkpoint:** por que a query recebe `:sector` como parâmetro em vez de concatenar o número na string? (resposta: segurança — evita SQL injection).

## Bloco 7 — Fluxo de trabalho (git e deploy)

**Objetivo:** saber levar uma mudança do código até o servidor.

1. Desenvolver na branch de trabalho → commit → push.
2. Abrir **Pull Request** → mesclar na `main`.
3. No servidor (MobaXterm): `git fetch otavio && git merge otavio/main`.
4. Se houver tabela nova: `php artisan migrate`. Sempre: `php artisan optimize:clear`.
- **Estude:** `docs/huddle/linha-do-tempo.md` (mostra a sequência real de PRs).
- **Checkpoint:** o que diferencia um `push` de um `Pull Request`? (resposta: push envia a branch; PR pede para mesclar na main).

## Bloco 8 — O que ainda falta (para você continuar)

**Objetivo:** saber onde o módulo para hoje e o que vem a seguir.

- **Estude:** `docs/huddle/linha-de-raciocinio-e-verificacao.md` (o quadro de verificação).
- **Pendências técnicas:**
  - Autopreenchimento do Tasy: **transporte** (quase mapeado), **orientação de alta**, **prescrição de alta**.
  - Testes automatizados; job diário que materializa o dia como vermelho.
- **Decisões com a área** (roteiro de 10 perguntas em `docs/huddle/`): regra do verde, catálogo de motivos, fim de semana, modo TV.
- **Checkpoint:** qual pendência está te bloqueando hoje e de quem depende (Tasy/TI ou enfermagem)?

---

## Ordem sugerida de estudo (resumo)

1. Bloco 1 (conceito) → 2. Bloco 2 (peças do Laravel) → 3. Bloco 3 (ler o código na ordem)
→ 4. Bloco 4 (Tasy × MySQL) → 5. Bloco 5 (garimpar Tasy) → 6. Bloco 6 (query no código)
→ 7. Bloco 7 (git/deploy) → 8. Bloco 8 (o que falta).

## Documentos de apoio (todos em `docs/huddle/`)

| Documento | Para quê |
|-----------|----------|
| `arquitetura-huddle-gestao-de-altas.md` | O desenho técnico completo |
| `plano-de-desenvolvimento.md` | Os incrementos de construção |
| `linha-de-raciocinio-e-verificacao.md` | O porquê das decisões + quadro de status |
| `linha-do-tempo.md` | A história do desenvolvimento (para contar aos colegas) |
| `roteiro-de-estudo.md` | Este guia |
