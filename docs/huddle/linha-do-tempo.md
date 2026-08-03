# Huddle — Gestão de Altas
## Linha do tempo do desenvolvimento

> Histórico das etapas do módulo, do entendimento do problema até o estado atual.
> Serve para acompanhar o projeto e explicá-lo à equipe.

---

## Fase 0 — Entendimento do problema

**O que foi feito:** análise dos dois insumos passados pelas áreas:
- **Documento de requisitos** (Projetos de Inovação / Santa Casa) — descreve o Huddle de
  Gestão de Altas, hoje feito de forma heterogênea e sem método, e a intenção de
  unificá-lo num módulo dentro da Passagem de Plantão.
- **Artigo científico** (BMJ Open Quality 2024) — a base metodológica: **SAFER bundle +
  Red2Green days**, com evidência de redução do tempo de permanência (LOS) de 19 → 14 dias.

**Conclusão que guiou tudo:** o Huddle é um painel focado no que **libera ou trava a alta**,
conduzido pela enfermagem, com **cores** para leitura rápida e **autopreenchimento** a
partir do Tasy sempre que possível.

## Fase 1 — Análise do sistema existente

**O que foi feito:** mapeamento do projeto Passagem de Plantão (Laravel 11, Livewire,
MySQL + Oracle Tasy/Scola, LDAP). Descoberta importante: já existia um **esqueleto do
Huddle** (rota em "coming soon", `HuddlePatientModal`, uma view) e, principalmente, toda a
infraestrutura de carregamento de pacientes do Oracle (`PatientDataLoader`,
`PatientPendingEventsService`) — que seria **reaproveitada** em vez de reescrita.

## Fase 2 — Arquitetura e plano

**O que foi feito:** dois documentos de referência:
- `arquitetura-huddle-gestao-de-altas.md` — modelo de dados, camadas, integração Tasy,
  autorização e decisões pendentes com o negócio.
- `plano-de-desenvolvimento.md` — a construção quebrada em incrementos (PRs) com
  "Definition of Done", separando o que é desbloqueado do que depende do Tasy.

## Fase 3 — Definição do repositório de trabalho

**O que foi feito:** o desenvolvimento passou a ser feito no repositório
**`Otavio-coder/passagem-plantao-otavio`** (mesmo projeto, repositório do Otavio). O
trabalho inicial foi portado para lá, na branch `claude/php-laravel-senior-mentor-vkzjlj`.

## Fase 4 — Construção do domínio (31/07)

Ordem de baixo para cima. Commits:
- `aab20b8` — **Enums** de domínio (`DayColor`, `RedReasonCategory`, `RedReason`) +
  documentação.
- `04e16ea` — **Migration** `huddle_patient_days` (estado diário: cor, previsão de alta,
  critérios). Regra "todo dia começa vermelho" virou o `default 'red'`.
- `9d741f8` — **Migration** `huddle_red_reasons` (motivos do dia vermelho).
- `03a281a` — **Models** `HuddlePatientDay` e `HuddleRedReason` (casts, relações,
  contadores red/green por internação).

## Fase 5 — Aplicação e tela (31/07)

- `a83d301` — **`HuddleBoardService`**: monta o painel reaproveitando o `PatientDataLoader`
  e fazendo merge com o estado do Huddle.
- `58f1d07` — **`HuddleBoard`** (componente Livewire), espelhando o padrão de estado do SBAR.
- `9371c0e` — **Tela e card**: views do painel + o modelo de card focado em alta (badge
  Red/Green, previsão de alta, contadores, motivos) + leito vago.
- `faab2de` — **Acesso**: rota `/huddle` protegida por `can:ver huddle`, permissões
  (`ver huddle` / `conduzir huddle`) e link na navbar (desktop e mobile).

## Fase 6 — Validação visual

**O que foi feito:** como o sistema depende de Oracle/LDAP para rodar com dados reais, foi
gerado um **mockup visual navegável** do painel, para validar o modelo de card com a
equipe sem depender de ambiente.

## Fase 7 — Integração à main e deploy (PRs #1 e #2)

**O que foi feito:** o código foi mesclado na `main` do repositório (Pull Requests #1 e #2).
No servidor (via MobaXterm), o repositório oficial (`iscmpa-rs/passagem-plantao`) recebeu o
código do repositório do Otavio como segundo remote, seguido de:
- `git merge otavio/main` — sem conflitos.
- `php artisan migrate` — as 3 migrations `huddle_*` rodaram com sucesso.

**Resultado:** o painel do Huddle (visualização) ficou **no ar no servidor**.

## Fase 8 — Camada de escrita (03/08)

- `1295581` — **Actions** de escrita (`OpenPatientDayAction`, `SetDayColorAction`,
  `SetExpectedDischargeAction`, `RegisterRedReasonsAction`): a lógica que grava as decisões
  do huddle (cor, previsão de alta, motivos), isolada e testável. Ainda **não conectada à
  tela** (é a próxima fatia).

## Fase 9 — Integração com o Oracle/Tasy (mapeamento)

**O que foi feito:** levantamento de **como acessar os dados de alta** no Tasy. Descoberta
central: a **previsão de alta já é consultada hoje** pelo sistema —
`tasy.atend_previsao_alta.dt_previsto_alta` — e já chega ao painel. Faltam mapear apenas
transporte, orientação e prescrição de alta (a confirmar com a equipe do Tasy).

## Fase 10 — Ajustes de processo e acesso (03/08)

- `f6b0b4d` — **Documentação de acompanhamento**: linha de raciocínio + quadro de
  verificação geral.
- `ccf200c` — **Correção no seeder**: as permissões do Huddle foram incluídas na lista do
  `DatabaseSeeder` (senão o seed as apagaria) e atribuídas aos perfis Administrador,
  Coordenador e Enfermeiro. A partir daqui, os commits passaram a ser assinados como
  **Otavio Nunes**.

---

## Onde estamos agora

| Bloco | Situação |
|-------|----------|
| Visualização (painel + card + acesso) | ✅ pronto e **no servidor** |
| Camada de escrita (Actions) | ✅ no repositório (falta conectar à tela) |
| Autopreenchimento da previsão de alta | ⬜ próximo (desbloqueado) |
| Edição no modal (marcar verde, previsão, motivos) | ⬜ próximo |
| Testes, job diário, filtros, modo TV | ⬜ pendentes |
| Decisões da área (regra do verde, motivos, campos Tasy) | 🟡 em validação |

## Próximos passos

1. Autopreenchimento da previsão de alta (usa o dado que já vem do Tasy).
2. Edição no modal, conectando as Actions.
3. Testes automatizados das regras (cor, limite de motivos).
4. Job diário que materializa o dia como vermelho.
5. Mapeamento das tabelas de transporte/orientação com a equipe do Tasy.
6. Validação com a enfermagem (roteiro de 10 perguntas já preparado).

---

## Como contar isso em uma frase

> "Transformamos o Huddle de Gestão de Altas — hoje feito no papel e sem padrão — em um
> painel digital dentro da Passagem de Plantão, baseado na metodologia SAFER + Red2Green
> comprovada em literatura, reaproveitando a integração que já temos com o Tasy. A
> visualização já está no ar; a próxima etapa é a edição pela enfermagem e o
> autopreenchimento a partir do prontuário."
