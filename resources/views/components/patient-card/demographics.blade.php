@props(['patient', 'showAdminData' => false, 'showScales' => false])

{{-- Row 2: Patient Name + Gender + Age --}}
<div class="bg-white/70 rounded-lg px-2 py-1 md:py-1.5 shadow-sm">
    <div class="flex items-center justify-center gap-1.5">
        @if(($patient['sexo'] ?? '') === 'F')
            <x-iconoir-female class="text-pink-600 h-3.5 w-3.5 md:h-4 md:w-4 flex-shrink-0" />
        @elseif(($patient['sexo'] ?? '') === 'M')
            <x-iconoir-male class="text-blue-600 h-3.5 w-3.5 md:h-4 md:w-4 flex-shrink-0" />
        @endif
        <p class="text-gray-900 text-xs md:text-sm font-bold truncate min-w-0">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
        <span class="text-gray-700 text-[11px] md:text-xs font-semibold flex-shrink-0">{{ $patient['age'] ?? '?' }}a</span>
        <span class="text-gray-500 text-[10px] md:text-[11px] font-medium flex-shrink-0">({{ $patient['birth_date'] ?? '?' }})</span>
    </div>
</div>

@if($showAdminData)
{{-- Row 3: Administrative Data grid --}}
@php
    $prevAltaRaw = $patient['discharge_info']['dt_previsto_alta_formatted'] ?? null;
    $hasPrevAlta = in_array(($patient['discharge_display']['type'] ?? ''), ['previsao_alta', 'alta_medica']) && !empty($prevAltaRaw);
    $avalEnf = $patient['avaliacao_enf'] ?? null;
    $avalEnfOk = $avalEnf && $avalEnf !== 'Não realizada';
    $avalEnfDisplay = $avalEnfOk ? Str::before($avalEnf, ' ') : null;
    $peData = $patient['pe_data'] ?? null;
    $peOk = $peData && $peData !== 'Não realizado';
    $hemocDate = $patient['ultima_hemocultura'] ?? null;
    $hemocPend = $patient['hemocultura_pendente'] ?? false;
    $medicoRaw = $patient['medico_responsavel'] ?? null;
    $medicoParts = $medicoRaw ? array_values(array_filter(explode(' ', $medicoRaw))) : [];
    $medicoAbrev = count($medicoParts) >= 2
        ? $medicoParts[0] . ' ' . $medicoParts[count($medicoParts) - 1]
        : ($medicoRaw ?? null);
