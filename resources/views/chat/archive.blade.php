@extends('layouts.app')
@section('title', 'Histórico de Avaliações')

@push('head')
<style>
    [x-cloak] { display: none !important; }

    /* DataTables overrides */
    #archive-table_wrapper .dt-search input,
    #archive-table_wrapper .dataTables_filter input {
        border: 1px solid #D1D5DB;
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.875rem;
        outline: none;
        margin-left: 4px;
    }
    #archive-table_wrapper .dt-search input:focus,
    #archive-table_wrapper .dataTables_filter input:focus {
        border-color: #0071B9;
        box-shadow: 0 0 0 2px rgba(0,113,185,.15);
    }
    #archive-table_wrapper .dt-length select {
        border: 1px solid #D1D5DB;
        border-radius: 0.375rem;
        padding: 0.25rem 0.5rem;
        font-size: 0.875rem;
    }
    #archive-table_wrapper .dt-paging button {
        border: 1px solid #E5E7EB;
        border-radius: 0.375rem;
        padding: 0.25rem 0.625rem;
        font-size: 0.75rem;
        color: #4B5563;
        margin: 0 1px;
    }
    #archive-table_wrapper .dt-paging button.current {
        background-color: #0071B9 !important;
        color: white !important;
        border-color: #0071B9 !important;
    }
    #archive-table_wrapper .dt-paging button:hover:not(.current) {
        background-color: #F3F4F6;
    }
    #archive-table th { white-space: nowrap; }
</style>
@endpush

