@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null,
    'clinicalData' => [],
])

<div x-show="activeTab === 'tab-b'" class="p-2 sm:p-3">
    @if($loadingPatient)
        <div class="flex flex-col items-center justify-center py-12 sm:py-20">
            <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center">
                <i class="fas fa-spinner fa-3x animate-spin"></i>
            </span>
            <p class="text-gray-700 text-lg sm:text-xl">Carregando detalhes do paciente...</p>
        </div>
    @elseif($currentPatient && !$currentPatient['has_patient'])
        <!-- Empty Bed -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <x-healthicons-o-inpatient class="w-12 h-12 sm:w-16 sm:h-16 text-gray-400 mb-4 animate-pulse" />
            <p class="text-gray-700 text-base sm:text-lg">Leito Vago</p>
            <p class="text-gray-500 mt-2 text-sm sm:text-base">Este leito não possui paciente internado no momento.</p>
        </div>
    @elseif($patientDetails)
        <!-- Background - Contexto clínico relevante -->
        <div class="bg-white rounded-xl p-3 shadow-sm border border-gray-100">
            <h4 class="text-sm font-bold text-gray-800 border-b border-gray-200 pb-2 mb-3 flex items-center">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#007D44] text-white mr-2 text-xs font-bold flex-shrink-0">B</span>
                <div>
                    <span class="text-sm">BACKGROUND</span>
                    <p class="text-[10px] text-gray-500 font-normal mt-0.5">Qual o contexto clínico relevante?</p>
                </div>
            </h4>

            <div class="space-y-2">
                <!-- Diagnóstico e Comorbidades -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-red-500 pl-2 bg-red-50 py-1 rounded-r">
                        Diagnóstico e Comorbidades
                    </h5>
                    <div class="bg-gray-50 p-2 rounded-lg border">
                        @if(!empty($clinicalData['cid_history'] ?? []))
                            <div class="space-y-1">
                                @foreach($clinicalData['cid_history'] as $cid)
                                    @php $isInativo = ($cid['situacao'] ?? '') !== 'Ativo'; @endphp
                                    <div class="flex items-start gap-2 text-xs {{ $isInativo ? 'opacity-50' : '' }}">
                                        <span class="inline-block w-1.5 h-1.5 flex-shrink-0 mt-1 rounded-full {{ $isInativo ? 'bg-gray-400' : 'bg-red-400' }}"></span>
                                        <div class="flex-1 min-w-0 leading-tight">
                                            <span class="font-mono font-semibold text-indigo-700">{{ $cid['cd_cid'] ?? '' }}</span>
                                            <span class="text-gray-800 {{ $isInativo ? 'line-through decoration-gray-400' : '' }}"> {{ $cid['ds_cid'] ?? '' }}</span>
                                            @if(($cid['classificacao'] ?? '') === 'Principal')
                                                <span class="ml-1 text-[10px] text-blue-600 font-medium">(Principal)</span>
                                            @elseif(($cid['classificacao'] ?? '') === 'Secundário')
                                                <span class="ml-1 text-[10px] text-gray-400">(Secundário)</span>
                                            @endif
                                            @if($isInativo && !empty($cid['dt_inativacao']))
                                                <span class="ml-1 text-[10px] text-gray-400">· inativado {{ $cid['dt_inativacao'] }}</span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        @elseif(count($clinicalData['diagnosticos_list'] ?? []) > 1)
                            <div class="space-y-1">
                                @foreach(($clinicalData['diagnosticos_list'] ?? []) as $diag)
                                    <div class="flex items-center space-x-2 text-xs text-gray-800">
                                        <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-red-400 rounded-full"></span>
                                        <span class="font-medium">{{ $diag }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-800 font-medium">{{ $clinicalData['diagnosticos'] ?? 'Não informado' }}</p>
                        @endif
                    </div>
                </div>

                <!-- Dispositivos -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-orange-500 pl-2 bg-orange-50 py-1 rounded-r">
                        Dispositivos em Uso
                    </h5>
                    <div class="bg-gray-50 p-2 rounded-lg border">
                        @if(count($clinicalData['dispositivos_list'] ?? []) > 1)
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-1.5">
                                @foreach(($clinicalData['dispositivos_list'] ?? []) as $dispositivo)
                                    <div class="flex items-center space-x-2 text-xs text-gray-800">
                                        <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-orange-400 rounded-full"></span>
                                        <span class="font-medium">{{ trim($dispositivo) }}</span>
                                    </div>
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-800 font-medium">{{ $clinicalData['dispositivos'] ?? 'Nenhum dispositivo' }}</p>
                        @endif
                    </div>
                </div>

                <!-- Alergias -->
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-1.5 border-l-4 border-red-500 pl-2 bg-red-50 py-1 rounded-r flex items-center">
                        <x-heroicon-o-exclamation-triangle class="h-3 w-3 mr-1.5 text-red-600 flex-shrink-0" />
                        Alergias Conhecidas
                    </h5>
                    <div class="bg-gray-50 p-2 rounded-lg border">
                        @if(!empty($clinicalData['alergias_items'] ?? []))
                            <div class="flex flex-wrap items-center gap-x-3 gap-y-1">
                                @foreach(($clinicalData['alergias_items'] ?? []) as $alergia)
                                    @if(!empty($alergia['med'] ?? null))
                                        <div class="flex items-center space-x-1.5 text-xs text-gray-800">
                                            <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-red-400 rounded-full"></span>
                                            <span class="font-medium">{{ $alergia['med'] }}</span>
                                            @if(!empty($alergia['grav']))
                                                <span class="text-gray-500">{{ $alergia['grav'] }}</span>
                                            @endif
                                        </div>
                                    @elseif(!empty($alergia['text'] ?? null))
                                        <div class="flex items-center space-x-1.5 text-xs text-gray-800">
                                            <span class="inline-block w-1.5 h-1.5 flex-shrink-0 bg-red-400 rounded-full"></span>
                                            <span class="font-medium">{{ $alergia['text'] }}</span>
                                        </div>
                                    @endif
                                @endforeach
                            </div>
                        @else
                            <p class="text-xs text-gray-800 font-medium">{{ $clinicalData['alergias'] ?? 'Nenhuma alergia registrada' }}</p>
                        @endif
                    </div>
                </div>

                <!-- Hemoculturas -->
                @php
                    $bgHemocHistory = $currentPatient['hemoc_history'] ?? [];
                    $bgHemocEvents  = collect($currentPatient['pending_events'] ?? [])
                        ->filter(fn($e) => in_array($e['tipo'] ?? '', ['exame', 'proc_exame'], true)
                            && str_contains(strtoupper($e['descricao'] ?? ''), 'HEMOCULTURA'))
                        ->values()
                        ->all();

                    $bgActivePrescr = collect($bgHemocEvents)
                        ->pluck('nr_prescricao')->map('strval')->filter()->values()->all();
                    $bgHemocHistFiltered = collect($bgHemocHistory)
                        ->reject(fn($h) => in_array((string)($h['nr_prescricao'] ?? ''), $bgActivePrescr, true))
                        ->values()->all();

                    $bgSortKey = function (string $s): string {
                        if (preg_match('/^(\d{4})-(\d{2})-(\d{2})/', $s)) {
                            return str_replace(['-', ' ', ':'], '', $s);
                        }
                        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{2,4})/', $s, $m)) {
                            $year = strlen($m[3]) === 2 ? '20'.$m[3] : $m[3];
                            return $year . $m[2] . $m[1] . preg_replace('/[^0-9]/', '', substr($s, 8));
                        }
                        return '0';
                    };

                    $bgAllItems = [];
                    foreach ($bgHemocEvents as $ev) {
                        $bgAllItems[] = ['type' => 'active', 'sort' => $bgSortKey((string) ($ev['dt_evento'] ?? $ev['dt_solicitacao'] ?? $ev['dt_coleta'] ?? '')), 'data' => $ev];
                    }
                    foreach ($bgHemocHistFiltered as $hist) {
                        $bgAllItems[] = ['type' => 'history', 'sort' => $bgSortKey((string) (($hist['dt_prescricao'] ?? '') ?: ($hist['date'] ?? ''))), 'data' => $hist];
                    }
                    usort($bgAllItems, fn($a, $b) => strcmp($b['sort'], $a['sort']));

                    $bgTotalCount  = count($bgAllItems);
                    $bgDisplayItems = array_slice($bgAllItems, 0, 5);
                    $bgHasHemoc    = !empty($bgAllItems) || !empty($patientDetails->ultima_hemocultura ?? null);
                @endphp
                <div>
                    <h5 class="text-xs font-semibold text-gray-700 mb-2 border-l-4 border-santacasa-100 pl-2 bg-santacasa-100/5 py-1 rounded-r flex items-center justify-between gap-1.5">
                        <span class="flex items-center gap-1.5">
                            <i class="fas fa-vials text-santacasa-100" style="font-size:11px;"></i>
                            Hemoculturas
                        </span>
                        @if($bgTotalCount > 0)
                            <span class="text-[10px] font-medium text-gray-400 pr-1">
                                {{ $bgTotalCount > 5 ? '5 de '.$bgTotalCount : $bgTotalCount }} registro{{ $bgTotalCount !== 1 ? 's' : '' }}
                            </span>
                        @endif
                    </h5>

                    @if(!$bgHasHemoc)
                        <div class="flex flex-col items-center justify-center py-5 text-center">
                            <i class="fas fa-vials text-2xl text-gray-200 mb-2"></i>
                            <p class="text-xs text-gray-400">Nenhuma hemocultura registrada.</p>
                        </div>
                    @else
                        <div class="space-y-1.5">
                            @foreach($bgDisplayItems as $bgItem)
                                @php
                                    if ($bgItem['type'] === 'active') {
                                        $bgEv      = $bgItem['data'];
                                        $bgRes     = $bgEv['scola_resultado'] ?? null;
                                        $bgScola   = $bgEv['scola_status'] ?? null;
                                        $bgIsPos   = str_starts_with($bgRes ?? '', 'Positivo');

                                        if ($bgIsPos)                                              { $bgBadge = $bgRes;                  $bgBadgeCls = 'bg-red-100 text-red-700'; }
                                        elseif ($bgRes === 'Negativo')                             { $bgBadge = 'Negativo';              $bgBadgeCls = 'bg-emerald-100 text-emerald-700'; }
                                        elseif ($bgRes !== null)                                   { $bgBadge = $bgRes;                  $bgBadgeCls = 'bg-amber-100 text-amber-700'; }
                                        elseif ($bgScola && str_contains($bgScola,'Laudo liberado')){ $bgBadge = 'Laudo liberado';        $bgBadgeCls = 'bg-santacasa-100/10 text-santacasa-200'; }
                                        elseif ($bgScola === 'Coletado (aguardando resultado)' || $bgScola === 'Resultado inserido (aguardando laudo)') { $bgBadge = 'Coletado · aguard. laudo'; $bgBadgeCls = 'bg-amber-100 text-amber-700'; }
                                        elseif ($bgScola !== null)                                 { $bgBadge = $bgScola;                $bgBadgeCls = 'bg-gray-100 text-gray-600'; }
                                        else                                                       { $bgBadge = 'Aguardando coleta';    $bgBadgeCls = 'bg-gray-100 text-gray-500'; }

                                        $bgTasyCode = (string) ($bgEv['ie_status_execucao'] ?? '');
                                        $bgTasyLabel = match($bgTasyCode) { '10' => 'Solicitado', '15' => 'Em exame', '20' => 'Executado', default => $bgTasyCode ?: 'Pendente' };

                                        if ($bgRes !== null)                                                { $bgScolaLabel = $bgRes; }
                                        elseif ($bgScola && str_contains($bgScola,'Laudo liberado'))        { $bgScolaLabel = 'Laudo liberado (aguard. TASY)'; }
                                        elseif ($bgScola === 'Resultado inserido (aguardando laudo)')       { $bgScolaLabel = 'Resultado inserido, aguard. laudo'; }
                                        elseif ($bgScola === 'Coletado (aguardando resultado)')             { $bgScolaLabel = 'Coletado, aguard. resultado'; }
                                        elseif ($bgScola === 'Nova coleta necessária')                      { $bgScolaLabel = 'Nova coleta necessária'; }
                                        elseif ($bgScola === 'Solicitado (aguardando coleta)')              { $bgScolaLabel = 'Aguardando coleta'; }
                                        elseif ($bgScola !== null)                                          { $bgScolaLabel = $bgScola; }
                                        else                                                                { $bgScolaLabel = 'Sem info SCOLA'; }

                                        $bgName       = ucwords(strtolower($bgEv['descricao'] ?? 'Hemocultura'));
                                        $bgNrPrescr   = $bgEv['nr_prescricao'] ?? null;
                                        $bgDtSolic    = $bgEv['dt_solicitacao'] ?? null;
                                        $bgDtColeta   = $bgEv['dt_coleta'] ?? null;
                                        $bgDtResult   = $bgEv['dt_resultado'] ?? null;
                                        $bgAmostra    = $bgEv['ie_amostra'] ?? null;
                                        $bgPrescritor = $bgEv['nm_prescritor'] ?? null;
                                        $bgTempoPend  = $bgEv['tempo_pendente'] ?? null;
                                        $bgSetorExec  = $bgEv['setor_execucao'] ?? null;
                                        $bgIsActive   = true;
                                    } else {
                                        $bgHist    = $bgItem['data'];
                                        $bgRes     = $bgHist['scola_resultado'] ?? null;
                                        $bgIsPos   = str_starts_with($bgRes ?? '', 'Positivo');

                                        if ($bgIsPos)                          { $bgBadge = $bgRes;              $bgBadgeCls = 'bg-red-100 text-red-700'; }
                                        elseif ($bgRes === 'Negativo')         { $bgBadge = 'Negativo';          $bgBadgeCls = 'bg-emerald-100 text-emerald-700'; }
                                        elseif ($bgRes !== null)               { $bgBadge = $bgRes;              $bgBadgeCls = 'bg-santacasa-100/10 text-santacasa-200'; }
                                        elseif ($bgHist['has_result'] ?? false){ $bgBadge = 'Laudo integrado';   $bgBadgeCls = 'bg-santacasa-100/10 text-santacasa-200'; }
                                        else                                   { $bgBadge = 'Realizada';         $bgBadgeCls = 'bg-gray-100 text-gray-500'; }

                                        $bgTasyCode  = (string) ($bgHist['status_execucao'] ?? '');
                                        $bgTasyLabel = match($bgTasyCode) { '10' => 'Solicitado', '15' => 'Em exame', '20' => 'Executado', default => $bgTasyCode ?: '—' };

                                        if ($bgRes !== null)                     { $bgScolaLabel = $bgRes; }
                                        elseif ($bgHist['has_result'] ?? false)  { $bgScolaLabel = 'Laudo integrado ao TASY'; }
                                        else                                     { $bgScolaLabel = 'Sem resultado SCOLA'; }

                                        $bgName       = ucwords(strtolower($bgHist['name'] ?: 'Hemocultura'));
                                        $bgNrPrescr   = $bgHist['nr_prescricao'] ?? null;
                                        $bgDtSolic    = ($bgHist['dt_prescricao'] ?? '') ?: ($bgHist['date'] ?? null);
                                        $bgDtColeta   = null;
                                        $bgDtResult   = null;
                                        $bgAmostra    = null;
                                        $bgPrescritor = null;
                                        $bgTempoPend  = null;
                                        $bgSetorExec  = null;
                                        $bgIsActive   = false;
                                    }
                                @endphp

                                <div class="bg-gray-50 rounded-lg border border-gray-200">
                                    <div class="flex items-start gap-2 p-2">
                                        <i class="fas {{ $bgIsActive ? 'fa-vials text-santacasa-100' : 'fa-circle-check text-emerald-500' }} flex-shrink-0 mt-0.5" style="font-size:11px;"></i>
                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-xs font-medium text-gray-800">{{ $bgName }}</span>
                                                @if($bgNrPrescr)
                                                    <span class="text-[10px] text-gray-400 font-mono">#{{ $bgNrPrescr }}</span>
                                                @endif
                                                @if(!$bgIsActive)
                                                    <span class="text-[9px] px-1 py-px rounded bg-gray-200 text-gray-500">Histórico</span>
                                                @endif
                                                <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ $bgBadgeCls }}">{{ $bgBadge }}</span>
                                            </div>
                                            <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1 text-[10px] text-gray-500">
                                                <span>
                                                    <span class="font-medium text-gray-600">Tasy:</span> {{ $bgTasyLabel }}
                                                </span>
                                                <span>
                                                    <span class="font-medium text-gray-600">Scola:</span> {{ $bgScolaLabel }}
                                                </span>
                                                @if($bgDtSolic)
                                                    <span>
                                                        <span class="font-medium text-gray-600">{{ $bgIsActive ? 'Solicitado:' : 'Prescrição:' }}</span>
                                                        <span class="font-mono">{{ $bgDtSolic }}</span>
                                                    </span>
                                                @endif
                                                @if($bgDtColeta)
                                                    <span>
                                                        <span class="font-medium text-gray-600">Coletado:</span>
                                                        <span class="font-mono">{{ $bgDtColeta }}</span>
                                                    </span>
                                                @endif
                                                @if($bgDtResult)
                                                    <span>
                                                        <span class="font-medium text-gray-600">Resultado:</span>
                                                        <span class="font-mono">{{ $bgDtResult }}</span>
                                                    </span>
                                                @endif
                                                @if($bgAmostra)
                                                    <span>
                                                        <span class="font-medium text-gray-600">Amostra:</span> {{ $bgAmostra }}
                                                    </span>
                                                @endif
                                                @if($bgPrescritor)
                                                    <span>
                                                        <span class="font-medium text-gray-600">Prescritor:</span> {{ $bgPrescritor }}
                                                    </span>
                                                @endif
                                                @if($bgSetorExec)
                                                    <span>
                                                        <span class="font-medium text-gray-600">Exec.:</span> {{ $bgSetorExec }}
                                                    </span>
                                                @endif
                                                @if($bgTempoPend && $bgIsActive)
                                                    <span class="text-amber-700 font-medium">{{ $bgTempoPend }}</span>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            @if($bgTotalCount > 5)
                                <p class="text-center text-[10px] text-gray-400 pt-0.5">
                                    + {{ $bgTotalCount - 5 }} registro{{ ($bgTotalCount - 5) > 1 ? 's' : '' }} mais antigo{{ ($bgTotalCount - 5) > 1 ? 's' : '' }} não exibido{{ ($bgTotalCount - 5) > 1 ? 's' : '' }}
                                </p>
                            @endif

                            @if(empty($bgAllItems) && !empty($patientDetails->ultima_hemocultura ?? null))
                                <div class="rounded-lg border border-gray-100 bg-white px-3 py-2.5 flex items-center gap-2">
                                    <i class="fas fa-circle-check text-emerald-500 flex-shrink-0" style="font-size:12px;"></i>
                                    <span class="text-xs text-gray-600">Última coleta:</span>
                                    <span class="text-xs font-mono font-semibold text-gray-800">{{ $patientDetails->ultima_hemocultura }}</span>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>
            </div>
        </div>
    @else
        <!-- Error State -->
        <div class="flex flex-col items-center justify-center py-8 sm:py-12 text-gray-700">
            <x-heroicon-o-exclamation-triangle class="w-12 h-12 sm:w-16 sm:h-16 text-red-500 mb-4" />
            <p class="text-gray-700 text-base sm:text-lg">Erro ao carregar detalhes do paciente</p>
            
            <button 
                wire:click="showPatientDetails('{{ $currentPatient['nr_atendimento'] ?? '' }}')"
                class="mt-4 px-4 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition-colors text-sm"
            >
                Tentar novamente
            </button>
        </div>
    @endif
</div>