@endphp
<div class="bg-white/70 rounded-lg px-2 md:px-3 py-1 md:py-1.5 shadow-sm space-y-1">

    {{-- Linha 1: Nº atendimento | Internação | Convênio --}}
    <div class="flex items-center gap-2 text-[10px] md:text-xs lg:text-[10px] leading-tight">
        <div class="flex items-center gap-0.5 min-w-0">
            <span class="text-gray-400 shrink-0">Nº</span>
            <span class="text-gray-900 font-semibold tabular-nums">{{ $patient['nr_atendimento'] ?? '—' }}</span>
        </div>
        <span class="text-gray-200 shrink-0">·</span>
        <div class="flex items-center gap-0.5 shrink-0">
            <span class="text-gray-400">Int:</span>
            @if($patient['is_new_patient'] ?? false)
                <span class="text-green-700 font-semibold">Admissão hoje</span>
            @elseif(isset($patient['internment_days']) && $patient['internment_days'] !== null)
                <span class="text-gray-800 font-semibold">{{ ceil($patient['internment_days']) }}d</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </div>
        <span class="text-gray-200 shrink-0">·</span>
        <div class="flex items-center gap-0.5 min-w-0 overflow-hidden">
            <span class="text-gray-400 shrink-0">Conv:</span>
            <span class="text-gray-800 font-medium truncate">{{ $patient['convenio_short'] ?? '—' }}</span>
        </div>
    </div>

    {{-- Linha 2: Prev. Alta + Médico --}}
    <div class="flex items-center gap-1.5 text-[10px] md:text-xs lg:text-[10px] leading-tight min-w-0">
        @if($hasPrevAlta)
            <x-heroicon-s-arrow-right-start-on-rectangle class="w-3 h-3 text-orange-500 shrink-0" />
            <span class="text-orange-600 font-medium shrink-0">Prv.Alta:</span>
            <span class="text-orange-700 font-bold shrink-0">{{ $prevAltaRaw }}</span>
        @else
            <span class="text-gray-400 shrink-0">Prv.Alta: —</span>
        @endif
        <span class="text-gray-200 shrink-0">·</span>
        <span class="text-gray-400 shrink-0">Dr:</span>
        @if($medicoAbrev)
            <span class="text-gray-800 font-medium truncate min-w-0">{{ $medicoAbrev }}</span>
        @else
            <span class="text-gray-400">—</span>
        @endif
    </div>

    {{-- Linha 4: Avaliação enfermagem + PE --}}
    <div class="flex items-center gap-2 text-[10px] md:text-xs lg:text-[10px] leading-tight">
        <div class="flex items-center gap-0.5 shrink-0">
            <span class="text-gray-400">Enf:</span>
            @if($avalEnfOk)
                <span class="text-gray-800 font-medium">{{ $avalEnfDisplay }}</span>
            @else
                <span class="text-gray-400">N/A</span>
            @endif
        </div>
        <span class="text-gray-200 shrink-0">·</span>
        <div class="flex items-center gap-0.5 shrink-0">
            <span class="text-gray-400">Educ:</span>
            @if($peOk)
                <span class="text-gray-800 font-medium">{{ $peData }}</span>
            @else
                <span class="text-gray-400">N/A</span>
            @endif
        </div>
    </div>

</div>

