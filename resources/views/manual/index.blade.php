@extends('layouts.app')

@push('scripts')
<style>
    /* ── Screen layout ─────────────────────────────────────────── */
    main > div { padding-top: 0 !important; padding-bottom: 0 !important; }
    main > div > div { padding: 0 !important; max-width: 100% !important; }
    main > div > div > div { display: block !important; }

    :root {
        --brand: #004D9D;
        --brand-dark: #003d7a;
        --brand-light: #e8f1fb;
        --ink: #1a1a2e;
        --muted: #6b7280;
        --rule: #e5e7eb;
    }

    #manual-wrap {
        display: flex;
        height: calc(100svh - 64px);
        overflow: hidden;
        font-family: 'Montserrat', sans-serif;
    }

    /* Sidebar */
    #sidebar {
        width: 268px;
        flex-shrink: 0;
        background: #f4f6f9;
        box-shadow: 2px 0 16px rgba(0, 77, 157, 0.07);
        overflow-y: auto;
        padding: 1.25rem 0 2rem;
        position: relative;
        z-index: 2;
    }
    #sidebar .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0 1.25rem 1.25rem;
        border-bottom: 1px solid #e2e6eb;
        margin-bottom: 0.75rem;
    }
    #sidebar .sidebar-brand-dot {
        width: 8px;
        height: 8px;
        border-radius: 50%;
        background: var(--brand);
        flex-shrink: 0;
    }
    #sidebar .sidebar-brand-label {
        font-size: 0.72rem;
        font-weight: 700;
        color: var(--brand);
        letter-spacing: 0.01em;
    }
    #sidebar .sidebar-title {
        font-size: 0.66rem;
        font-weight: 600;
        color: #a0aec0;
        padding: 0.75rem 1.25rem 0.35rem;
        text-transform: none;
    }
    #sidebar a {
        display: flex;
        align-items: center;
        gap: 0.55rem;
        padding: 0.65rem 1.25rem;
        font-size: 0.82rem;
        color: #4a5568;
        text-decoration: none;
        line-height: 1.3;
        border-left: 3px solid transparent;
        transition: background 0.12s, color 0.12s, border-color 0.12s;
        border-radius: 0 6px 6px 0;
        min-height: 44px;
    }
    #sidebar a:hover { background: rgba(0, 77, 157, 0.06); color: var(--brand); }
    #sidebar a.active {
        border-left-color: var(--brand);
        background: rgba(0, 77, 157, 0.08);
        color: var(--brand);
        font-weight: 600;
    }
    #sidebar a .num {
        font-variant-numeric: tabular-nums;
        min-width: 1.5rem;
        color: #a0aec0;
        font-weight: 700;
        font-size: 0.68rem;
        padding-top: 0.1rem;
    }
    #sidebar a.active .num { color: var(--brand); opacity: 0.7; }
    #sidebar a.sub { padding-left: 2.5rem; font-size: 0.78rem; color: #718096; min-height: 40px; }
    #sidebar a.sub:hover { color: var(--brand); background: rgba(0,77,157,0.04); }
    #sidebar a.sub.active { color: var(--brand); font-weight: 600; background: rgba(0,77,157,0.07); }

    /* Content area */
    #content-area {
        flex: 1;
        overflow-y: auto;
        scroll-behavior: smooth;
        background: #eef1f6;
    }
    #manual-content {
        max-width: 800px;
        margin: 1.75rem auto;
        padding: 2.5rem 3rem 4rem;
        background: #fff;
        border-radius: 10px;
        box-shadow: 0 1px 3px rgba(0,0,0,0.07), 0 4px 16px rgba(0,77,157,0.05);
        color: var(--ink);
        line-height: 1.75;
        font-size: 0.9rem;
    }

    /* Top bar */
    #top-bar {
        position: sticky;
        top: 0;
        z-index: 30;
        background: #fff;
        border-bottom: 1px solid var(--rule);
        box-shadow: 0 1px 4px rgba(0,0,0,0.04);
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.5rem 1rem;
        min-height: 52px;
    }
    #top-bar button, #top-bar a {
        min-height: 40px;
        display: inline-flex;
        align-items: center;
    }

    /* Typography */
    .manual-h1 {
        font-size: 1.7rem;
        font-weight: 800;
        color: var(--brand);
        margin: 0 0 0.25rem;
        line-height: 1.25;
    }
    .manual-subtitle {
        font-size: 0.85rem;
        color: var(--muted);
        margin-bottom: 2rem;
    }
    .section { margin-bottom: 3rem; }
    .section-anchor { scroll-margin-top: 3.5rem; }
    .s-title {
        font-size: 1.15rem;
        font-weight: 700;
        color: var(--brand);
        border-bottom: 2px solid var(--brand-light);
        padding-bottom: 0.4rem;
        margin: 0 0 1rem;
        display: flex;
        align-items: baseline;
        gap: 0.6rem;
    }
    .s-title .s-num { color: var(--muted); font-weight: 400; font-size: 0.9em; }
    .ss-title {
        font-size: 0.95rem;
        font-weight: 700;
        color: var(--ink);
        margin: 1.5rem 0 0.5rem;
    }
    p { margin: 0 0 0.85rem; }
    ul { margin: 0 0 0.85rem 1.4rem; list-style: disc; }
    ol { margin: 0 0 0.85rem 1.4rem; list-style: decimal; }
    li { margin-bottom: 0.4rem; padding-left: 0.2rem; }
    strong { color: var(--ink); }

    /* Callout boxes */
    .callout {
        border-radius: 0.5rem;
        padding: 0.9rem 1.1rem;
        margin: 1rem 0;
        font-size: 0.85rem;
        line-height: 1.6;
    }
    .callout-info  { background: var(--brand-light); border-left: 4px solid var(--brand); }
    .callout-warn  { background: #fffbeb; border-left: 4px solid #f59e0b; }
    .callout-tip   { background: #f0fdf4; border-left: 4px solid #22c55e; }
    .callout-title { font-weight: 700; font-size: 0.82rem; margin-bottom: 0.3rem; }
    .callout-info  .callout-title { color: var(--brand); }
    .callout-warn  .callout-title { color: #92400e; }
    .callout-tip   .callout-title { color: #166534; }

    /* Scale tables */
    .scale-table { width: 100%; border-collapse: collapse; font-size: 0.82rem; margin: 0.75rem 0 1rem; }
    .scale-table th { background: #f3f4f6; font-weight: 700; text-align: left; padding: 0.5rem 0.75rem; border: 1px solid var(--rule); }
    .scale-table td { padding: 0.45rem 0.75rem; border: 1px solid var(--rule); vertical-align: top; }
    .scale-table tr:nth-child(even) td { background: #fafafa; }
    .chip { display: inline-block; padding: 0.15rem 0.55rem; border-radius: 99px; font-size: 0.75rem; font-weight: 600; }
    .chip-red    { background: #fee2e2; color: #991b1b; }
    .chip-orange { background: #ffedd5; color: #9a3412; }
    .chip-yellow { background: #fef9c3; color: #78350f; }
    .chip-gray   { background: #f3f4f6; color: #374151; }
    .chip-green  { background: #dcfce7; color: #166534; }
    .chip-blue   { background: var(--brand-light); color: var(--brand); }

    /* Color swatches */
    .color-row { display: flex; align-items: flex-start; gap: 0.75rem; margin-bottom: 0.6rem; }
    .swatch { width: 1.2rem; height: 1.2rem; border-radius: 4px; flex-shrink: 0; margin-top: 0.15rem; }
    .swatch-red    { background: #fca5a5; border: 2px solid #ef4444; }
    .swatch-orange { background: #fdba74; border: 2px solid #f97316; }
    .swatch-yellow { background: #fde68a; border: 2px solid #eab308; }
    .swatch-blue   { background: var(--brand-light); border: 2px solid var(--brand); }
    .swatch-green  { background: #bbf7d0; border: 2px solid #22c55e; }
    .swatch-gray   { background: #e5e7eb; border: 2px solid #9ca3af; }

    /* Steps */
    .steps { counter-reset: step; margin: 0.5rem 0 1rem; }
    .step  { counter-increment: step; display: flex; gap: 0.85rem; margin-bottom: 0.75rem; align-items: flex-start; }
    .step::before { content: counter(step); display: flex; align-items: center; justify-content: center; min-width: 1.7rem; height: 1.7rem; border-radius: 50%; background: var(--brand); color: #fff; font-size: 0.75rem; font-weight: 700; flex-shrink: 0; }
    .step-body { padding-top: 0.1rem; }

    /* Divider */
    .divider { border: none; border-top: 1px solid var(--rule); margin: 2.5rem 0; }

    /* Glossário colapsável (Alpine.js) */
    .glossary-item {
        border: 1px solid var(--rule);
        border-radius: 0.5rem;
        margin-bottom: 0.5rem;
        overflow: hidden;
    }
    .glossary-item .gi-summary {
        cursor: pointer;
        padding: 0.85rem 1rem;
        font-weight: 600;
        font-size: 0.88rem;
        color: var(--ink);
        display: flex;
        align-items: center;
        gap: 0.6rem;
        user-select: none;
        min-height: 48px;
        background: #f9fafb;
        transition: background 0.12s;
        -webkit-tap-highlight-color: rgba(0,77,157,0.08);
    }
    .glossary-item .gi-summary::before {
        content: '›';
        font-size: 1.2rem;
        line-height: 1;
        color: var(--brand);
        transition: transform 0.18s ease;
        display: inline-block;
        flex-shrink: 0;
        font-weight: 400;
        width: 1rem;
        text-align: center;
    }
    .glossary-item.gi-open .gi-summary::before { transform: rotate(90deg); }
    .glossary-item.gi-open .gi-summary { background: var(--brand-light); color: var(--brand); }
    .glossary-item .gi-summary:hover { background: var(--brand-light); }
    .glossary-item .gi-content { margin: 0; padding: 0.75rem 1rem 0.9rem 2rem; font-size: 0.85rem; color: #374151; border-top: 1px solid var(--rule); }

    /* Sidebar toggle mobile */
    #sidebar-toggle { display: none; }

    @media (max-width: 767px) {
        #sidebar { position: fixed; left: -290px; top: 64px; bottom: 0; z-index: 200; transition: left 0.22s cubic-bezier(.4,0,.2,1); width: 290px; border-radius: 0; }
        #sidebar.open { left: 0; box-shadow: 4px 0 24px rgba(0,0,0,0.22); }
        #sidebar-toggle { display: inline-flex; }
        #content-area { background: #f8fafc; }
        #manual-content { margin: 0; border-radius: 0; box-shadow: none; padding: 1.5rem 1.1rem 3rem; }
    }

    /* ── Print / PDF export ────────────────────────────────────── */
    @media print {
        @page { size: A4; margin: 18mm 16mm 18mm; }

        body, html { background: #fff !important; }

        header, #top-bar, #sidebar, #sidebar-overlay,
        .no-print, footer { display: none !important; }

        #manual-wrap { display: block; height: auto; overflow: visible; }
        #content-area { overflow: visible; }
        #manual-content { max-width: 100%; padding: 0; font-size: 9.5pt; }

        .manual-h1 { font-size: 18pt; }
        .s-title   { font-size: 12pt; page-break-after: avoid; }
        .ss-title  { font-size: 10pt; page-break-after: avoid; }
        .section   { page-break-inside: avoid; margin-bottom: 1.5rem; }

        .callout { page-break-inside: avoid; }
        .scale-table { page-break-inside: avoid; }

        h1, h2, h3, .s-title, .ss-title { page-break-after: avoid; }
        p, li { orphans: 3; widows: 3; }

        a { color: inherit !important; text-decoration: none !important; }
    }
</style>
@endpush

@section('content')

<div id="manual-wrap">

    {{-- ── Sidebar ─────────────────────────────────────────────── --}}
    <nav id="sidebar" aria-label="Navegação do manual">

        <div class="sidebar-brand">
            <div class="sidebar-brand-dot"></div>
            <span class="sidebar-brand-label">Manual do Sistema</span>
        </div>

        <a href="#sec-apresentacao"><span class="num">1</span> Apresentação</a>
        <a href="#sec-acesso"><span class="num">2</span> Acesso ao Sistema</a>
        <a href="#sec-rede"><span class="num">3</span> Rede Interna</a>
        <a href="#sec-recursos"><span class="num">4</span> Recursos Disponíveis</a>
        <a href="#sec-relatorio-pendencias" class="sub">Relatório de Pendências</a>
        <a href="#sec-feedback" class="sub">Formulário de Feedback</a>
        <a href="#sec-avaliacoes" class="sub">Avaliações do Turno</a>
        <a href="#sec-config"><span class="num">5</span> Configuração Inicial</a>
        <a href="#sec-painel"><span class="num">6</span> Painel de Passagem</a>
        <a href="#sec-filtros" class="sub">Filtros e Ordenação</a>
        <a href="#sec-cards" class="sub">Cartão do Paciente</a>
        <a href="#sec-cores" class="sub">Cores e Alertas</a>
        <a href="#sec-escalas" class="sub">Escalas Clínicas</a>
        <a href="#sec-pendencias" class="sub">Pendências</a>
        <a href="#sec-multi" class="sub">Equipes Multidisciplinares</a>
        <a href="#sec-modal" class="sub">Janela do Paciente</a>
        <a href="#sec-chat" class="sub">Chat e Anotações</a>
        <a href="#sec-passagem" class="sub">Iniciando a Passagem</a>
        <a href="#sec-huddle"><span class="num">7</span> Huddle — Gestão de Altas</a>
        <a href="#sec-huddle-card" class="sub">Cartão do Huddle</a>
        <a href="#sec-huddle-detalhes" class="sub">Detalhes do Paciente</a>
        <a href="#sec-huddle-round" class="sub">Round Unidade</a>
        <a href="#sec-app"><span class="num">8</span> Instalar como App</a>
        <a href="#sec-dicas"><span class="num">9</span> Dicas Importantes</a>
        <a href="#sec-glossario"><span class="num">10</span> Glossário</a>
    </nav>

    {{-- ── Main area ───────────────────────────────────────────── --}}
    <div id="content-area">

        {{-- Top bar --}}
        <div id="top-bar">
            <button id="sidebar-toggle" class="no-print inline-flex items-center justify-center w-8 h-8 rounded-lg bg-gray-100 hover:bg-gray-200 transition-colors" aria-label="Abrir navegação">
                <i class="fas fa-bars text-sm text-gray-600"></i>
            </button>
            <span class="text-sm font-semibold text-gray-700">Manual do Sistema — Passagem de Plantão</span>
            <div class="flex-1"></div>
            <a href="{{ route('home') }}" class="no-print inline-flex items-center gap-1.5 text-xs text-gray-500 hover:text-gray-700 px-2.5 py-1.5 rounded-lg hover:bg-gray-100 transition-colors">
                <i class="fas fa-arrow-left text-xs"></i>
                <span class="hidden sm:inline">Voltar</span>
            </a>
            <button onclick="window.print()" class="no-print inline-flex items-center gap-1.5 text-xs font-semibold bg-[#004D9D] hover:bg-[#003d7a] text-white px-3 py-1.5 rounded-lg transition-colors">
                <i class="fas fa-file-pdf text-xs"></i>
                Exportar PDF
            </button>
        </div>

        <div id="manual-content">

            <h1 class="manual-h1">Manual do Usuário</h1>
            <p class="manual-subtitle">
                Sistema de Passagem de Plantão — Santa Casa de Porto Alegre &nbsp;·&nbsp; Versão {{ date('Y') }}
            </p>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 1. APRESENTAÇÃO
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-apresentacao">
                <h2 class="s-title section-anchor"><span class="s-num">1.</span> Apresentação do Sistema</h2>

                <p>O <strong>Sistema de Passagem de Plantão</strong> é uma plataforma digital desenvolvida pela equipe de Inovação da Santa Casa de Porto Alegre com o objetivo de modernizar e padronizar o processo de comunicação entre turnos de trabalho nas unidades de internação.</p>

                <p>O sistema substitui as tradicionais planilhas em papel e as passagens verbais informais, centralizando em um único ambiente todas as informações clínicas relevantes do paciente no momento da troca de turno. Ao acessar o sistema, o enfermeiro ou coordenador visualiza uma grade completa de leitos do setor com os dados mais recentes de cada paciente, organizados segundo o modelo <strong>SBAR</strong> — Situação, Background (antecedentes), Avaliação e Recomendação.</p>

                <div class="callout callout-info">
                    <div class="callout-title">O que é o modelo SBAR?</div>
                    O SBAR é uma metodologia internacional de comunicação clínica estruturada, amplamente adotada em ambientes hospitalares. Ele organiza as informações do paciente em quatro dimensões: a situação atual, o histórico relevante, a avaliação do estado clínico e as recomendações ou pendências. O sistema aplica esse modelo digitalmente para garantir que nenhuma informação crítica seja perdida na troca de turno.
                </div>

                <p><strong>Principais benefícios para a equipe de enfermagem:</strong></p>
                <ul>
                    <li>Visualização de todos os leitos do setor em uma única tela</li>
                    <li>Acesso às escalas de risco atualizadas automaticamente pelo prontuário eletrônico</li>
                    <li>Identificação rápida de exames, procedimentos e tratamentos pendentes</li>
                    <li>Canal de comunicação escrita entre turnos para cada paciente</li>
                    <li>Registro formal de quando a passagem de plantão foi realizada</li>
                    <li>Disponível em computador, tablet e celular</li>
                </ul>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 2. ACESSO AO SISTEMA
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-acesso">
                <h2 class="s-title section-anchor"><span class="s-num">2.</span> Acesso ao Sistema</h2>

                <p>O acesso é feito pelo navegador de internet (Chrome, Edge, Safari ou Firefox) digitando o endereço da aplicação na barra de endereços. O sistema utiliza as mesmas credenciais de rede da Santa Casa, ou seja, o mesmo usuário e senha que você usa para acessar o computador ou o e-mail institucional.</p>

                <div class="steps">
                    <div class="step"><div class="step-body">Abra o navegador de internet no computador, tablet ou celular.</div></div>
                    <div class="step"><div class="step-body">Digite o endereço do sistema na barra de endereços e pressione Enter.</div></div>
                    <div class="step"><div class="step-body">Na tela de login, informe seu <strong>usuário de rede</strong> (ex: <code>nome.sobrenome</code>) e sua <strong>senha</strong>.</div></div>
                    <div class="step"><div class="step-body">Clique em <strong>Entrar</strong>. O sistema irá direcioná-lo automaticamente ao painel principal.</div></div>
                </div>

                <div class="callout callout-warn">
                    <div class="callout-title">Atenção</div>
                    O sistema não possui cadastro próprio de usuários. Toda autenticação é realizada pela rede institucional da Santa Casa. Caso não consiga acessar, verifique com a TI se sua conta de rede está ativa e com permissão para o sistema.
                </div>

                <h3 class="ss-title">Encerramento de sessão</h3>
                <p>Para sair do sistema, clique no seu avatar ou nome de usuário no canto superior direito da tela e selecione <strong>Sair do sistema</strong>. Recomendamos sempre encerrar a sessão ao terminar o uso, especialmente em dispositivos compartilhados.</p>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 3. REDE INTERNA
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-rede">
                <h2 class="s-title section-anchor"><span class="s-num">3.</span> Restrição à Rede Interna</h2>

                <p>Por questões de segurança e proteção de dados dos pacientes, o sistema de Passagem de Plantão está disponível <strong>exclusivamente dentro da rede interna da Santa Casa de Porto Alegre</strong>. Isso inclui:</p>

                <ul>
                    <li>Computadores e estações de trabalho conectados à rede corporativa por cabo</li>
                    <li>Dispositivos conectados à rede Wi-Fi institucional (redes identificadas como rede interna da Santa Casa)</li>
                    <li>Tablets e celulares institucionais</li>
                </ul>

                <div class="callout callout-warn">
                    <div class="callout-title">Fora da rede interna</div>
                    Não é possível acessar o sistema de casa, por dados móveis (4G/5G) ou por redes Wi-Fi externas. Caso você tente acessar de fora da rede institucional, o endereço simplesmente não será encontrado pelo navegador. Isso é intencional e garante a segurança das informações clínicas.
                </div>

                <p>Se você precisar utilizar o sistema em um tablet ou celular pessoal dentro da unidade, solicite ao setor de TI a conexão ao Wi-Fi institucional correto.</p>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 4. RECURSOS DISPONÍVEIS
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-recursos">
                <h2 class="s-title section-anchor"><span class="s-num">4.</span> Recursos Disponíveis</h2>

                <p>Ao acessar o sistema, você encontra no menu superior os principais recursos da plataforma. Os itens visíveis dependem do perfil do usuário. Para enfermeiros e coordenadores, estão disponíveis:</p>

                <h3 class="ss-title">Passagem de Plantão (SBAR)</h3>
                <p>Tela principal do sistema. Exibe todos os leitos do setor selecionado com os dados clínicos atualizados de cada paciente. É aqui que a passagem de plantão é realizada e registrada. Acesso pelo menu <strong>Plantão</strong>.</p>

                <h3 class="ss-title">Meus Setores</h3>
                <p>Página de configuração onde o enfermeiro define quais setores e leitos acompanha. Permite também ativar o modo de carregamento por leitos selecionados para uma visualização mais focada. Acesso pelo ícone de hospital no menu superior ou por <strong>Meus Setores</strong>.</p>

                <h3 class="ss-title">Manual do Sistema</h3>
                <p>Esta documentação. Disponível no menu principal, pode ser exportada como PDF para consulta offline.</p>

                <div class="callout callout-info">
                    <div class="callout-title">Itens exclusivos para coordenadores e administradores</div>
                    Além dos itens acima, coordenadores e usuários com perfil administrativo têm acesso a: gerenciamento de usuários, gerenciamento de perfis, histórico de anotações, relatório de pendências e relatório de utilização. Esses itens aparecem sob o menu <strong>Admin</strong> na barra de navegação.
                </div>
            </section>

            {{-- Relatório de Pendências --}}
            <section class="section" id="sec-relatorio-pendencias">
                <h3 class="ss-title section-anchor" style="font-size:1rem; color:var(--brand); border-bottom: 1px solid var(--brand-light); padding-bottom:0.3rem; margin-bottom:0.85rem;">4.1 Relatório de Pendências</h3>

                <p>O Relatório de Pendências é uma tela administrativa que consolida em tempo real todas as pendências assistenciais ativas dos pacientes internados nos setores selecionados. Está disponível no menu <strong>Admin → Relatório de Pendências</strong>.</p>

                <p><strong>O que é exibido:</strong></p>
                <ul>
                    <li>Exames, procedimentos, hemoterapia, antimicrobianos e outros itens prescritos ainda não executados</li>
                    <li>Cada pendência exibe o paciente, leito, tipo, descrição, data de prescrição, horário previsto de execução e tempo em aberto</li>
                    <li>Integração com o laboratório: quando disponível, informa se o exame já foi coletado, aguarda resultado ou tem laudo liberado</li>
                    <li>Indicação de antimicrobianos ativos com destaque visual</li>
                </ul>

                <p><strong>Filtros disponíveis:</strong> por setor (um ou múltiplos), por tipo de pendência, por paciente. É possível exportar o resultado filtrado como planilha CSV.</p>

                <div class="callout callout-info">
                    <div class="callout-title">Dados compartilhados com o SBAR</div>
                    As pendências exibidas neste relatório são as mesmas que aparecem nos cartões do painel SBAR. A diferença é que o relatório oferece uma visão consolidada de todos os setores em uma única tabela, enquanto o SBAR organiza as mesmas informações por leito individualmente.
                </div>

                <p>Os dados são atualizados automaticamente a cada 10 minutos. Para forçar uma atualização imediata, utilize o botão <strong>Atualizar</strong> disponível na página.</p>
            </section>

            {{-- Formulário de Feedback --}}
            <section class="section" id="sec-feedback">
                <h3 class="ss-title section-anchor" style="font-size:1rem; color:var(--brand); border-bottom: 1px solid var(--brand-light); padding-bottom:0.3rem; margin-bottom:0.85rem;">4.2 Formulário de Feedback</h3>

                <p>O <strong>Feedback</strong> é um formulário integrado para enviar sugestões, relatar problemas ou compartilhar observações sobre o sistema diretamente com a equipe de Inovação da Santa Casa. Disponível no menu <strong>Feedback</strong> na barra de navegação.</p>

                <p>O formulário é incorporado diretamente na tela do sistema, sem necessidade de abrir outra janela ou sair da plataforma. Ao submeter, sua mensagem é encaminhada automaticamente para a equipe responsável.</p>

                <div class="callout callout-tip">
                    <div class="callout-title">Por que o feedback importa</div>
                    O sistema é desenvolvido de forma contínua com base no uso real em campo. Relatos de lentidão, dados incorretos, funcionalidades confusas ou sugestões de melhoria recebidos pelo formulário são avaliados e priorizados pela equipe de desenvolvimento. Use o formulário sempre que encontrar algo que possa ser melhorado.
                </div>
            </section>

            {{-- Avaliações do Turno --}}
            <section class="section" id="sec-avaliacoes">
                <h3 class="ss-title section-anchor" style="font-size:1rem; color:var(--brand); border-bottom: 1px solid var(--brand-light); padding-bottom:0.3rem; margin-bottom:0.85rem;">4.3 Avaliações do Turno</h3>

                <p>O painel SBAR exibe um botão <strong>Avaliações</strong> no cabeçalho, que abre uma janela consolidada com todas as anotações registradas pela equipe no turno atual e no turno anterior para o setor selecionado. Isso permite uma revisão rápida do setor inteiro sem precisar abrir cada paciente individualmente.</p>

                <p><strong>O que a janela exibe:</strong></p>
                <ul>
                    <li>Anotações agrupadas por turno (atual e anterior), com separação visual clara entre eles</li>
                    <li>Nome do usuário, foto de perfil e horário exato de cada mensagem</li>
                    <li>Identificação do paciente e número do leito correspondente a cada anotação</li>
                    <li>Avaliações fixadas em destaque, diferenciadas das mensagens comuns</li>
                </ul>

                <div class="callout callout-tip">
                    <div class="callout-title">Uso recomendado</div>
                    Utilize este recurso ao assumir o turno para ter uma visão panorâmica de todas as anotações relevantes do setor antes de iniciar a passagem leito a leito. É especialmente útil em setores com muitos pacientes.
                </div>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 5. CONFIGURAÇÃO INICIAL
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-config">
                <h2 class="s-title section-anchor"><span class="s-num">5.</span> Configuração Inicial</h2>

                <p>Antes de utilizar o sistema pela primeira vez, é necessário configurar quais setores você acompanha e, se você for enfermeiro, quais leitos são de sua responsabilidade na passagem de plantão.</p>

                <h3 class="ss-title">5.1 Configurando os Setores</h3>

                <div class="steps">
                    <div class="step"><div class="step-body">Acesse <strong>Meus Setores</strong> pelo menu superior ou clique no ícone de hospital.</div></div>
                    <div class="step"><div class="step-body">Na aba <strong>Setores</strong>, você verá todos os hospitais e setores disponíveis para sua conta.</div></div>
                    <div class="step"><div class="step-body">Marque as caixas de seleção dos setores que você quer visualizar no painel de passagem de plantão.</div></div>
                    <div class="step"><div class="step-body">Clique em <strong>Salvar preferências</strong>. Os setores selecionados ficarão disponíveis nos filtros do painel principal.</div></div>
                </div>

                <div class="callout callout-tip">
                    <div class="callout-title">Dica</div>
                    Selecione apenas os setores que você realmente utiliza. Isso torna a navegação mais rápida e o carregamento dos dados mais eficiente.
                </div>

                <h3 class="ss-title">5.2 Configurando os Leitos (para Enfermeiros)</h3>

                <p>Enfermeiros com acesso à funcionalidade de passagem de plantão estruturada devem também configurar quais leitos são de sua responsabilidade. Essa informação é usada pelo sistema para iniciar a passagem de plantão mostrando somente os pacientes sob seus cuidados.</p>

                <div class="steps">
                    <div class="step"><div class="step-body">Em <strong>Meus Setores</strong>, acesse a aba <strong>Meus Leitos</strong>.</div></div>
                    <div class="step"><div class="step-body">Para cada setor que você acompanha, clique no nome do setor para expandir a lista de leitos.</div></div>
                    <div class="step"><div class="step-body">Clique nos leitos que são de sua responsabilidade. Os leitos selecionados ficam destacados em azul.</div></div>
                    <div class="step"><div class="step-body">Use <strong>Todos</strong> para selecionar todos os leitos do setor de uma vez, ou <strong>Limpar</strong> para desmarcar todos.</div></div>
                    <div class="step"><div class="step-body">Clique em <strong>Salvar</strong> no topo da tela.</div></div>
                </div>

                <h3 class="ss-title">5.3 Carregar Apenas Meus Leitos</h3>

                <p>Ainda na aba <strong>Meus Leitos</strong>, há uma opção chamada <strong>Carregar apenas meus leitos</strong>. Quando ativada, o painel de passagem de plantão exibirá somente os leitos que você configurou, ignorando os demais leitos do setor.</p>

                <p>Isso é especialmente útil em setores com muitos leitos, pois reduz o volume de dados carregados e torna a visualização mais objetiva. A opção pode ser ativada ou desativada a qualquer momento pelo mesmo local.</p>

                <div class="callout callout-warn">
                    <div class="callout-title">Atenção</div>
                    Quando esta opção está ativa, um indicador verde <strong>Apenas meus leitos</strong> aparece no topo do painel de passagem de plantão. Para ver todos os leitos do setor novamente, desative a opção em Meus Leitos.
                </div>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 6. PAINEL DE PASSAGEM DE PLANTÃO
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-painel">
                <h2 class="s-title section-anchor"><span class="s-num">6.</span> Painel de Passagem de Plantão</h2>

                <p>O painel de passagem de plantão é a tela principal do sistema. Ele exibe em tempo quase real os dados clínicos de todos os pacientes internados nos leitos do setor selecionado. Os dados são sincronizados automaticamente com o prontuário eletrônico Tasy e atualizados periodicamente em segundo plano.</p>

                <p><strong>Periodicidade de atualização:</strong></p>
                <ul>
                    <li><strong>Dados do paciente</strong> (nome, leito, médico, tempo de internação): a cada 15 minutos</li>
                    <li><strong>Escalas de risco</strong> (MEWS, Braden, Morse, etc.): a cada 10 minutos</li>
                    <li><strong>Pendências</strong> (exames, procedimentos, medicamentos): a cada 10 minutos</li>
                    <li><strong>Dados clínicos</strong> (alergias, dispositivos, isolamento): a cada 15 minutos</li>
                    <li><strong>Anotações e passagem de plantão</strong>: a cada 5 minutos</li>
                </ul>

                <p>Para forçar uma atualização imediata dos dados, utilize o botão <strong>Atualizar</strong> no cabeçalho do painel.</p>

                <div class="callout callout-info">
                    <div class="callout-title">Turnos do sistema</div>
                    O sistema reconhece três turnos ao longo do dia. As anotações e o controle de passagem de plantão são organizados dentro de cada turno:
                    <ul style="margin-top:0.4rem">
                        <li><strong>Manhã:</strong> das 07h00 às 12h59</li>
                        <li><strong>Tarde:</strong> das 13h00 às 18h59</li>
                        <li><strong>Noite:</strong> das 19h00 às 06h59</li>
                    </ul>
                </div>
            </section>

            {{-- 6.1 Filtros --}}
            <section class="section" id="sec-filtros">
                <h3 class="ss-title section-anchor">6.1 Filtros e Ordenação</h3>

                <p>O cabeçalho do painel contém diversos filtros que permitem refinar a visualização dos pacientes sem recarregar os dados do servidor. Todos os filtros são processados localmente, ou seja, a resposta é imediata.</p>

                <table class="scale-table">
                    <thead>
                        <tr><th>Filtro</th><th>O que faz</th></tr>
                    </thead>
                    <tbody>
                        <tr><td><strong>Hospital</strong></td><td>Seleciona o hospital. Disponível para quem tem acesso a mais de uma unidade.</td></tr>
                        <tr><td><strong>Setor</strong></td><td>Seleciona o setor dentro do hospital escolhido.</td></tr>
                        <tr><td><strong>Criticidade</strong></td><td>Filtra pacientes pelo score MEWS/PEWS: Todos, Críticos (≥5), Alerta (3-4) ou Normais (0-2).</td></tr>
                        <tr><td><strong>Isolamento</strong></td><td>Exibe somente pacientes com medida de bloqueio/isolamento ativa.</td></tr>
                        <tr><td><strong>Leitos</strong></td><td>Alterna entre mostrar todos os leitos, apenas leitos ocupados ou apenas leitos vagos.</td></tr>
                        <tr><td><strong>Passagem</strong></td><td>Filtra entre pacientes que já têm anotação registrada neste turno e os que ainda não têm.</td></tr>
                        <tr><td><strong>Alta</strong></td><td>Filtra pacientes com alta efetivada, alta médica ou previsão de alta.</td></tr>
                        <tr><td><strong>Antimicrobiano</strong></td><td>Exibe somente pacientes com antimicrobiano pendente.</td></tr>
                        <tr><td><strong>Internação</strong></td><td>Filtra por tempo de internação: mais de 3, 7 ou 14 dias.</td></tr>
                    </tbody>
                </table>

                <p>É possível também <strong>ordenar</strong> os cartões por leito (padrão), por nome do paciente, pelo score MEWS/PEWS, por tempo de internação ou por faixa etária. A ordenação pode ser crescente ou decrescente.</p>

                <div class="callout callout-tip">
                    <div class="callout-title">Filtros combinados</div>
                    Os filtros podem ser usados simultaneamente. Por exemplo: mostrar apenas os pacientes críticos (MEWS ≥5) com antimicrobiano pendente e sem anotação de passagem ainda realizada neste turno.
                </div>
            </section>

            {{-- 6.2 Cartão do Paciente --}}
            <section class="section" id="sec-cards">
                <h3 class="ss-title section-anchor">6.2 Cartão do Paciente</h3>

                <p>Cada leito é representado por um cartão individual que condensa as informações mais relevantes do paciente. Os cartões são organizados em grade e adaptam o número de colunas ao tamanho da tela.</p>

                <p><strong>Informações exibidas no cartão:</strong></p>
                <ul>
                    <li><strong>Número do leito</strong> — identificação do leito físico no setor</li>
                    <li><strong>Score MEWS ou PEWS</strong> — escala de risco (MEWS para adultos; PEWS para pacientes com menos de 18 anos)</li>
                    <li><strong>Nome do paciente</strong> — nome social quando disponível, nome de registro caso contrário</li>
                    <li><strong>Número do prontuário</strong></li>
                    <li><strong>Idade</strong> — em anos para adultos; em anos, meses e dias para pediatria</li>
                    <li><strong>Sexo</strong></li>
                    <li><strong>Convênio</strong> (abreviado)</li>
                    <li><strong>Médico responsável</strong></li>
                    <li><strong>Tempo de internação</strong> — em dias a partir da data de entrada</li>
                    <li><strong>Indicadores de alerta</strong> — ícones no cabeçalho do cartão sinalizando alergias, isolamento, cirurgia agendada, alta médica/prevista, óbito registrado, entre outros</li>
                    <li><strong>Resumo de pendências</strong> — tipos de pendências identificadas para o paciente (exames, procedimentos, antimicrobianos etc.)</li>
                    <li><strong>Equipes multidisciplinares</strong> — ícones indicando quais especialidades estão com avaliação solicitada</li>
                    <li><strong>Anotação do turno</strong> — trecho da última anotação registrada no chat ou da avaliação fixada, quando disponível</li>
                    <li><strong>Indicador de passagem realizada</strong> — marca quando o paciente já foi visitado durante a passagem de plantão deste turno</li>
                </ul>

                <p>Leitos <strong>vagos</strong> aparecem com fundo cinza e sem informações de paciente. Um leito vago também é informação relevante para o planejamento da unidade.</p>

                <p>Clique no botão <strong>Detalhes</strong> na parte inferior de qualquer cartão para abrir a janela completa do paciente com todas as informações disponíveis.</p>
            </section>

            {{-- 6.3 Cores e Alertas --}}
            <section class="section" id="sec-cores">
                <h3 class="ss-title section-anchor">6.3 Cores e Alertas Visuais</h3>

                <p>A cor de fundo e a borda de cada cartão refletem o nível de criticidade clínica do paciente com base no score MEWS ou PEWS mais recente. Essa codificação visual permite identificar rapidamente os pacientes que exigem maior atenção.</p>

                <div class="color-row"><div class="swatch swatch-red"></div><div><strong>Vermelho — Crítico</strong><br>MEWS igual ou superior a 5 pontos. Paciente em situação de alto risco de deterioração clínica. Requer atenção imediata.</div></div>
                <div class="color-row"><div class="swatch swatch-orange"></div><div><strong>Laranja — Alto</strong><br>MEWS igual a 4 pontos. Paciente com risco elevado, requerendo monitoramento frequente.</div></div>
                <div class="color-row"><div class="swatch swatch-yellow"></div><div><strong>Amarelo — Alerta</strong><br>MEWS igual a 3 pontos. Paciente com sinais de instabilidade, necessitando de avaliação.</div></div>
                <div class="color-row"><div class="swatch swatch-blue"></div><div><strong>Azul — Normal</strong><br>MEWS entre 0 e 2 pontos. Paciente clinicamente estável.</div></div>
                <div class="color-row"><div class="swatch swatch-green"></div><div><strong>Verde — Admissão recente</strong><br>Paciente com menos de 1 dia de internação. A cor verde indica admissão nova, independentemente do score.</div></div>
                <div class="color-row"><div class="swatch swatch-gray"></div><div><strong>Cinza — Leito vago</strong><br>Nenhum paciente internado no leito no momento.</div></div>

                <p style="margin-top:1rem">Além da cor do cartão, o sistema exibe <strong>ícones de alerta</strong> no cabeçalho de cada cartão para sinalizar condições específicas:</p>
                <ul>
                    <li><strong>Alergia</strong> — paciente possui alergia registrada no prontuário</li>
                    <li><strong>Isolamento</strong> — paciente em medida de bloqueio ou isolamento</li>
                    <li><strong>Cirurgia</strong> — cirurgia ou procedimento cirúrgico agendado</li>
                    <li><strong>Alta médica</strong> — alta médica concedida pelo médico responsável</li>
                    <li><strong>Previsão de alta</strong> — previsão de alta registrada no prontuário</li>
                    <li><strong>Óbito</strong> — óbito registrado no prontuário</li>
                    <li><strong>Hemocultura pendente</strong> — coleta de hemocultura registrada sem resultado definitivo</li>
                </ul>

                <div class="callout callout-info">
                    <div class="callout-title">Escala atrasada</div>
                    Quando uma escala de risco não foi avaliada no período esperado, o sistema exibe um indicador vermelho piscando ao lado do score (por exemplo, ao lado do MEWS). Isso indica que a avaliação da escala está em atraso e deve ser realizada o quanto antes.
                </div>
            </section>

            {{-- 6.4 Escalas --}}
            <section class="section" id="sec-escalas">
                <h3 class="ss-title section-anchor">6.4 Escalas Clínicas</h3>

                <p>O sistema exibe automaticamente as escalas de risco aferidas pela equipe de enfermagem no prontuário eletrônico. Nenhuma escala é calculada pelo sistema — ele apenas lê e apresenta os valores registrados pela equipe.</p>

                <p>Cada escala possui uma <strong>periodicidade esperada de avaliação</strong>. Quando o prazo é ultrapassado, o score exibido no cartão ou na janela do paciente aparece com destaque em vermelho, indicando que o registro está desatualizado.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">MEWS — Modified Early Warning Score (Adultos)</h4>
                <p>Avaliação de risco de deterioração clínica para pacientes com 18 anos ou mais. Calculado a partir de sinais vitais.</p>
                <table class="scale-table">
                    <thead><tr><th>Pontuação</th><th>Classificação</th></tr></thead>
                    <tbody>
                        <tr><td>0 – 2 pontos</td><td><span class="chip chip-gray">Normal</span> — paciente estável</td></tr>
                        <tr><td>3 pontos</td><td><span class="chip chip-yellow">Alerta</span> — sinais de instabilidade; monitorar</td></tr>
                        <tr><td>4 pontos</td><td><span class="chip chip-orange">Alto</span> — risco elevado; acionar médico</td></tr>
                        <tr><td>5 ou mais</td><td><span class="chip chip-red">Crítico</span> — deterioração iminente; intervenção urgente</td></tr>
                    </tbody>
                </table>
                <p><strong>Periodicidade:</strong> deve ser avaliado pelo menos uma vez por turno de trabalho (manhã, tarde ou noite).</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">PEWS — Pediatric Early Warning Score (Pediatria)</h4>
                <p>Equivalente pediátrico do MEWS. Aplicado a pacientes com menos de 18 anos.</p>
                <table class="scale-table">
                    <thead><tr><th>Pontuação</th><th>Classificação</th></tr></thead>
                    <tbody>
                        <tr><td>0 – 1 ponto</td><td><span class="chip chip-gray">Normal</span></td></tr>
                        <tr><td>2 – 3 pontos</td><td><span class="chip chip-yellow">Moderado</span> — atenção necessária</td></tr>
                        <tr><td>4 ou mais</td><td><span class="chip chip-red">Alto</span> — intervenção necessária</td></tr>
                    </tbody>
                </table>
                <p><strong>Periodicidade:</strong> uma vez por turno.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Escala de Braden — Risco de Lesão por Pressão</h4>
                <p>Avalia o risco de desenvolvimento de lesões por pressão. Pontuações mais baixas indicam maior risco.</p>
                <table class="scale-table">
                    <thead><tr><th>Pontuação</th><th>Classificação</th></tr></thead>
                    <tbody>
                        <tr><td>19 – 23 pontos</td><td><span class="chip chip-green">Sem risco</span></td></tr>
                        <tr><td>15 – 18 pontos</td><td><span class="chip chip-gray">Risco leve</span></td></tr>
                        <tr><td>13 – 14 pontos</td><td><span class="chip chip-yellow">Risco moderado</span></td></tr>
                        <tr><td>12 ou menos</td><td><span class="chip chip-red">Alto risco</span></td></tr>
                    </tbody>
                </table>
                <p><strong>Periodicidade:</strong> avaliação a cada 24 horas.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Escala de Morse — Risco de Quedas</h4>
                <p>Avalia o risco de quedas do paciente durante a internação.</p>
                <table class="scale-table">
                    <thead><tr><th>Pontuação</th><th>Classificação</th></tr></thead>
                    <tbody>
                        <tr><td>0 – 24 pontos</td><td><span class="chip chip-gray">Risco baixo</span></td></tr>
                        <tr><td>25 – 44 pontos</td><td><span class="chip chip-yellow">Risco moderado</span></td></tr>
                        <tr><td>45 ou mais</td><td><span class="chip chip-red">Alto risco</span></td></tr>
                    </tbody>
                </table>
                <p><strong>Periodicidade:</strong> avaliação a cada 24 horas.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Escala de Dor</h4>
                <p>Registrada pelos sinais vitais do paciente. Avalia a intensidade da dor relatada.</p>
                <table class="scale-table">
                    <thead><tr><th>Pontuação</th><th>Classificação</th></tr></thead>
                    <tbody>
                        <tr><td>0</td><td><span class="chip chip-green">Sem dor</span></td></tr>
                        <tr><td>1 – 3</td><td><span class="chip chip-gray">Dor leve</span></td></tr>
                        <tr><td>4 – 6</td><td><span class="chip chip-yellow">Dor moderada</span></td></tr>
                        <tr><td>7 ou mais</td><td><span class="chip chip-red">Dor intensa</span></td></tr>
                    </tbody>
                </table>
                <p><strong>Periodicidade:</strong> uma vez por turno.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Escala TEV — Risco de Tromboembolismo Venoso</h4>
                <p>Avalia o risco de formação de trombos venosos durante a internação.</p>
                <table class="scale-table">
                    <thead><tr><th>Pontuação</th><th>Classificação</th></tr></thead>
                    <tbody>
                        <tr><td>0 – 2 pontos</td><td><span class="chip chip-gray">Risco baixo</span></td></tr>
                        <tr><td>3 – 6 pontos</td><td><span class="chip chip-yellow">Risco moderado</span></td></tr>
                        <tr><td>7 ou mais</td><td><span class="chip chip-red">Alto risco</span></td></tr>
                    </tbody>
                </table>
                <p><strong>Periodicidade:</strong> avaliação a cada 24 horas.</p>

                <div class="callout callout-warn">
                    <div class="callout-title">Escalas atrasadas — o que significa</div>
                    O sistema verifica automaticamente se cada escala foi avaliada dentro do período esperado. Quando uma escala está fora do prazo, o sistema exibe o score com destaque em vermelho no cartão do paciente. Isso não significa que o estado do paciente piorou — significa apenas que a avaliação registrada no prontuário está desatualizada. A equipe responsável deve realizar uma nova avaliação e registrá-la no prontuário eletrônico.
                </div>

                <p>A janela de tolerância para considerar uma escala válida é de <strong>30 minutos antes do início do turno</strong>. Isso evita falsos alertas quando a avaliação foi feita imediatamente antes da virada do turno.</p>
            </section>

            {{-- 6.5 Pendências --}}
            <section class="section" id="sec-pendencias">
                <h3 class="ss-title section-anchor">6.5 Pendências</h3>

                <p>O sistema identifica e organiza automaticamente as pendências assistenciais de cada paciente, cruzando as prescrições e agendamentos registrados no prontuário eletrônico com o status de execução de cada item.</p>

                <p><strong>Tipos de pendências monitoradas:</strong></p>
                <ul>
                    <li><strong>Exames e procedimentos</strong> — exames laboratoriais, de imagem e procedimentos prescritos ainda não executados</li>
                    <li><strong>Hemoterapia</strong> — transfusões de sangue e hemoderivados com status pendente</li>
                    <li><strong>Antimicrobianos</strong> — antibióticos e antimicrobianos com dose ou ciclo em andamento</li>
                    <li><strong>Quimioterapia</strong> — ciclos quimioterápicos pendentes ou em andamento</li>
                    <li><strong>Cirurgia agendada</strong> — procedimentos cirúrgicos com data futura agendada</li>
                </ul>

                <p>Cada pendência é exibida com:</p>
                <ul>
                    <li><strong>Descrição</strong> do exame ou procedimento</li>
                    <li><strong>Horário previsto</strong> para a realização</li>
                    <li><strong>Tempo em atraso</strong> — quando o horário já passou, aparece a indicação de há quantas horas ou minutos o item está atrasado</li>
                    <li><strong>Status no laboratório</strong> — quando disponível, exibe se o exame já foi coletado, aguardando resultado, ou com resultado liberado (integração com o sistema SCOLA)</li>
                </ul>

                <p>As pendências são classificadas por <strong>proximidade temporal</strong>: itens com horário iminente ou já atrasados aparecem em destaque no início da lista. A pendência mais urgente do paciente aparece resumida no próprio cartão do painel.</p>

                <div class="callout callout-info">
                    <div class="callout-title">Pendências prioritárias</div>
                    Na janela detalhada do paciente, as pendências são agrupadas por categoria e ordenadas por urgência. Itens com até 3 horas de antecedência ou já atrasados são marcados como prioritários.
                </div>
            </section>

            {{-- 6.6 Equipes Multidisciplinares --}}
            <section class="section" id="sec-multi">
                <h3 class="ss-title section-anchor">6.6 Equipes Multidisciplinares</h3>

                <p>O sistema sinaliza quais especialidades da equipe multidisciplinar têm avaliação solicitada para cada paciente. Quando uma solicitação está aberta (ainda não respondida), um ícone da respectiva equipe aparece no cartão do paciente.</p>

                <p><strong>Equipes monitoradas:</strong></p>
                <ul>
                    <li>Fisioterapia</li>
                    <li>Psicologia</li>
                    <li>Nutrição</li>
                    <li>Fonoaudiologia</li>
                    <li>Serviço Social</li>
                    <li>Acessos Vasculares (cateterismo, PICC e similares)</li>
                </ul>

                <p>Na janela detalhada do paciente, é possível visualizar o histórico de solicitações com data de abertura, profissional solicitante, status (aberta ou respondida) e data de resposta.</p>
            </section>

            {{-- 6.7 Janela do Paciente (Modal) --}}
            <section class="section" id="sec-modal">
                <h3 class="ss-title section-anchor">6.7 Janela do Paciente</h3>

                <p>Ao clicar em <strong>Detalhes</strong> em qualquer cartão, abre-se uma janela completa com todas as informações disponíveis para aquele paciente. Esta janela está organizada em abas.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Visão Geral</h4>
                <p>Apresenta o resumo do paciente: identificação completa, diagnóstico principal e comorbidades, alergias detalhadas (com nível de gravidade: leve, moderada ou grave), dispositivos em uso (cateter, sonda, dreno etc.) e avaliação de enfermagem.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Escalas</h4>
                <p>Exibe o valor atual de cada escala com classificação de risco e variação em relação à avaliação anterior (seta indicando se o score aumentou ou permaneceu estável). Também mostra a data e hora da última avaliação e alerta quando está fora do prazo.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Pendências</h4>
                <p>Lista completa de pendências, agrupadas por categoria (exames, procedimentos, hemoterapia, antimicrobianos etc.), com horários, status de execução e atraso calculado.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Plano Terapêutico</h4>
                <p>Apresenta as prescrições ativas do paciente divididas em nove categorias, acessadas por abas internas:</p>
                <ul>
                    <li><strong>Medicamentos</strong> — prescrições ativas (sem suspensão), agrupadas por número de prescrição. Inclui filtro de busca por nome do medicamento e botão para exibir somente antimicrobianos. Ordenáveis por número de prescrição (crescente ou decrescente).</li>
                    <li><strong>Proc. e Exames</strong> — procedimentos e exames prescritos. O filtro padrão exibe itens do dia anterior, do dia atual e do dia seguinte (Ontem–Hoje–Amanhã). Botão para visualizar todas as pendências abertas.</li>
                    <li><strong>Hemoterapia</strong> — transfusões de sangue e hemoderivados prescritos, com produto, quantidade e status de execução.</li>
                    <li><strong>Quimioterapia</strong> — ciclos quimioterápicos ativos ou agendados, com protocolo e fase do tratamento.</li>
                    <li><strong>Nutrição</strong> — dietas hospitalares e suplementos nutricionais prescritos.</li>
                    <li><strong>Recomendações</strong> — prescrições médicas textuais e orientações específicas de cuidado.</li>
                    <li><strong>Intervenções</strong> — intervenções de enfermagem prescritas para o paciente.</li>
                    <li><strong>Gasoterapia</strong> — terapia com gases medicinais e suporte ventilatório. Exibe modalidade de uso, tipo de gás, parâmetros ventilatórios (FiO₂, PEEP, PIP, volume corrente e frequência ventilatória), equipamentos prescritos e horários programados.</li>
                    <li><strong>Diálise</strong> — terapia renal substitutiva. Exibe modalidade (hemodiálise ou diálise peritoneal), número de sessões por semana, dias de realização, duração da sessão, fluxo de sangue e índice Kt/V.</li>
                </ul>
                <p>A navegação por abas é paginada com 10 itens por página. Todas as categorias exibem apenas itens ativos no momento da consulta.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Hemoculturas</h4>
                <p>Histórico de hemoculturas do paciente com data de coleta, status de execução e resultado quando disponível via integração com o laboratório.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Multidisciplinar</h4>
                <p>Lista de solicitações para as equipes multidisciplinares com status (aberta/respondida), motivo da solicitação e profissional responsável pela resposta.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Aba: Alertas</h4>
                <p>Alertas ativos registrados para o paciente no prontuário, como restrições de dieta, cuidados especiais e outras observações críticas.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Navegação entre pacientes</h4>
                <p>Dentro da janela do paciente, use os botões de seta (<strong>anterior</strong> / <strong>próximo</strong>) para navegar entre os pacientes do setor sem fechar a janela. A ordem de navegação segue a mesma sequência exibida no painel principal, incluindo os filtros ativos.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Chat e Anotações do paciente</h4>
                <p>A janela do paciente também inclui o chat do turno, descrito em detalhes na próxima seção.</p>
            </section>

            {{-- 6.8 Chat --}}
            <section class="section" id="sec-chat">
                <h3 class="ss-title section-anchor">6.8 Chat e Anotações do Turno</h3>

                <p>Cada paciente possui um <strong>canal de comunicação exclusivo</strong> disponível na janela de detalhes. Esse canal funciona como um chat em tempo real onde a equipe de enfermagem registra observações, avaliações e informações relevantes para o turno.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Como funciona</h4>
                <ul>
                    <li>As mensagens são exibidas em ordem cronológica e identificadas pelo nome do usuário e horário de envio</li>
                    <li>O conteúdo é permanente — as anotações ficam registradas para consulta histórica mesmo após o fim do turno</li>
                    <li>Mensagens enviadas por qualquer membro da equipe aparecem em tempo real para todos os usuários que estiverem com o paciente aberto</li>
                    <li>É possível editar uma mensagem recém-enviada (dentro de um curto período após o envio)</li>
                </ul>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Avaliação fixada</h4>
                <p>Qualquer mensagem pode ser <strong>fixada</strong> clicando no ícone de pino ao lado dela. A avaliação fixada fica destacada no topo do chat e também aparece resumida no cartão do paciente no painel principal. Apenas uma avaliação pode estar fixada por vez. Para desfixar, clique novamente no ícone de pino.</p>

                <p>Use a fixação para destacar a avaliação mais importante do turno — aquela que o próximo enfermeiro deve ler com prioridade.</p>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Lista de verificação (checklist)</h4>
                <p>O chat também possui uma lista de verificação por paciente, onde a equipe pode marcar itens de cuidado como realizados durante o turno. Essa lista é visível na janela do paciente e serve como apoio ao controle das tarefas de enfermagem.</p>

                <div class="callout callout-info">
                    <div class="callout-title">Anotações e passagem de plantão</div>
                    O painel principal exibe um indicador visual nos cartões dos pacientes que já possuem anotação registrada no turno atual. Use o filtro <strong>Passagem</strong> para ver rapidamente quais pacientes ainda não foram anotados.
                </div>
            </section>

            {{-- 6.9 Iniciando a Passagem --}}
            <section class="section" id="sec-passagem">
                <h3 class="ss-title section-anchor">6.9 Iniciando a Passagem de Plantão</h3>

                <p>O botão <strong>Iniciar Passagem</strong> está disponível no cabeçalho do painel principal para os enfermeiros que têm leitos configurados. Ao clicar, o sistema abre um fluxo estruturado de passagem de plantão, percorrendo sequencialmente cada leito do enfermeiro.</p>

                <p><strong>Pré-requisitos:</strong></p>
                <ul>
                    <li>O enfermeiro deve ter os leitos configurados em <strong>Meus Leitos</strong></li>
                    <li>A passagem só pode ser iniciada dentro da janela do turno atual</li>
                    <li>Uma passagem por turno pode ser realizada por leito (não é possível iniciar uma segunda passagem para o mesmo leito no mesmo turno)</li>
                </ul>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Como realizar a passagem</h4>

                <div class="steps">
                    <div class="step"><div class="step-body">Clique em <strong>Iniciar Passagem</strong> no cabeçalho do painel. O sistema abrirá automaticamente o primeiro paciente da sua lista.</div></div>
                    <div class="step"><div class="step-body">Revise as informações do paciente, as escalas, pendências e anotações anteriores na janela aberta.</div></div>
                    <div class="step"><div class="step-body">Registre no chat as observações relevantes para o próximo turno.</div></div>
                    <div class="step"><div class="step-body">Use os botões <strong>Anterior</strong> e <strong>Próximo</strong> para navegar entre os seus leitos. O sistema vai registrando o progresso da passagem.</div></div>
                    <div class="step"><div class="step-body">Ao percorrer todos os leitos, clique em <strong>Finalizar Passagem</strong>. O sistema registra o horário de início e término, o percentual de conclusão e salva a sessão.</div></div>
                </div>

                <div class="callout callout-tip">
                    <div class="callout-title">Passagem interrompida</div>
                    Se a passagem for interrompida antes de ser concluída (por exemplo, por algum chamado urgente), o sistema mantém a sessão em andamento. Na próxima vez que você acessar o painel, aparecerá um aviso informando que há uma passagem em andamento, com opção de <strong>Retomar</strong> ou <strong>Ignorar</strong>.
                </div>

                <h4 style="font-size:0.88rem; font-weight:700; margin:1.25rem 0 0.5rem; color:var(--ink)">Mensagens de bloqueio</h4>
                <p>O sistema pode bloquear o início da passagem em três situações:</p>
                <ul>
                    <li><strong>Leitos não configurados:</strong> o enfermeiro não configurou seus leitos em Meus Leitos. Acesse as configurações e selecione seus leitos antes de tentar novamente.</li>
                    <li><strong>Sessão em andamento:</strong> já existe uma passagem ativa para esses leitos neste turno. Conclua ou retome a sessão existente.</li>
                    <li><strong>Passagem já concluída:</strong> a passagem de todos os seus leitos já foi finalizada neste turno. O botão ficará disponível novamente no próximo turno.</li>
                </ul>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 7. HUDDLE — GESTÃO DE ALTAS
            ══════════════════════════════════════════════════════════ --}}
            {{-- ══════════════════════════════════════════════════════════
                 7. HUDDLE — GESTÃO DE ALTAS
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-huddle">
                <h2 class="s-title section-anchor"><span class="s-num">7.</span> Huddle — Gestão de Altas</h2>

                <p>O <strong>Huddle — Gestão de Altas</strong> é o painel voltado à <strong>gestão do fluxo de altas</strong> dos pacientes internados. Ele acompanha, dia a dia, o quanto o plano de cuidado de cada paciente avançou em direção à alta, usando a metodologia <strong>Red2Green</strong>: todo dia começa <strong>vermelho (Red)</strong> e só se torna <strong>verde (Green)</strong> quando o plano do dia efetivamente avançou a jornada de alta.</p>

                <p>Assim como o SBAR, o Huddle lê os dados dos pacientes diretamente do prontuário Tasy e os organiza em uma grade de cartões por setor. O acesso é controlado por permissão: <strong>visualizar</strong> o painel e <strong>conduzir</strong> o Huddle (preencher) são liberações distintas.</p>

                <div class="callout callout-info">
                    <div class="callout-title">Cabeçalho do painel</div>
                    No topo do Huddle você encontra o seletor de <strong>hospital</strong> e <strong>setor</strong>, a <strong>busca por leito ou paciente</strong>, o botão <strong>Atualizar</strong> (força a leitura mais recente do Tasy) e o botão <strong>Legenda e orientações</strong>, que abre a explicação das cores e dos selos do cartão.
                </div>

                <h3 class="ss-title section-anchor" id="sec-huddle-card" style="font-size:1rem; color:var(--brand); border-bottom:1px solid var(--brand-light); padding-bottom:0.3rem; margin-bottom:0.85rem;">7.1 O cartão do Huddle</h3>
                <p>Cada leito é um cartão. Os principais elementos são:</p>
                <ul>
                    <li><strong>Status Red / Green / Round:</strong> a cor do dia do paciente. <em>Round</em> indica que o paciente não tem previsão de alta nas próximas 72 horas e deve ser discutido em round. A <strong>borda do cartão</strong> também segue essa cor.</li>
                    <li><strong>Contadores da internação:</strong> quantos dias vermelhos e verdes o paciente acumulou.</li>
                    <li><strong>Dias seguidos:</strong> a sequência de dias na mesma cor (ex.: "3 dias seguidos em red").</li>
                    <li><strong>Exames pendentes:</strong> um selo em destaque com a quantidade de exames a resolver.</li>
                    <li><strong>Pendências:</strong> indicadores coloridos (vermelho = pendente, verde = resolvido, cinza = sem informação) para exames, procedimentos, terapias, multidisciplinar, orientação, transporte e prescrição de alta.</li>
                </ul>

                <h3 class="ss-title section-anchor" id="sec-huddle-detalhes" style="font-size:1rem; color:var(--brand); border-bottom:1px solid var(--brand-light); padding-bottom:0.3rem; margin-bottom:0.85rem;">7.2 Detalhes do paciente</h3>
                <p>O botão <strong>Detalhes</strong> abre a janela do paciente, com o checklist de critérios de alta. Ao responder cada item, o sistema calcula a cor do dia: <strong>se qualquer item for marcado como vermelho, o paciente fica vermelho</strong>, independentemente dos demais.</p>
                <ul>
                    <li><strong>Comentários obrigatórios:</strong> quando o paciente está vermelho, aparece ao final do formulário um campo de comentários que deve ser preenchido.</li>
                    <li><strong>Auditoria:</strong> cada resposta e o dia registram <strong>quem preencheu e quando</strong> (login e data/hora).</li>
                </ul>

                <h3 class="ss-title section-anchor" id="sec-huddle-round" style="font-size:1rem; color:var(--brand); border-bottom:1px solid var(--brand-light); padding-bottom:0.3rem; margin-bottom:0.85rem;">7.3 Round Unidade</h3>
                <p>No canto superior direito de cada cartão há o botão <strong>Round Unidade</strong>, que abre o formulário de gestão à vista <strong>da unidade</strong> (um preenchimento por setor, por dia), organizado em quatro eixos:</p>
                <ul>
                    <li><strong>Eixo 1 — Ocupação e fluxo:</strong> altas e admissões previstas, leitos bloqueados por isolamento e por manutenção.</li>
                    <li><strong>Eixo 2 — Risco clínico e segurança:</strong> paciente grave sem leito, falha de medicação crítica, evento adverso (24h), contenção física/química, quebra de barreira, LPP e quedas.</li>
                    <li><strong>Eixo 3 — Condições operacionais:</strong> déficit de equipe e atraso de exame crítico.</li>
                    <li><strong>Eixo 4 — Classificação:</strong> classificação da unidade (Verde, Amarelo ou Vermelho), justificativa e medidas imediatas.</li>
                </ul>
                <p>O botão fica <strong>vermelho</strong> quando o paciente do cartão está vermelho, e exibe um <strong>✓</strong> quando a unidade já teve o Round preenchido no dia.</p>
                <div class="callout callout-warn">
                    <div class="callout-title">Não pode ser alterado após salvo</div>
                    Uma vez salvo no dia, o Round Unidade fica <strong>somente leitura</strong> e não pode ser modificado — garantindo a integridade do registro.
                </div>

                <h3 class="ss-title">7.4 Regras de funcionamento</h3>
                <ul>
                    <li><strong>Dias úteis:</strong> a rotina de preenchimento do Huddle não ocorre em <strong>fins de semana e feriados</strong> — nesses dias o formulário fica somente leitura.</li>
                    <li><strong>Atualização diária:</strong> todo dia útil, pela manhã, o sistema materializa automaticamente o Huddle a partir do Tasy (cada paciente ativo começa o dia em vermelho).</li>
                    <li><strong>Dados sempre atualizados:</strong> os dados vêm do Tasy com atualização periódica; o botão <strong>Atualizar</strong> força a busca imediata.</li>
                </ul>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 8. INSTALAÇÃO COMO APP
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-app">
                <h2 class="s-title section-anchor"><span class="s-num">8.</span> Instalar como Aplicativo</h2>

                <p>O sistema pode ser instalado como um aplicativo no celular ou tablet, funcionando de forma similar a um app nativo. Isso facilita o acesso rápido sem precisar abrir o navegador e digitar o endereço toda vez. Após instalado, o app fica disponível na tela inicial do dispositivo.</p>

                <div class="callout callout-warn">
                    <div class="callout-title">Importante</div>
                    O aplicativo instalado ainda requer conexão com a rede interna da Santa Casa para funcionar. Ele não opera offline para dados clínicos.
                </div>

                <h3 class="ss-title">7.1 Instalação no Android (Chrome)</h3>

                <div class="steps">
                    <div class="step"><div class="step-body">Abra o navegador Chrome no seu dispositivo Android e acesse o endereço do sistema.</div></div>
                    <div class="step"><div class="step-body">Toque no ícone de <strong>três pontos</strong> no canto superior direito do Chrome.</div></div>
                    <div class="step"><div class="step-body">Selecione <strong>Adicionar à tela inicial</strong> ou <strong>Instalar app</strong>.</div></div>
                    <div class="step"><div class="step-body">Confirme o nome do aplicativo e toque em <strong>Adicionar</strong>. Um ícone do sistema aparecerá na tela inicial.</div></div>
                    <div class="step"><div class="step-body">Para abrir o sistema, toque no ícone <strong>Plantão</strong> na tela inicial. O app abrirá em tela cheia, sem a barra do navegador.</div></div>
                </div>

                <div class="callout callout-tip">
                    <div class="callout-title">Banner de instalação automático</div>
                    Em alguns dispositivos Android, o Chrome exibe automaticamente um banner na parte inferior da tela com a opção de instalar o app após alguns acessos. Basta tocar em <strong>Instalar</strong> quando esse banner aparecer.
                </div>

                <h3 class="ss-title">7.2 Instalação no iPhone e iPad (Safari)</h3>

                <p>No iOS, a instalação só funciona pelo navegador Safari. Outros navegadores (Chrome, Edge) no iPhone não oferecem suporte à instalação de aplicativos web.</p>

                <div class="steps">
                    <div class="step"><div class="step-body">Abra o <strong>Safari</strong> no iPhone ou iPad e acesse o endereço do sistema.</div></div>
                    <div class="step"><div class="step-body">Toque no ícone de <strong>compartilhamento</strong> (retângulo com seta para cima) na barra inferior do Safari.</div></div>
                    <div class="step"><div class="step-body">Role a lista de opções e toque em <strong>Adicionar à Tela de Início</strong>.</div></div>
                    <div class="step"><div class="step-body">Confirme o nome exibido (aparecerá como <strong>Plantão</strong>) e toque em <strong>Adicionar</strong>.</div></div>
                    <div class="step"><div class="step-body">O ícone do sistema aparecerá na tela inicial. Toque nele para abrir o app em modo de tela cheia.</div></div>
                </div>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 9. DICAS IMPORTANTES
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-dicas">
                <h2 class="s-title section-anchor"><span class="s-num">9.</span> Dicas Importantes</h2>

                <h3 class="ss-title">Manter os leitos configurados atualizados</h3>
                <p>Se você mudar de setor ou de escala, atualize seus leitos em <strong>Meus Setores → Meus Leitos</strong>. A configuração é salva no seu perfil e é utilizada pelo sistema para direcionar a passagem de plantão corretamente.</p>

                <h3 class="ss-title">O botão Atualizar e o botão Forçar Atualização</h3>
                <p>O botão <strong>Atualizar</strong> recarrega os dados dinâmicos do setor (pendências, anotações e escalas) sem apagar o cache dos dados estáticos, tornando a atualização mais rápida. Caso perceba que os dados estão muito desatualizados, use o botão de <strong>Forçar Atualização</strong> (acessível pelo ícone ao lado do botão Atualizar) para recarregar tudo do início.</p>

                <h3 class="ss-title">Conexão lenta durante a troca de turno</h3>
                <p>É normal que o sistema demore alguns segundos a mais para carregar exatamente nos horários de troca de turno (07h, 13h e 19h), pois há um aumento simultâneo de acessos. O sistema pre-carrega os dados dos setores 15 minutos antes de cada troca para minimizar esse impacto. Se a lentidão persistir, o indicador de conexão no canto inferior direito da tela exibe o status da rede e o tempo de resposta.</p>

                <h3 class="ss-title">Indicador de conexão</h3>
                <p>Um pequeno indicador no canto inferior direito da tela mostra o status da conexão com o servidor:</p>
                <ul>
                    <li><strong>Verde</strong> — conexão estável (tempo de resposta abaixo de 500 ms)</li>
                    <li><strong>Amarelo</strong> — Wi-Fi lento (500 ms a 2 segundos). O sistema continua funcionando normalmente, mas os carregamentos podem demorar um pouco mais.</li>
                    <li><strong>Vermelho</strong> — conexão muito lenta ou interrompida. Verifique o Wi-Fi ou chame o suporte de TI.</li>
                </ul>
                <p>Clique no indicador para ver detalhes sobre o tempo de resposta e uma explicação do status atual.</p>

                <h3 class="ss-title">Filtros na passagem de plantão</h3>
                <p>Utilize os filtros para focar a atenção onde ela é mais necessária. Por exemplo: no início do turno, filtre por <strong>Passagem: Sem anotação</strong> para identificar quais pacientes ainda não foram avaliados. Ao final, altere para <strong>Passagem: Com anotação</strong> para conferir se todos foram contemplados.</p>

                <h3 class="ss-title">Fixar a avaliação mais importante</h3>
                <p>Ao registrar a avaliação de cada paciente no chat, fixe a mensagem mais relevante para o próximo turno. A avaliação fixada aparece em destaque no cartão do paciente no painel, poupando tempo para o enfermeiro que chega.</p>

                <h3 class="ss-title">Dados do prontuário vs. dados do sistema</h3>
                <p>O sistema exibe os dados que estão registrados no prontuário eletrônico (Tasy). Se uma escala não aparece ou um exame não está listado como pendente, verifique primeiro se o lançamento foi feito corretamente no prontuário. O sistema não substitui o prontuário — ele é uma janela de visualização e comunicação que depende do correto preenchimento das informações na fonte.</p>

                <h3 class="ss-title">Problemas técnicos</h3>
                <p>Em caso de problemas técnicos com o sistema, entre em contato com a equipe de Inovação pelo e-mail <strong>inovacao@santacasa.org.br</strong> ou pelo ramal de suporte de TI da unidade.</p>

                <hr class="divider">
                <p style="font-size:0.78rem; color:var(--muted); text-align:center">
                    Manual do Sistema de Passagem de Plantão — Santa Casa de Porto Alegre<br>
                    Equipe de Inovação &nbsp;·&nbsp; inovacao@santacasa.org.br &nbsp;·&nbsp; {{ date('Y') }}
                </p>
            </section>

            <hr class="divider">

            {{-- ══════════════════════════════════════════════════════════
                 10. GLOSSÁRIO
            ══════════════════════════════════════════════════════════ --}}
            <section class="section" id="sec-glossario">
                <h2 class="s-title section-anchor"><span class="s-num">10.</span> Glossário</h2>
                <p>Definições dos principais termos utilizados no sistema e neste manual.</p>

                <div class="glossary-item gi-open" x-data="{ open: true }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">SBAR</div>
                    <p class="gi-content" x-show="open">Metodologia internacional de comunicação clínica estruturada. As letras significam: <strong>S</strong>ituação (o que está acontecendo com o paciente agora), <strong>B</strong>ackground (histórico clínico relevante), <strong>A</strong>valiação (interpretação da equipe sobre o estado atual) e <strong>R</strong>ecomendação (o que precisa ser feito ou acompanhado).</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">MEWS — Modified Early Warning Score</div>
                    <p class="gi-content" x-show="open">Escala de risco para pacientes adultos (18 anos ou mais). Calculada a partir de sinais vitais registrados no prontuário. Valores: 0–2 = estável, 3 = alerta, 4 = alto risco, ≥5 = crítico.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">PEWS — Pediatric Early Warning Score</div>
                    <p class="gi-content" x-show="open">Versão pediátrica do MEWS, aplicada a pacientes com menos de 18 anos. Avalia comportamento, função cardiovascular e respiratória.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Escala de Braden</div>
                    <p class="gi-content" x-show="open">Avalia o risco de desenvolvimento de lesão por pressão (LPP). Pontuações menores indicam maior risco. O sistema destaca pacientes com risco moderado a grave.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Escala de Morse</div>
                    <p class="gi-content" x-show="open">Avalia o risco de queda do paciente. Utilizada para priorizar intervenções preventivas. Risco alto: ≥45 pontos.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">TEV — Tromboembolismo Venoso</div>
                    <p class="gi-content" x-show="open">Risco de formação de coágulos venosos. O sistema exibe o resultado da avaliação de profilaxia registrado no prontuário.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Pendência assistencial</div>
                    <p class="gi-content" x-show="open">Item prescrito ou solicitado que ainda não foi executado: exame não coletado ou sem resultado, procedimento não realizado, medicamento não administrado, hemoterapia pendente, entre outros.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Alta médica vs. Alta efetivada</div>
                    <p class="gi-content" x-show="open"><strong>Alta médica</strong>: o médico concedeu alta, mas o paciente ainda não saiu fisicamente do leito. <strong>Alta efetivada</strong>: o paciente já deixou a unidade. Ambos aparecem com destaque visual nos cartões.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Tasy</div>
                    <p class="gi-content" x-show="open">Prontuário eletrônico do paciente utilizado pela Santa Casa (sistema Oracle). O sistema de passagem de plantão lê os dados do Tasy em tempo quase real, mas não grava informações nele.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Passagem de plantão estruturada</div>
                    <p class="gi-content" x-show="open">Funcionalidade que guia o enfermeiro por cada leito sob sua responsabilidade no momento da troca de turno, registrando o horário de início, fim e percentual de conclusão.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Plano Terapêutico</div>
                    <p class="gi-content" x-show="open">Aba da janela do paciente que consolida todas as prescrições ativas, organizada em nove categorias: medicamentos, procedimentos e exames, hemoterapia, quimioterapia, nutrição, recomendações, intervenções, gasoterapia e diálise. Leitura direta do prontuário eletrônico Tasy — não permite edição.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Gasoterapia</div>
                    <p class="gi-content" x-show="open">Terapia com gases medicinais, incluindo oxigenoterapia e suporte ventilatório (ventilação mecânica invasiva ou não invasiva). Os parâmetros prescritos (FiO₂, PEEP, volume corrente etc.) são exibidos na aba Gasoterapia do Plano Terapêutico.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Diálise</div>
                    <p class="gi-content" x-show="open">Terapia renal substitutiva para pacientes com insuficiência renal. O sistema exibe a modalidade (hemodiálise ou diálise peritoneal), parâmetros de sessão e frequência semanal. Dados provenientes diretamente do prontuário Tasy.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">Avaliações do Turno</div>
                    <p class="gi-content" x-show="open">Visão consolidada de todas as anotações do chat registradas no turno atual e no turno anterior para o setor selecionado. Acessada pelo botão <em>Avaliações</em> no cabeçalho do painel SBAR. Permite revisar rapidamente o setor inteiro sem abrir paciente por paciente.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">SCOLA</div>
                    <p class="gi-content" x-show="open">Sistema de laboratório utilizado pela Santa Casa para gestão de exames. O sistema de passagem de plantão integra-se ao SCOLA para exibir o status atualizado de cada exame: coletado, aguardando resultado ou laudo liberado.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">LPP — Lesão por Pressão</div>
                    <p class="gi-content" x-show="open">Dano localizado na pele ou tecidos subjacentes, geralmente sobre uma proeminência óssea. A Escala de Braden avalia o risco de desenvolvimento de LPP. Pacientes com pontuação baixa na Braden (alto risco) são destacados visualmente no sistema.</p>
                </div>
                <div class="glossary-item" x-data="{ open: false }" :class="{ 'gi-open': open }">
                    <div class="gi-summary" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open" :aria-expanded="open.toString()">PICC</div>
                    <p class="gi-content" x-show="open">Cateter Central de Inserção Periférica. Dispositivo venoso de longa permanência, monitorado pelo serviço de Acessos Vasculares. A solicitação para avaliação desse serviço aparece como pendência multidisciplinar nos cartões do painel.</p>
                </div>
            </section>

        </div>{{-- /manual-content --}}
    </div>{{-- /content-area --}}

</div>{{-- /manual-wrap --}}

{{-- Mobile sidebar overlay --}}
<div id="sidebar-overlay" class="no-print fixed inset-0 bg-black/40 z-[199] hidden sm:hidden" onclick="closeSidebar()"></div>

@endsection

@push('scripts')
<script>
(function () {
    // ── Sidebar toggle (mobile) ─────────────────────────────────
    const sidebar  = document.getElementById('sidebar');
    const overlay  = document.getElementById('sidebar-overlay');
    const toggleBtn = document.getElementById('sidebar-toggle');

    function openSidebar()  { sidebar.classList.add('open');    overlay.classList.remove('hidden'); }
    function closeSidebar() { sidebar.classList.remove('open'); overlay.classList.add('hidden'); }
    window.closeSidebar = closeSidebar;

    if (toggleBtn) toggleBtn.addEventListener('click', openSidebar);

    // ── Active nav link on scroll ──────────────────────────────
    const sections  = document.querySelectorAll('.section-anchor');
    const navLinks  = document.querySelectorAll('#sidebar a');
    const contentEl = document.getElementById('content-area');
    const TOP_OFFSET = 56; // top-bar height in px

    function updateActive() {
        let current = null;
        const scrollTop = contentEl.scrollTop + TOP_OFFSET + 8;

        sections.forEach(sec => {
            // offsetTop relative to #manual-content parent; add its own offsetTop for accuracy
            const secTop = sec.offsetTop + (sec.offsetParent?.offsetTop ?? 0);
            if (secTop <= scrollTop) current = sec.id;
        });

        navLinks.forEach(link => {
            const href = link.getAttribute('href')?.replace('#', '');
            link.classList.toggle('active', href === current);
        });
    }

    // ── Intercept anchor clicks: scroll #content-area, not window ──
    sidebar.querySelectorAll('a[href^="#"]').forEach(a => {
        a.addEventListener('click', e => {
            e.preventDefault();
            const targetId = a.getAttribute('href').slice(1);
            const target   = document.getElementById(targetId);
            if (!target) return;

            // Calculate target's position relative to #content-area top
            const contentRect = contentEl.getBoundingClientRect();
            const targetRect  = target.getBoundingClientRect();
            const scrollTarget = contentEl.scrollTop + (targetRect.top - contentRect.top) - TOP_OFFSET;

            contentEl.scrollTo({ top: scrollTarget, behavior: 'smooth' });

            if (window.innerWidth < 768) closeSidebar();
        });
    });

    contentEl.addEventListener('scroll', updateActive, { passive: true });
    updateActive();
})();
</script>
@endpush
