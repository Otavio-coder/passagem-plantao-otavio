<p align="center">
  <img src="public/images/santacasa-horizontal-branco.svg" width="400" style="margin-bottom: 15px;">
  <img src="public/images/passagem-plantao.svg" alt="Passagem Plantão" width="350" style="display: block; margin: 0 auto;">
</p>

<h1 align="center">Sistema de Passagem de Plantão</h1>
<p align="center"><strong>Santa Casa de Porto Alegre</strong></p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-11-FF2D20?style=flat&logo=laravel&logoColor=white" alt="Laravel">
  <img src="https://img.shields.io/badge/PHP-8.2-777BB4?style=flat&logo=php&logoColor=white" alt="PHP">
  <img src="https://img.shields.io/badge/Livewire-3-FB70A9?style=flat&logo=livewire&logoColor=white" alt="Livewire">
  <img src="https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Oracle-Tasy%2FScola-F80000?style=flat&logo=oracle&logoColor=white" alt="Oracle">
  <img src="https://img.shields.io/badge/TailwindCSS-3-06B6D4?style=flat&logo=tailwindcss&logoColor=white" alt="TailwindCSS">
</p>

## 📋 Sumário

- [Sobre o Projeto](#-sobre-o-projeto)
- [Tecnologias](#-tecnologias)
- [Arquitetura](#-arquitetura)
- [Funcionalidades](#-funcionalidades)
- [Instalação](#-orientações-para-instalação)
- [WebSocket (Reverb)](#-websocket-laravel-reverb)
- [Contatos](#-contatos)

## 🏥 Sobre o Projeto

O **Sistema de Passagem de Plantão** digitaliza e integra o processo de troca de turno nas unidades hospitalares da Santa Casa de Porto Alegre. Substituindo o tradicional processo em papel, o sistema centraliza e padroniza a comunicação clínica entre turnos, aumentando a segurança do cuidado e a rastreabilidade das informações.

### Modelo SBAR

O sistema implementa digitalmente o modelo **SBAR** (Situação, Background, Avaliação e Recomendação), estrutura internacional de comunicação clínica padronizada. As informações são organizadas em cards individuais por leito, sincronizados em tempo real com o prontuário eletrônico Tasy (Oracle).

**Três turnos reconhecidos:**

| Turno  | Horário          |
|--------|------------------|
| Manhã  | 07h00 às 12h59   |
| Tarde  | 13h00 às 18h59   |
| Noite  | 19h00 às 06h59   |

## 🔧 Tecnologias

### Core

| Tecnologia | Versão | Uso |
|------------|--------|-----|
| [Laravel](https://laravel.com/docs/11.x) | 11 | Framework principal |
| [PHP](https://www.php.net/) | 8.2+ | Linguagem backend |
| [Livewire](https://livewire.laravel.com/) | 3 | Componentes reativos server-side |
| [Alpine.js](https://alpinejs.dev/) | 3 | Interações client-side leves |
| [MySQL](https://dev.mysql.com/doc/) | 8.0+ | Banco de dados principal (chat, sessões, preferências) |
| [TailwindCSS](https://tailwindcss.com/) | 3 | Estilização |
| [Vite](https://vitejs.dev/) | 4+ | Bundler de assets |

### Integração e Infraestrutura

| Pacote | Versão | Uso |
|--------|--------|-----|
| [laravel/reverb](https://reverb.laravel.com/) | ^1 | Servidor WebSocket nativo (chat em tempo real) |
| [yajra/laravel-oci8](https://github.com/yajra/laravel-oci8) | ^11 | Conexão Oracle (Tasy EMR + Scola) |
| [directorytree/ldaprecord-laravel](https://ldaprecord.com/docs/laravel/v3) | ^3 | Autenticação via Active Directory |
| [spatie/laravel-permission](https://spatie.be/docs/laravel-permission/v6/) | ^6 | Controle de perfis e permissões |
| [laravel/horizon](https://laravel.com/docs/11.x/horizon) | ^5 | Monitoramento de filas Redis |
| [laravel/sanctum](https://laravel.com/docs/11.x/sanctum) | ^4 | Autenticação de API |
| [opcodesio/log-viewer](https://log-viewer.opcodes.io/) | ^3 | Visualização de logs da aplicação |

## 🏗 Arquitetura

### Conexões de Banco de Dados

O sistema mantém três conexões simultâneas:

| Conexão | Tipo | Conteúdo |
|---------|------|----------|
| `mysql` | MySQL 8 | Dados do sistema: usuários, chat, sessões de passagem, preferências de setor |
| `tasy` | Oracle | Prontuário eletrônico: pacientes, escalas, prescrições, procedimentos, dietas, gasoterapia, diálise |
| `scola` | Oracle | Status de exames laboratoriais (coletado, aguardando resultado, laudo liberado) |

### Fluxo Principal de Dados (Painel SBAR)

```
Navegador → Livewire (SbarReport)
    │
    ├── PatientDataLoader::forSector($sectorId)
    │       ├── DemographicsLoader   — dados do paciente, leito, médico       [cache 15 min]
    │       ├── ScalesLoader         — MEWS/PEWS, Braden, Morse, Dor, TEV    [cache 10 min]
    │       ├── PendingEventsLoader  — exames, procedimentos, hemoterapia     [cache 10 min]
    │       ├── ClinicalLoader       — isolamento, dispositivos, alergias     [cache 15 min]
    │       ├── MultidisciplinaryLoader — equipes e solicitações abertas      [cache 10 min]
    │       └── SurgeryLoader        — cirurgias agendadas                    [cache 10 min]
    │
    └── SbarPatientModal (detalhe do paciente)
            └── TasyService::getSbarPatientDetails($attendanceNumber)         [cache 10 min]
```

### Pendências Assistenciais (`PatientPendingEventsService`)

Consolida as pendências de todos os pacientes do setor via queries Oracle em lote (`CHUNK_SIZE = 200`). Handlers independentes:

- `PrescriptionPendingHandler` — exames e procedimentos prescritos sem execução
- `HemotherapyPendingHandler` — hemoterapia pendente
- `AntibioticPendingHandler` — antimicrobianos ativos
- `ChemotherapyPendingHandler` — quimioterapia pendente
- `SurgeryPendingHandler` — cirurgias agendadas
- `ExamsPendingHandler` — exames com status do laboratório (integração Scola)

### Chat em Tempo Real

Chat por paciente (por `nr_atendimento`) via **Laravel Reverb** (WebSocket).

| Aspecto | Detalhe |
|---------|---------|
| Tabelas | `chat_messages`, `chat_reactions`, `chat_message_pins` |
| Canal | `chat.{nr_atendimento}` (PrivateChannel) |
| Pin ativo | `unpinned_at IS NULL` — apenas um por paciente |
| Arquivo | `chat_messages_archive` — histórico comprimido (base64 gzcompress JSON) |

### Autenticação e Autorização

- **Autenticação:** LDAP via LdapRecord — mesmas credenciais de rede da Santa Casa
- **Perfis:** Spatie Laravel Permission com labels em português (ex: `'ver usuarios'`, `'ver historico chat'`)
- **Middleware:** `VerifyAuthorization` (alias `verify.authorization`) em todas as rotas autenticadas
- **Fotos e sincronização de perfil:** Microsoft Graph API (`app/Services/MSGraph/`)

## ✨ Funcionalidades

### Painel SBAR — Passagem de Plantão

- **Grade de leitos em tempo real** — cards individuais por leito com dados sincronizados do Tasy. Atualização automática em background.
- **Codificação visual por criticidade** — cor do card determinada pelo score MEWS/PEWS (cinza/azul/verde/amarelo/laranja/vermelho)
- **Filtros locais sem recarregamento** — por criticidade, isolamento, leitos ocupados/vagos, passagem realizada/pendente, alta médica, antimicrobiano, tempo de internação
- **Ordenação** — por leito, nome, score MEWS/PEWS, tempo de internação ou faixa etária
- **Indicadores de alerta nos cards** — alergias, isolamento, cirurgia agendada, alta médica, previsão de alta, óbito, hemocultura pendente
- **Resumo de pendências** — tipos de pendências do paciente visíveis diretamente no card
- **Equipes multidisciplinares** — ícones de especialidades com solicitação aberta (fisioterapia, psicologia, nutrição, fonoaudiologia, serviço social, acessos vasculares)
- **Avaliação fixada** — trecho da mensagem fixada no chat aparece no card do paciente
- **Pré-aquecimento de cache** — setores são carregados 15 minutos antes da troca de turno

### Janela Detalhada do Paciente

Abre ao clicar em **Detalhes** em qualquer card. Organizada em abas:

| Aba | Conteúdo |
|-----|----------|
| Visão Geral | Identificação completa, diagnósticos, comorbidades, alergias (com gravidade), dispositivos, avaliação de enfermagem |
| Escalas | MEWS/PEWS, Braden, Morse, Dor, TEV — com valor atual, variação, data/hora da avaliação e alerta de atraso |
| Pendências | Lista completa por categoria, com horários, status de execução e tempo em atraso calculado |
| Plano Terapêutico | Medicamentos, Proc. e Exames, Hemoterapia, Quimioterapia, Nutrição, Recomendações, Intervenções, Gasoterapia, Diálise |
| Hemoculturas | Histórico de coletas com status e resultado laboratorial |
| Multidisciplinar | Solicitações com status (aberta/respondida), motivo e profissional responsável |
| Alertas | Alertas ativos do prontuário (restrições, cuidados especiais) |

Navegação entre pacientes com setas anterior/próximo sem fechar a janela (respeita filtros ativos).

### Escalas Clínicas Monitoradas

| Escala | Aplicação | Periodicidade |
|--------|-----------|---------------|
| MEWS | Adultos (≥18 anos) | Por turno |
| PEWS | Pediatria (<18 anos) | Por turno |
| Braden | Risco de LPP | 24 horas |
| Morse | Risco de quedas | 24 horas |
| Dor | Intensidade de dor | Por turno |
| TEV | Tromboembolismo venoso | 24 horas |

Escalas com avaliação atrasada exibem alerta vermelho piscante. Tolerância de 30 minutos antes do turno para evitar falsos alertas.

### Chat e Anotações por Paciente

- Chat em tempo real por paciente via Laravel Reverb (WebSocket)
- Mensagens identificadas por usuário, foto e horário
- **Fixação de avaliação** — apenas uma mensagem fixada por vez; aparece resumida no card do painel
- **Lista de verificação** — checklist de tarefas por paciente no turno
- Edição de mensagem recém-enviada
- Histórico permanente com separação visual por turno

### Avaliações do Turno (Modal)

Janela consolidada acessada pelo botão **Avaliações** no cabeçalho do painel. Exibe todas as anotações do turno atual e anterior de todos os pacientes do setor, agrupadas por turno, com fotos, horários e indicação de avaliação fixada. Permite revisão rápida do setor antes de iniciar a passagem.

### Passagem de Plantão Estruturada

Fluxo guiado leito a leito para enfermeiros com leitos configurados:

- Abertura automática sequencial de cada paciente
- Registro de início, progresso e conclusão da sessão
- Retomada de passagem interrompida
- Bloqueio de dupla passagem no mesmo turno
- Métricas de tempo e percentual de conclusão armazenadas

### Configuração de Setores e Leitos

- **Minha Preferências** — cada usuário seleciona quais setores acompanha
- **Meus Leitos** — enfermeiros selecionam leitos de responsabilidade por setor
- **Apenas Meus Leitos** — modo de visualização focada; carrega somente os leitos configurados

### Relatório de Pendências (Admin)

Visão consolidada de todas as pendências assistenciais de múltiplos setores em uma única tabela. Filtros por setor, tipo de pendência e paciente. Exportação CSV. Atualização automática a cada 10 minutos ou sob demanda.

### Panorama (Admin)

Área exclusiva para coordenadores e administradores (`ver historico chat`):

- Histórico completo de anotações por setor, período e usuário
- Métricas de adesão à passagem de plantão por setor e turno
- Detalhamento individual por enfermeiro (volume, leitos cobertos, tempo médio)
- Exportação de dados históricos em CSV

### Gerenciamento de Usuários e Perfis (Admin)

- Busca, criação, edição e bloqueio de usuários
- Associação de perfis e permissões granulares (Spatie)
- Funcionalidade "Acessar como" para suporte e diagnóstico
- Sincronização de fotos e atributos via Microsoft Graph API

### Instalação como Aplicativo (PWA)

Suporte completo a Progressive Web App. Instalável no Android (Chrome) e iOS (Safari). Opera em modo tela cheia sem barra de navegador. Requer conexão com a rede interna para dados clínicos.

### Indicador de Conexão

Indicador no canto inferior direito mostra status da conexão em tempo real:
- **Verde** — abaixo de 500 ms (estável)
- **Amarelo** — 500 ms a 2 s (lento mas funcional)
- **Vermelho** — acima de 2 s ou sem resposta

## 🚀 Orientações para Instalação

### Pré-requisitos

- PHP 8.2+ com extensões `pdo_oci`, `ldap`, `redis`
- MySQL 8.0+
- Oracle Client 21c+ (para conexão Tasy/Scola)
- Redis (filas e cache)
- Node.js 18+ e NPM
- Composer 2+

### Passos para Instalação

1. **Clone o repositório**
   ```bash
   git clone [url-do-repositorio]
   cd passagem-plantao
   ```

2. **Instale as dependências**
   ```bash
   composer install
   npm install
   ```

3. **Configure o ambiente**
   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

4. **Configure as conexões no `.env`**
   ```env
   # MySQL principal
   DB_CONNECTION=mysql
   DB_HOST=...
   DB_DATABASE=passagem_plantao

   # Oracle — Tasy EMR
   DB_TASY_HOST=...
   DB_TASY_DATABASE=...

   # Oracle — Scola
   DB_SCOLA_HOST=...
   DB_SCOLA_DATABASE=...

   # Active Directory (LDAP)
   LDAP_HOST=...
   LDAP_USERNAME=...
   LDAP_PASSWORD=...
   LDAP_BASE_DN=...

   # Redis
   REDIS_HOST=127.0.0.1
   CACHE_STORE=redis
   QUEUE_CONNECTION=redis
   SESSION_DRIVER=redis

   # Microsoft Graph (fotos de usuário)
   MSGRAPH_CLIENT_ID=...
   MSGRAPH_CLIENT_SECRET=...
   MSGRAPH_TENANT_ID=...

   # Usuário administrador inicial (login de rede)
   USER_FIRST=nome.sobrenome
   ```

5. **Ajuste as permissões de diretórios**
   ```bash
   sudo chown -R www-data storage/ bootstrap/cache
   ```

6. **Prepare o banco de dados**
   ```bash
   php artisan migrate
   php artisan db:seed
   ```

7. **Compile os assets**
   ```bash
   npm run build
   ```

8. **Configure o servidor web**

   Adicione no VirtualHost Apache (veja seção WebSocket abaixo para configuração de proxy WSS).

9. **Acesse o sistema**

   Use as credenciais de rede da Santa Casa. O usuário definido em `USER_FIRST` receberá o perfil de administrador automaticamente na primeira execução do seeder.

### Desenvolvimento local

```bash
composer dev
# Inicia simultaneamente: artisan serve, queue worker, Pail (logs), Vite e Reverb
```

## 🔌 WebSocket (Laravel Reverb)

O chat em tempo real utiliza [Laravel Reverb](https://reverb.laravel.com/). O Reverb roda na própria máquina e o Apache faz proxy das conexões WebSocket via SSL.

### Fluxo

```
Navegador (wss://dominio:443/app/...)
    ↓  Apache SSL (proxy_wstunnel)
Reverb (ws://127.0.0.1:8080/app/...)
    ↓  Broadcasting event
Queue Worker → chat_messages → Livewire #[On] → UI atualizada
```

### Configuração em produção

#### 1. Variáveis de ambiente (`.env`)

```env
BROADCAST_CONNECTION=reverb

REVERB_APP_ID=<gerar-id-unico>
REVERB_APP_KEY=<gerar-key-unica>
REVERB_APP_SECRET=<gerar-secret-unico>
REVERB_HOST=0.0.0.0
REVERB_PORT=8080
REVERB_SCHEME=http

VITE_REVERB_APP_KEY=<mesmo-valor-de-REVERB_APP_KEY>
VITE_REVERB_HOST=<dominio-publico-do-servidor>
VITE_REVERB_PORT=443
VITE_REVERB_SCHEME=https
```

> Gere valores únicos:
> ```bash
> php artisan tinker --execute 'echo Str::random(20);'
> ```

#### 2. Apache — habilitar módulos

```bash
sudo a2enmod proxy proxy_http proxy_wstunnel headers rewrite
sudo systemctl restart apache2
```

#### 3. Apache — VirtualHost (bloco `<VirtualHost *:443>`)

```apache
# WebSocket proxy para Laravel Reverb
RewriteEngine On
RewriteCond %{HTTP:Upgrade} websocket [NC]
RewriteCond %{HTTP:Connection} upgrade [NC]
RewriteRule ^/app/(.*) ws://127.0.0.1:8080/app/$1 [P,L]

ProxyPass /app/ ws://127.0.0.1:8080/app/
ProxyPassReverse /app/ ws://127.0.0.1:8080/app/
ProxyPass /apps/ http://127.0.0.1:8080/apps/
ProxyPassReverse /apps/ http://127.0.0.1:8080/apps/

RequestHeader set X-Forwarded-Proto "https"
RequestHeader set X-Forwarded-Port "443"
```

#### 4. Compilar assets

```bash
npm run build
php artisan config:clear
```

#### 5. Supervisor — manter Reverb e Queue Worker em produção

`/etc/supervisor/conf.d/reverb.conf`:

```ini
[program:reverb]
command=php /var/www/passagem-plantao/artisan reverb:start --host=0.0.0.0 --port=8080
directory=/var/www/passagem-plantao
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/log/supervisor/reverb.log
```

`/etc/supervisor/conf.d/worker.conf`:

```ini
[program:worker]
command=php /var/www/passagem-plantao/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
directory=/var/www/passagem-plantao
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/supervisor/worker.log
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start reverb worker:*
```

#### 6. Verificar

```bash
# Reverb respondendo
curl http://localhost:8080/apps/<REVERB_APP_ID>

# WebSocket acessível via Apache
curl -i https://<dominio>/apps/<REVERB_APP_ID>

# Status do supervisor
sudo supervisorctl status
```

### Comandos úteis de manutenção

```bash
# Limpar todos os caches
php artisan cache:clear

# Pré-aquecer cache de um setor manualmente
php artisan cache:warm-sector {sectorId}

# Executar migrações
php artisan migrate

# Verificar filas via Horizon
php artisan horizon
```

---

## 📞 Contatos

Para maiores informações, contate o time de Inovação:

📧 [inovacao@santacasa.org.br](mailto:inovacao@santacasa.org.br)

### Desenvolvedores

**🔗 [Caio Foti Pontes](https://github.com/caiofoti)**  
✉️ [caio.pontes@santacasa.org.br](mailto:caio.pontes@santacasa.org.br)  
🌐 [github/iscmpa-rs](https://github.com/iscmpa-rs)

---

<p align="center">
  <small>© 2025 Santa Casa de Porto Alegre. Todos os direitos reservados.</small>
</p>