{{-- Hemocultura: card clicável (cor única santacasa) --}}
@php
    $hemocEvents = collect($patient['pending_events'] ?? [])
        ->filter(fn($e) => in_array($e['tipo'] ?? '', ['exame', 'proc_exame'], true)
            && str_contains(strtoupper($e['descricao'] ?? ''), 'HEMOCULTURA'))
        ->values()
        ->all();
    $hemocHistory = $patient['hemoc_history'] ?? [];
    $hemocActiveCount = count($hemocEvents);

    // Prescrições já exibidas nos eventos ativos → excluir do histórico (sem duplicação)
    $activePrescricoes = collect($hemocEvents)
        ->pluck('nr_prescricao')
        ->map('strval')
        ->filter()
        ->values()
        ->all();
    $hemocHistoryFiltered = collect($hemocHistory)
        ->reject(fn($h) => in_array((string)($h['nr_prescricao'] ?? ''), $activePrescricoes, true))
        ->values()
        ->all();

    // Normaliza ISO ou DD/MM/YY para chave ordenável YYYYMMDDHHMMSS
    $hemocSortKey = function (string $s): string {
        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s)) {
            return str_replace(['-', ' ', ':'], '', $s);
        }
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2,4})/', $s, $m)) {
            $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];
            return $year . $m[2] . $m[1] . preg_replace('/[^0-9]/', '', substr($s, 8));
        }
        return '0';
    };

    // Lista unificada: ativos + histórico, ordenada por data da prescrição desc
    $hemocAllItems = [];
    foreach ($hemocEvents as $ev) {
        // dt_evento é ISO raw — usa como preferência sobre dt_solicitacao formatado
        $sortDate = $hemocSortKey((string) ($ev['dt_evento'] ?? $ev['dt_solicitacao'] ?? $ev['dt_coleta'] ?? ''));
        $hemocAllItems[] = ['type' => 'active', 'sort' => $sortDate, 'data' => $ev];
    }
    foreach ($hemocHistoryFiltered as $hist) {
        $sortDate = $hemocSortKey(($hist['dt_prescricao'] ?? '') ?: ($hist['date'] ?? ''));
        $hemocAllItems[] = ['type' => 'history', 'sort' => $sortDate, 'data' => $hist];
    }
    usort($hemocAllItems, fn($a, $b) => strcmp($b['sort'], $a['sort']));

    // Não renderizar o card se não há nenhum dado de hemocultura
    $hemocTemDados = $hemocDate !== null || $hemocActiveCount > 0 || !empty($hemocHistory);

    // Badge do card: status da hemocultura mais recente (não contagem)
    // Fonte primária: hemocHistory[0] (mais recente com coleta)
    // Fonte secundária: primeiro evento ativo se não há histórico
    $hemocCardBadge = null;
    $hemocCardBadgeStyle = 'bg-gray-100 text-gray-600';

    $latestResult    = $hemocHistory[0]['scola_resultado'] ?? null;
    $latestHasResult = $hemocHistory[0]['has_result'] ?? false;
    $latestTasyCode  = $hemocHistory[0]['status_execucao'] ?? null;

    // Se não há histórico, tenta o evento ativo mais recente
    if (empty($hemocHistory) && !empty($hemocEvents)) {
        $latestResult    = $hemocEvents[0]['scola_resultado'] ?? null;
        $latestHasResult = false;
        $latestTasyCode  = null;
        $latestScolaStatus = $hemocEvents[0]['scola_status'] ?? null;
    } else {
        $latestScolaStatus = null;
    }

    if ($latestResult === 'Laudo integrado') {
        $hemocCardBadge      = 'Laudo integrado';
        $hemocCardBadgeStyle = 'bg-santacasa-100/10 text-santacasa-200';
    } elseif ($latestResult === 'Laudo liberado') {
        $hemocCardBadge      = 'Laudo liberado';
        $hemocCardBadgeStyle = 'bg-emerald-100 text-emerald-700';
    } elseif ($latestResult === 'Resultado em análise') {
        $hemocCardBadge      = 'Resultado em análise';
        $hemocCardBadgeStyle = 'bg-amber-100 text-amber-700';
    } elseif ($latestResult === 'Coletado') {
        $hemocCardBadge      = 'Coletado · aguard. laudo';
        $hemocCardBadgeStyle = 'bg-amber-100 text-amber-700';
    } elseif ($latestHasResult) {
        $hemocCardBadge      = 'Laudo integrado';
        $hemocCardBadgeStyle = 'bg-santacasa-100/10 text-santacasa-200';
    } elseif ($latestScolaStatus !== null && str_contains($latestScolaStatus, 'Laudo liberado')) {
        $hemocCardBadge      = 'Laudo liberado';
        $hemocCardBadgeStyle = 'bg-emerald-100 text-emerald-700';
    } elseif ($latestScolaStatus === 'Coletado (aguardando resultado)' || $latestScolaStatus === 'Resultado inserido (aguardando laudo)') {
        $hemocCardBadge      = 'Coletado · aguard. laudo';
        $hemocCardBadgeStyle = 'bg-amber-100 text-amber-700';
    } elseif ($latestTasyCode === '20') {
        $hemocCardBadge      = 'Executado';
        $hemocCardBadgeStyle = 'bg-gray-100 text-gray-600';
    } elseif ($hemocPend || $hemocActiveCount > 0) {
        $hemocCardBadge      = 'Aguard. coleta';
        $hemocCardBadgeStyle = 'bg-santacasa-100/10 text-santacasa-200';
    }