@section('content')
<div class="w-full px-3 my-2 text-gray-600">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex justify-between items-center mb-4">
        <span class="text-md md:text-2xl font-medium text-santacasa-100">
            <i class="fas fa-clock-rotate-left mr-1"></i>
            Histórico de Avaliações
        </span>
    </div>

    @if($stats && $stats->total > 0)
    {{-- ── Métricas ────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col gap-3 mb-4">

        {{-- Cards: 4 principais --}}
        <div class="grid grid-cols-2 md:grid-cols-4 gap-3">

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Atendimentos</p>
                <p class="text-2xl font-bold text-santacasa-100 mt-0.5">{{ number_format($stats->total) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">com anotações</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Total de anotações</p>
                <p class="text-2xl font-bold text-santacasa-200 mt-0.5">{{ number_format($stats->total_msgs) }}</p>
                <p class="text-xs text-gray-400 mt-0.5">registradas</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Cobertura contínua</p>
                <p class="text-2xl font-bold mt-0.5 {{ $coveragePct >= 70 ? 'text-green-600' : ($coveragePct >= 40 ? 'text-amber-500' : 'text-red-500') }}">
                    {{ $coveragePct }}%
                </p>
                <p class="text-xs text-gray-400 mt-0.5">≥ 3 anotações/atend.</p>
            </div>

            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Média por atend.</p>
                <p class="text-2xl font-bold text-santacasa-100 mt-0.5">{{ $stats->avg_per_attendance }}</p>
                <p class="text-xs text-gray-400 mt-0.5">anotações</p>
            </div>
        </div>

        {{-- Linha 2: Turnos + Time series (2 cols) --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-3">

            {{-- Distribuição por turno --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-3">Anotações por turno</p>
                @php
                    $shiftTotal = max(1, array_sum($shiftStats));
                    $shifts = [
                        'manha' => ['Manhã',  '#F59E0B'],
                        'tarde' => ['Tarde',  '#0071B9'],
                        'noite' => ['Noite',  '#073772'],
                    ];
                @endphp
                <div class="space-y-2">
                    @foreach($shifts as $k => [$label, $color])
                        @php $pct = round($shiftStats[$k] / $shiftTotal * 100); @endphp
                        <div>
                            <div class="flex justify-between text-[10px] text-gray-500 mb-1">
                                <span>{{ $label }}</span>
                                <span class="font-semibold">{{ $pct }}%</span>
                            </div>
                            <div class="h-2 bg-gray-100 rounded-full">
                                <div class="h-2 rounded-full transition-all" style="width:{{ $pct }}%; background-color:{{ $color }}"></div>
                            </div>
                        </div>
                    @endforeach
                </div>
                <div class="mt-2 pt-2 border-t border-gray-100">
                    <p class="text-[10px] text-gray-400">
                        Período:
                        <span class="text-gray-600 font-medium">{{ $stats->oldest ? \Carbon\Carbon::parse($stats->oldest)->format('d/m/Y') : '—' }}</span>
                        →
                        <span class="text-gray-600 font-medium">{{ $stats->newest ? \Carbon\Carbon::parse($stats->newest)->format('d/m/Y') : '—' }}</span>
                    </p>
                </div>
            </div>

            {{-- Time series: últimos 6 meses --}}
            <div class="bg-white rounded-lg border border-gray-200 shadow-sm px-4 py-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider mb-3">Anotações — últimos 6 meses</p>
                @php $maxMsg = max(1, max(array_column($months, 'messages'))); @endphp
                <div class="space-y-1.5">
                    @foreach($months as $data)
                        @php $pct = (int) round($data['messages'] / $maxMsg * 100); @endphp
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] text-gray-400 w-11 text-right flex-shrink-0">{{ $data['label'] }}</span>
                            <div class="flex-1 bg-gray-100 rounded-full h-2.5">
                                <div class="h-2.5 rounded-full" style="width:{{ $pct }}%; background-color:#0071B9"></div>
                            </div>
                            <span class="text-[10px] text-gray-600 font-semibold w-8 text-right">{{ $data['messages'] ?: '—' }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Linha 3: Ranking horizontal (full width) --}}
        @if(count($topAnnotators) > 0)
        <div class="bg-white rounded-lg border border-gray-200 shadow-sm">
            <div class="px-4 py-2.5 border-b border-gray-100 flex items-center gap-3">
                <p class="text-[10px] text-gray-400 font-semibold uppercase tracking-wider">Ranking de anotações — plantonistas</p>
                <span class="text-[10px] text-gray-300">Top {{ count($topAnnotators) }}</span>
            </div>
            <div class="p-3 grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-0.5">
                @foreach($topAnnotators as $i => $a)
                <div class="flex items-center gap-2 px-2 py-1.5 rounded-lg hover:bg-gray-50 transition-colors min-w-0">
                    <span class="text-[10px] font-bold w-5 text-center flex-shrink-0 {{ $i === 0 ? 'text-amber-400' : ($i === 1 ? 'text-gray-400' : ($i === 2 ? 'text-amber-700' : 'text-gray-200')) }}">{{ $i + 1 }}º</span>
                    <x-ui.user-avatar :photo="$a['photo']" :name="$a['name']" class="w-7 h-7 flex-shrink-0" />
                    <div class="min-w-0 flex-1">
                        <p class="text-xs font-medium text-gray-700 truncate">{{ $a['name'] }}</p>
                        <p class="text-[10px] text-gray-400 truncate">{{ $a['username'] }} &middot; <span class="font-semibold text-santacasa-100">{{ number_format($a['count']) }}</span></p>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
        @endif
    </div>
    @endif

    {{-- ── Tabela DataTables ─────────────────────────────────────────────────── --}}
    <div class="bg-white shadow-sm rounded-lg border border-gray-200 overflow-hidden">

        {{-- Tabela --}}
        <div class="overflow-x-auto p-2">
            <table id="archive-table" class="min-w-full text-sm" style="width:100%">
                <thead class="bg-gray-50 border-b border-gray-200">
                    <tr>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:110px">Atendimento</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:180px">Paciente</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:160px">Setor</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:100px">Internação</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:100px">Alta</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:60px">Dias</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:70px">Anot.</th>
                        <th class="px-3 py-2.5 text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider" style="width:110px">Últ. Anot.</th>
                        <th class="px-3 py-2.5" style="width:60px"></th>
                    </tr>
                </thead>
                <tbody></tbody>
            </table>
        </div>
    </div>

    {{-- ── Modal de mensagens (mesmo padrão dos modais de usuário) ────────────── --}}
    <div id="archive-modal" class="fixed inset-0 z-[9998] hidden" x-data="{ open: false }" x-init="
        $el._show = () => { open = true; document.body.style.overflow = 'hidden'; };
        $el._hide = () => { open = false; document.body.style.overflow = ''; };
        $watch('open', val => { if (!val) archiveModalDestroy(); });
    ">
        <div
            x-show="open"
            x-transition:enter="transition ease-out duration-300"
            x-transition:enter-start="opacity-0"
            x-transition:enter-end="opacity-100"
            x-transition:leave="transition ease-in duration-200"
            x-transition:leave-start="opacity-100"
            x-transition:leave-end="opacity-0"
            class="absolute inset-0"
        >
            {{-- Backdrop --}}
            <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" onclick="closeModal('archive-modal')"></div>

            {{-- Container --}}
            <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
                <div
                    class="relative bg-white flex flex-col overflow-hidden
                           w-full h-full
                           sm:w-[95vw] sm:h-auto sm:max-h-[90vh] sm:rounded-2xl
                           lg:w-[860px]
                           shadow-2xl"
                    onclick="event.stopPropagation()"
                    x-show="open"
                    x-transition:enter="transition ease-out duration-300"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-200"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-4"
                >
                    {{-- Header --}}
                    <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                        <div class="flex items-center gap-3 min-w-0">
                            <i class="fas fa-clock-rotate-left text-white flex-shrink-0"></i>
                            <div class="min-w-0">
                                <h2 class="text-base font-bold text-white leading-tight truncate" id="archive-modal-title">Anotações</h2>
                                <p class="text-xs text-white/70" id="archive-modal-subtitle"></p>
                            </div>
                        </div>
                        <button onclick="closeModal('archive-modal')" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                            </svg>
                        </button>
                    </div>

                    {{-- Content --}}
                    <div class="flex-1 overflow-y-auto min-h-0 p-4">
                        {{-- Loading --}}
                        <div id="archive-modal-loading" class="flex items-center justify-center py-12">
                            <div class="text-center">
                                <div class="w-7 h-7 border-gray-200 rounded-full animate-spin mx-auto mb-2" style="border-width:3px; border-top-color:#0071B9"></div>
                                <p class="text-xs text-gray-400">Carregando...</p>
                            </div>
                        </div>

                        {{-- Erro --}}
                        <div id="archive-modal-error" class="hidden flex-col items-center justify-center py-12 gap-3">
                            <i class="fas fa-triangle-exclamation text-3xl text-amber-400"></i>
                            <p class="text-sm text-gray-500" id="archive-modal-error-msg">Não foi possível carregar as mensagens.</p>
                            <button onclick="archiveModalLoad()" class="px-3 py-1.5 text-sm bg-[#004D9D] text-white rounded-md hover:bg-[#003d7a] transition">
                                Tentar novamente
                            </button>
                        </div>

                        {{-- Tabela --}}
                        <div id="archive-modal-table" class="hidden">
                            <table id="modal-msgs-dt" class="min-w-full text-sm" style="width:100%">
                                <thead>
                                    <tr>
                                        <th class="text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider px-2 py-1.5">Data/Hora</th>
                                        <th class="text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider px-2 py-1.5">Usuário</th>
                                        <th class="text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider px-2 py-1.5">Turno</th>
                                        <th class="text-left text-[10px] font-semibold text-gray-500 uppercase tracking-wider px-2 py-1.5">Mensagem</th>
                                    </tr>
                                </thead>
                                <tbody id="modal-msgs-body"></tbody>
                            </table>
                        </div>

                        {{-- Vazio --}}
                        <div id="archive-modal-empty" class="hidden text-center py-8 text-gray-400 text-sm">
                            Nenhuma anotação encontrada.
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>
@endsection

@push('scripts')
<script>
// ── Utilitários ──────────────────────────────────────────────────────────────
function esc(str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(str || ''));
    return d.innerHTML;
}

const ARCHIVE_PALETTE = ['4F46E5','0891B2','059669','B45309','7C3AED','0284C7','BE185D','0F766E'];

function archiveInitials(name) {
    if (name === null || name === undefined) return '?';
    const normalized = String(name).trim();
    if (!normalized) return '?';
    if (normalized === '—') return '?';
    const parts = normalized.split(/[\s.]+/);
    let ini = (parts[0] || '').charAt(0).toUpperCase();
    if (parts.length > 1) ini += (parts[parts.length - 1] || '').charAt(0).toUpperCase();
    return ini;
}

function archiveAvatarColor(name) {
    if (name === null || name === undefined) return '#6B7280';
    name = String(name);
    if (!name || name === '—') return '#6B7280';
    let h = 0;
    for (let i = 0; i < name.length; i++) h = (Math.imul(31, h) + name.charCodeAt(i)) | 0;
    return '#' + ARCHIVE_PALETTE[Math.abs(h) % ARCHIVE_PALETTE.length];
}

// ── Estado do modal ──────────────────────────────────────────────────────────
let _archiveNr = null;

function archiveModalState(state) {
    document.getElementById('archive-modal-loading').classList.toggle('hidden', state !== 'loading');
    document.getElementById('archive-modal-error').classList.toggle('hidden',   state !== 'error');
    document.getElementById('archive-modal-table').classList.toggle('hidden',   state !== 'table');
    document.getElementById('archive-modal-empty').classList.toggle('hidden',   state !== 'empty');

    // flex só quando visível
    document.getElementById('archive-modal-error').style.display = state === 'error' ? 'flex' : '';
}

function archiveModalDestroy() {
    if (window._msgDT) {
        try { window._msgDT.destroy(); } catch(e) {}
        window._msgDT = null;
    }
}

async function archiveModalLoad() {
    const nr = _archiveNr;
    if (!nr) return;

    archiveModalDestroy();
    document.getElementById('modal-msgs-body').innerHTML = '';
    archiveModalState('loading');

    try {
        const csrf = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        const res  = await fetch(`{{ url('/administracao/historico') }}/${nr}`, {
            headers: {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
            },
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const data  = await res.json();
        const msgs  = data.messages || [];
        const users = data.users    || {};

        // Atualiza header com dados reais do paciente
        if (data.patient_name) {
            document.getElementById('archive-modal-title').textContent = data.patient_name;
        }
        document.getElementById('archive-modal-subtitle').textContent =
            (data.total || msgs.length) + ' anotações · Atend. ' + nr;

        if (msgs.length === 0) {
            archiveModalState('empty');
            return;
        }

        document.getElementById('modal-msgs-body').innerHTML = msgs.map(msg => {
            const shiftClass = {
                'Manhã': 'bg-amber-50 text-amber-700',
                'Tarde': 'bg-sky-50 text-sky-700',
                'Noite': 'bg-indigo-50 text-indigo-700',
            }[msg.turno] || 'bg-gray-100 text-gray-500';
            const photo = users[msg.user] || null;
            const ini   = archiveInitials(msg.user);
            const color = archiveAvatarColor(msg.user);
            const avatar = photo
                ? `<img src="data:image/jpeg;base64,${photo}" alt="${esc(msg.user)}" class="w-6 h-6 rounded-full object-cover flex-shrink-0">`
                : `<div class="w-6 h-6 rounded-full text-white flex items-center justify-center text-[9px] font-bold flex-shrink-0" style="background:${color}">${ini}</div>`;
            return `<tr>
                <td class="px-2 py-2 whitespace-nowrap text-xs text-gray-500">${esc(msg.date)}</td>
                <td class="px-2 py-2">
                    <div class="flex items-center gap-1.5">
                        ${avatar}
                        <span class="text-xs font-medium text-gray-700 whitespace-nowrap">${esc(msg.user)}</span>
                    </div>
                </td>
                <td class="px-2 py-2">
                    <span class="text-[10px] font-medium px-1.5 py-0.5 rounded-full ${shiftClass}">${esc(msg.turno)}</span>
                </td>
                <td class="px-2 py-2 text-xs text-gray-600 max-w-xs">${esc(msg.text)}</td>
            </tr>`;
        }).join('');

        archiveModalState('table');

        try {
            window._msgDT = new DataTable('#modal-msgs-dt', {
                language: {
                    info: 'Mostrando _START_–_END_ de _TOTAL_',
                    infoEmpty: 'Nenhum registro',
                    zeroRecords: 'Nenhuma anotação encontrada',
                    paginate: { previous: '‹', next: '›', first: '«', last: '»' },
                },
                searching:  false,
                lengthChange: false,
                order:      [[0, 'asc']],
                pageLength: 15,
                columnDefs: [{ targets: 3, orderable: false }],
            });
        } catch(e) {
            console.warn('[ArchiveModal] DataTable init error:', e);
        }

    } catch (e) {
        console.error('[ArchiveModal] load error:', e);
        document.getElementById('archive-modal-error-msg').textContent = 'Não foi possível carregar as mensagens.';
        archiveModalState('error');
    }
}

// ── Fecha com Escape ─────────────────────────────────────────────────────────
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') closeModal('archive-modal');
});

// ── Clique no botão Ver (delegado) ───────────────────────────────────────────
document.addEventListener('click', function (e) {
    const btn = e.target.closest('[data-archive-nr]');
    if (!btn) return;

    _archiveNr = btn.dataset.archiveNr;
    const name = btn.dataset.archiveName || null;

    // Prepara header com dados disponíveis imediatamente
    document.getElementById('archive-modal-title').textContent    = name || ('Atendimento ' + _archiveNr);
    document.getElementById('archive-modal-subtitle').textContent = '';
    archiveModalState('loading');

    // Abre o modal exatamente como openModal faz nos modais de usuário
    openModal('archive-modal');

    // Busca dados
    archiveModalLoad();
});

// ── DataTable principal ──────────────────────────────────────────────────────
let archiveTable;

document.addEventListener('DOMContentLoaded', function () {
    archiveTable = new DataTable('#archive-table', {
        ajax: {
            url:  '{{ route("chat.archive.client-data") }}',
            type: 'GET',
            error: function (xhr, error, thrown) {
                console.error('DataTable ajax error:', error, thrown, xhr.responseText);
            },
        },
        processing: true,
        language: {
            url: '//cdn.datatables.net/plug-ins/2.2.2/i18n/pt-BR.json',
            processing: '<div class="text-xs text-gray-400 py-1">Carregando...</div>',
            search: 'Buscar:',
        },
        pageLength: 25,
        lengthMenu: [10, 25, 50, 100],
        order: [[7, 'desc']],
        columns: [
            { data: 'nr_atendimento', render: (v) => `<span class="font-mono font-semibold text-gray-700">${v}</span>` },
            { data: 'patient_name',   render: (v) => v ? `<span class="text-gray-700">${esc(v)}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'sector_name',    render: (v) => v ? `<span class="px-2 py-0.5 rounded bg-blue-50 text-santacasa-100 font-medium text-xs">${esc(v)}</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'dt_entrada',     render: (v) => v ? `<span class="text-gray-500 text-xs">${v}</span>` : '<span class="text-gray-300">—</span>' },
            {
                data: 'still_admitted',
                render: (v, type, row) => {
                    if (v === true)  return '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-green-50 text-green-700 text-[10px] font-medium border border-green-200"><span class="w-1.5 h-1.5 rounded-full bg-green-500 animate-pulse inline-block"></span>Internado</span>';
                    if (v === false) return `<span class="text-gray-500 text-xs">${row.dt_alta || '—'}</span>`;
                    return '<span class="text-gray-300">—</span>';
                },
            },
            { data: 'admission_days', render: (v) => v != null ? `<span class="${v >= 14 ? 'text-amber-600 font-semibold' : 'text-gray-500'} text-xs">${v}d</span>` : '<span class="text-gray-300">—</span>' },
            { data: 'message_count',  render: (v) => `<span class="text-xs text-gray-600 font-semibold">${v}</span>` },
            { data: 'last_message_at',render: (v) => v ? `<span class="text-xs text-gray-400">${v}</span>` : '—' },
            {
                data: 'nr_atendimento', orderable: false, searchable: false,
                render: (nr, type, row) => {
                    if (type !== 'display') return nr;
                    const safeName = (row.patient_name || '')
                        .replace(/&/g, '&amp;').replace(/"/g, '&quot;').replace(/'/g, '&#39;').replace(/\n/g, ' ').replace(/\r/g, '');
                    return `<button data-archive-nr="${nr}" data-archive-name="${safeName}"
                        class="text-xs text-santacasa-100 hover:text-santacasa-200 font-medium px-2 py-1 rounded hover:bg-blue-50 transition-colors">
                        <i class="fas fa-eye fa-xs"></i> Ver
                    </button>`;
                },
            },
            { data: 'last_message_raw', visible: false, searchable: false },
        ],
        rowCallback: function (row) {
            row.querySelectorAll('td').forEach(td => td.classList.add('px-3', 'py-2.5'));
        },
    });
});
</script>
@endpush