@endphp
@if($hemocTemDados)
<div x-data="{ showHemocModal: false }">
    <div class="rounded-lg px-2 md:px-3 py-1 md:py-1.5 lg:py-1 shadow-sm border-l-[3px] border-l-santacasa-100 bg-white/70 cursor-pointer hover:bg-white/90 transition-all"
         @click="showHemocModal = true; document.body.style.overflow = 'hidden'">
        <div class="flex items-center justify-between gap-1.5">
            @php
                // Prioriza evento ativo (pendente) sobre histórico para exibir no card
                $cardNrPrescricao = $hemocEvents[0]['nr_prescricao']
                    ?? $hemocHistory[0]['nr_prescricao']
                    ?? null;
                $cardDate = $hemocActiveCount > 0
                    ? ($hemocEvents[0]['dt_solicitacao'] ?? $hemocEvents[0]['dt_evento_formatted'] ?? null)
                    : $hemocDate;
            @endphp
            <div class="flex items-center gap-1.5 min-w-0">
                <i class="fas fa-vials text-[10px] md:text-xs lg:text-[10px] text-santacasa-100 flex-shrink-0"></i>
                <span class="text-[9px] md:text-[11px] lg:text-[9px] font-semibold text-santacasa-200">Hemocultura</span>
                @if(!empty($cardNrPrescricao))
                    <span class="text-[9px] md:text-[10px] lg:text-[9px] text-gray-400 font-mono">#{{ $cardNrPrescricao }}</span>
                @endif
                @if($cardDate)
                    <span class="text-[9px] md:text-[10px] lg:text-[9px] text-gray-500">{{ $cardDate }}</span>
                @endif
            </div>
            @if($hemocCardBadge)
                <span class="text-[9px] md:text-[10px] lg:text-[9px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 {{ $hemocCardBadgeStyle }} whitespace-nowrap">
                    {{ $hemocCardBadge }}
                </span>
            @endif
        </div>
    </div>

    {{-- Modal de Hemoculturas --}}
    <div x-show="showHemocModal"
         x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-0 sm:p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="showHemocModal = false; document.body.style.overflow = ''">
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showHemocModal = false; document.body.style.overflow = ''"></div>

        <div class="relative w-full h-full sm:w-[620px] sm:h-auto sm:max-w-[95vw] sm:max-h-[90vh] bg-white rounded-none sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4">

            {{-- Header --}}
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-santacasa-200 to-santacasa-100 flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <i class="fas fa-vials text-white text-base flex-shrink-0"></i>
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-white leading-tight">Hemoculturas</h3>
                        <p class="text-white/70 text-xs leading-tight truncate">{{ $patient['nm_pessoa_fisica'] ?? '' }}</p>
                    </div>
                </div>
                <button @click="showHemocModal = false; document.body.style.overflow = ''"
                        title="Fechar"
                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>

            {{-- Body: lista unificada ordenada por data de prescrição --}}
            <div class="flex-1 overflow-y-auto min-h-0 p-3 space-y-1">

                @foreach($hemocAllItems as $item)
                    @if($item['type'] === 'active')
                        @php
                            $hemocEv = $item['data'];
                            $evScola = $hemocEv['scola_status'] ?? null;
                            $evRes   = $hemocEv['scola_resultado'] ?? null;
                            $evIsPos = str_starts_with($evRes ?? '', 'Positivo');
                            if ($evIsPos) { $evBadgeText = $evRes; $evBadgeCls = 'bg-red-100 text-red-700'; }
                            elseif ($evRes === 'Negativo') { $evBadgeText = 'Negativo'; $evBadgeCls = 'bg-emerald-100 text-emerald-700'; }
                            elseif ($evRes !== null) { $evBadgeText = $evRes; $evBadgeCls = 'bg-amber-100 text-amber-700'; }
                            elseif ($evScola !== null && str_contains($evScola, 'Laudo liberado')) { $evBadgeText = 'Laudo liberado'; $evBadgeCls = 'bg-santacasa-100/20 text-santacasa-200'; }
                            elseif ($evScola !== null) { $evBadgeText = $evScola; $evBadgeCls = 'bg-gray-100 text-gray-600'; }
                            else { $evBadgeText = 'Aguardando coleta'; $evBadgeCls = 'bg-gray-100 text-gray-500'; }

                            $evDate = $hemocEv['dt_solicitacao'] ?? $hemocEv['dt_coleta'] ?? null;

                            $evTasyCode = (string) ($hemocEv['ie_status_execucao'] ?? '');
                            if ($evTasyCode === '10') { $evTasyLabel = 'Solicitado'; }
                            elseif ($evTasyCode === '15') { $evTasyLabel = 'Em exame'; }
                            elseif ($evTasyCode === '20') { $evTasyLabel = 'Executado'; }
                            else { $evTasyLabel = $evTasyCode ?: 'Pendente'; }

                            if ($evRes !== null) { $evScolaLabel = $evRes; }
                            elseif ($evScola !== null && str_contains($evScola, 'Laudo liberado')) { $evScolaLabel = 'Laudo liberado (aguard. TASY)'; }
                            elseif ($evScola === 'Resultado inserido (aguardando laudo)') { $evScolaLabel = 'Resultado inserido, aguard. laudo'; }
                            elseif ($evScola === 'Coletado (aguardando resultado)') { $evScolaLabel = 'Coletado, aguard. resultado'; }
                            elseif ($evScola === 'Nova coleta necessária') { $evScolaLabel = 'Nova coleta necessária'; }
                            elseif ($evScola === 'Solicitado (aguardando coleta)') { $evScolaLabel = 'Aguardando coleta'; }
                            elseif ($evScola !== null) { $evScolaLabel = $evScola; }
                            else { $evScolaLabel = 'Sem info SCOLA'; }
                        @endphp
                        <div class="px-3 py-2 rounded-lg bg-white border border-gray-100 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <i class="fas fa-vials text-santacasa-100 flex-shrink-0" style="font-size:10px;"></i>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-[10px] font-semibold text-gray-700 truncate">
                                            {{ ucwords(strtolower($hemocEv['descricao'] ?? 'Hemocultura')) }}
                                        </span>
                                        @if(!empty($hemocEv['nr_prescricao']))
                                            <span class="text-[9px] text-gray-400 font-mono flex-shrink-0">#{{ $hemocEv['nr_prescricao'] }}</span>
                                        @endif
                                    </div>
                                    @if($evDate)<div class="font-mono text-[9px] text-gray-400">{{ $evDate }}</div>@endif
                                </div>
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap {{ $evBadgeCls }}">{{ $evBadgeText }}</span>
                            </div>
                            <div class="flex gap-x-3 mt-1 text-[9px] text-gray-500 pl-[22px]">
                                <span><span class="font-medium text-gray-600">TASY:</span> {{ $evTasyLabel }}</span>
                                <span><span class="font-medium text-gray-600">SCOLA:</span> {{ $evScolaLabel }}</span>
                            </div>
                        </div>
                    @else
                        @php
                            $hist = $item['data'];
                            $histRes   = $hist['scola_resultado'] ?? null;
                            $histIsPos = str_starts_with($histRes ?? '', 'Positivo');
                            if ($histIsPos) { $histBadgeText = $histRes; $histBadgeCls = 'bg-red-100 text-red-700'; }
                            elseif ($histRes === 'Negativo') { $histBadgeText = 'Negativo'; $histBadgeCls = 'bg-emerald-100 text-emerald-700'; }
                            elseif ($histRes !== null) { $histBadgeText = $histRes; $histBadgeCls = 'bg-santacasa-100/10 text-santacasa-200'; }
                            elseif ($hist['has_result'] ?? false) { $histBadgeText = 'Laudo integrado'; $histBadgeCls = 'bg-santacasa-100/10 text-santacasa-200'; }
                            else { $histBadgeText = 'Realizada'; $histBadgeCls = 'bg-gray-100 text-gray-600'; }

                            $histName = ucwords(strtolower($hist['name'] ?: 'Hemocultura'));
                            $histDate = ($hist['dt_prescricao'] ?? '') ?: ($hist['date'] ?? '');

                            $histTasyCode = (string) ($hist['status_execucao'] ?? '');
                            if ($histTasyCode === '10') { $histTasyLabel = 'Solicitado'; }
                            elseif ($histTasyCode === '15') { $histTasyLabel = 'Em exame'; }
                            elseif ($histTasyCode === '20') { $histTasyLabel = 'Executado'; }
                            else { $histTasyLabel = $histTasyCode ?: '—'; }

                            if ($histRes !== null) { $histScolaLabel = $histRes; }
                            elseif ($hist['has_result'] ?? false) { $histScolaLabel = 'Laudo integrado ao TASY'; }
                            else { $histScolaLabel = 'Sem resultado SCOLA'; }
                        @endphp
                        <div class="px-3 py-2 rounded-lg bg-white border border-gray-100 hover:bg-gray-50 transition-colors">
                            <div class="flex items-center gap-2.5">
                                <i class="fas fa-circle-check text-santacasa-100 flex-shrink-0" style="font-size:10px;"></i>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-1.5 min-w-0">
                                        <span class="text-[10px] font-semibold text-gray-700 truncate">{{ $histName }}</span>
                                        @if(!empty($hist['nr_prescricao']))
                                            <span class="text-[9px] text-gray-400 font-mono flex-shrink-0">#{{ $hist['nr_prescricao'] }}</span>
                                        @endif
                                    </div>
                                    <div class="font-mono text-[9px] text-gray-400">{{ $histDate }}</div>
                                </div>
                                <span class="text-[9px] font-semibold px-1.5 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap {{ $histBadgeCls }}">{{ $histBadgeText }}</span>
                            </div>
                            <div class="flex gap-x-3 mt-1 text-[9px] text-gray-500 pl-[22px]">
                                <span><span class="font-medium text-gray-600">TASY:</span> {{ $histTasyLabel }}</span>
                                <span><span class="font-medium text-gray-600">SCOLA:</span> {{ $histScolaLabel }}</span>
                            </div>
                        </div>
                    @endif
                @endforeach

                {{-- Estado vazio --}}
                @if(empty($hemocAllItems))
                    <div class="text-center py-8">
                        <i class="fas fa-vials text-3xl text-gray-300 mb-3"></i>
                        <p class="text-sm font-medium text-gray-400">
                            {{ $hemocDate ? 'Últ. coleta: ' . $hemocDate : 'Sem registros de hemoculturas' }}
                        </p>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endif
@endif

@if($showScales)
{{-- Row 4: Risk Scales (clickable → opens lightweight scales modal) --}}
<div x-data="{ showScalesModal: false }">
    <div
        class="bg-white/70 rounded-lg px-2 md:px-3 py-1 md:py-1.5 lg:py-1 shadow-sm cursor-pointer hover:bg-white/90 transition-colors"
        title="Ver escalas de avaliação"
        @click="showScalesModal = true; document.body.style.overflow = 'hidden'"
    >
        <div class="flex flex-wrap gap-1 justify-center items-center min-h-[18px]">
            @if($patient['has_patient'] ?? false)
                {{-- Braden --}}
                <span class="inline-flex items-center px-1.5 py-0.5 md:px-2 md:py-0.5 lg:px-1.5 rounded text-[10px] md:text-xs lg:text-[10px] border whitespace-nowrap relative
                    {{ $patient['braden_styling']['bg'] ?? 'bg-gray-50' }}
                    {{ $patient['braden_styling']['text'] ?? 'text-gray-800' }}
                    {{ $patient['braden_styling']['border'] ?? 'border-gray-300' }}
                    {{ ($patient['braden_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                    <strong>Braden:</strong>
                    <span class="ml-1">{{ $patient['braden_score'] ?? '-' }}</span>
                    @if($patient['braden_shift'] ?? null)
                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['braden_shift'] }})</span>
                    @endif
                    @if(($patient['braden_increased'] ?? false))
                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                    @endif
                </span>
                {{-- Morse --}}
                <span class="inline-flex items-center px-1.5 py-0.5 md:px-2 md:py-0.5 lg:px-1.5 rounded text-[10px] md:text-xs lg:text-[10px] border whitespace-nowrap relative
                    {{ $patient['morse_styling']['bg'] ?? 'bg-gray-50' }}
                    {{ $patient['morse_styling']['text'] ?? 'text-gray-800' }}
                    {{ $patient['morse_styling']['border'] ?? 'border-gray-300' }}
                    {{ ($patient['morse_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                    <strong>Morse:</strong>
                    <span class="ml-1">{{ $patient['morse_score'] ?? '-' }}</span>
                    @if($patient['morse_shift'] ?? null)
                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['morse_shift'] }})</span>
                    @endif
                    @if(($patient['morse_increased'] ?? false))
                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                    @endif
                </span>
                {{-- Pain --}}
                <span class="inline-flex items-center px-1.5 py-0.5 md:px-2 md:py-0.5 lg:px-1.5 rounded text-[10px] md:text-xs lg:text-[10px] border whitespace-nowrap relative
                    {{ $patient['pain_styling']['bg'] ?? 'bg-gray-50' }}
                    {{ $patient['pain_styling']['text'] ?? 'text-gray-800' }}
                    {{ $patient['pain_styling']['border'] ?? 'border-gray-300' }}
                    {{ ($patient['pain_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                    <strong>Dor:</strong>
                    <span class="ml-1">{{ $patient['pain_score'] ?? '-' }}</span>
                    @if($patient['pain_shift'] ?? null)
                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['pain_shift'] }})</span>
                    @endif
                    @if(($patient['pain_increased'] ?? false))
                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                    @endif
                </span>
                {{-- VTE --}}
                <span class="inline-flex items-center px-1.5 py-0.5 md:px-2 md:py-0.5 lg:px-1.5 rounded text-[10px] md:text-xs lg:text-[10px] border whitespace-nowrap relative
                    {{ $patient['vte_styling']['bg'] ?? 'bg-gray-50' }}
                    {{ $patient['vte_styling']['text'] ?? 'text-gray-800' }}
                    {{ $patient['vte_styling']['border'] ?? 'border-gray-300' }}
                    {{ ($patient['vte_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                    <strong>TEV:</strong>
                    <span class="ml-1">{{ $patient['vte_score'] ?? '-' }}</span>
                    @if($patient['vte_shift'] ?? null)
                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['vte_shift'] }})</span>
                    @endif
                    @if(($patient['vte_increased'] ?? false))
                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                    @endif
                </span>
            @else
                <span class="text-xs text-gray-400 italic">Escalas pendentes de avaliação</span>
            @endif
        </div>
    </div>

    {{-- Modal de Escalas (Alpine inline, sem Livewire) --}}
    <div x-show="showScalesModal"
         x-cloak
         class="fixed inset-0 z-[9999] flex items-center justify-center p-0 sm:p-4"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-150"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         @keydown.escape.window="showScalesModal = false; document.body.style.overflow = ''"
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
             @click="showScalesModal = false; document.body.style.overflow = ''"></div>
        <div class="relative w-full h-full sm:w-[680px] sm:h-auto sm:max-h-[90vh] bg-white rounded-none sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
             @click.stop
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-purple-700 to-purple-500 flex-shrink-0">
                <div class="flex items-center gap-2.5 min-w-0">
                    <div class="min-w-0">
                        <h3 class="text-base font-bold text-white leading-tight">Escalas de Avaliação</h3>
                        <p class="text-white/70 text-xs leading-tight truncate">{{ $patient['nm_pessoa_fisica'] ?? '' }}</p>
                    </div>
                </div>
                <button @click="showScalesModal = false; document.body.style.overflow = ''"
                        title="Fechar"
                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>
            <div class="flex-1 overflow-y-auto p-4">
                <x-ui.scales-display :data="$patient" />
            </div>
        </div>
    </div>
</div>
@endif
