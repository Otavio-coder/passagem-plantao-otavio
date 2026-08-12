@props([
    'patient',
    'currentHospitalName' => '',
    'sectorId' => 0,
])

@php
    $isPatient = (bool) ($patient['has_patient'] ?? false);

    $redCount = (int) ($patient['huddle_red_count'] ?? 0);
    $greenCount = (int) ($patient['huddle_green_count'] ?? 0);
    $edd = $patient['huddle_expected_discharge'] ?? null;

    // Exames pendentes em destaque (selo) e sequência de dias na mesma cor
    $examesPend = (int) ($patient['huddle_pending']['exames'] ?? 0);
    $streak = $patient['huddle_streak'] ?? null; // ['color' => 'red'|'green', 'days' => int]

    // Round automático: sem previsão de alta nas próximas 72h (vindo do Tasy)
    $isRound = ! ($patient['huddle_discharge_within_72h'] ?? false);
    $within24h = $patient['huddle_discharge_within_24h'] ?? false;

    // Cor do dia (Red2Green) — só se aplica a paciente do Huddle
    $isGreen = ($patient['huddle_color'] ?? 'red') === 'green';
    $isRed = ! $isRound && ! $isGreen;
    $borderClass = $isRound ? 'border-slate-300' : ($isGreen ? 'border-green-400' : 'border-red-400');

    // Médico abreviado (primeiro + último nome), como no SBAR
    $medicoRaw = $patient['medico_responsavel'] ?? null;
    $medicoParts = $medicoRaw ? array_values(array_filter(explode(' ', $medicoRaw))) : [];
    $medicoAbrev = count($medicoParts) >= 2
        ? $medicoParts[0] . ' ' . $medicoParts[count($medicoParts) - 1]
        : ($medicoRaw ?? null);

    // Pendências (checklist + Tasy)
    $signals = $patient['huddle_checklist'] ?? [];
    $pend = $patient['huddle_pending'] ?? [];
    $transporteTasy = $patient['huddle_transporte'] ?? null;
    $transporteSig = $transporteTasy === 'organizado' ? 'green' : ($transporteTasy === 'pendente' ? 'red' : null);
    $orientacaoTasy = $patient['huddle_orientacao'] ?? null;
    $orientacaoSig = $orientacaoTasy === 'feita' ? 'green' : ($orientacaoTasy === 'pendente' ? 'red' : null);

    $categories = [
        ['label' => 'Exames',        'item' => 'exames_laudo',    'count' => $pend['exames'] ?? null],
        ['label' => 'Procedimentos', 'item' => 'procedimentos',   'count' => $pend['procedimentos'] ?? null],
        ['label' => 'Multidisc.',    'item' => 'consultorias',    'count' => $pend['multidisciplinar'] ?? null],
        ['label' => 'Terapias',      'item' => 'terapias',        'count' => $pend['terapias'] ?? null],
        ['label' => 'Orientação',    'item' => 'orientacao_alta', 'count' => null, 'force' => $orientacaoSig],
        ['label' => 'Transporte',    'item' => 'transporte',      'count' => null, 'force' => $transporteSig],
        ['label' => 'Presc. alta',   'item' => null,              'count' => null, 'done' => $patient['huddle_prescricao_alta'] ?? false],
    ];
@endphp

<div class="relative w-full">
    <div class="flex flex-col rounded-xl shadow-lg overflow-hidden bg-white border-2 {{ $borderClass }} h-[430px] md:h-[520px] lg:h-[440px]">

        @if(! $isPatient)
            <x-huddle-card.empty-bed :patient="$patient" />
        @else
            <div class="flex flex-col h-full overflow-hidden">

                @php
                    $openPayload = [
                        'attendanceNumber' => (int) ($patient['nr_atendimento'] ?? 0),
                        'hospital' => $currentHospitalName,
                        'sectorId' => (int) $sectorId,
                        'sbarPatient' => [
                            'nr_atendimento' => $patient['nr_atendimento'] ?? 0,
                            'cd_unidade_basica' => $patient['cd_unidade_basica'] ?? null,
                            'nm_pessoa_fisica' => $patient['nm_pessoa_fisica'] ?? null,
                            'has_patient' => true,
                        ],
                        'patients' => [],
                    ];
                @endphp

                {{-- Cabeçalho: leito --}}
                <div class="flex-shrink-0 p-3 flex flex-col gap-2">
                    <div class="flex justify-between items-center gap-2">
                        <span class="inline-flex items-center bg-slate-100 text-slate-800 text-sm md:text-base font-bold px-3 py-1 rounded-full shadow-sm">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>
                    </div>

                    {{-- Nome + idade --}}
                    <div class="bg-slate-50 rounded-lg px-2 py-1.5 text-center">
                        <p class="text-gray-900 text-sm font-bold truncate">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
                        <p class="text-[11px] text-gray-500 mt-0.5">
                            {{ $patient['age_label'] ?? '—' }}
                            @if(! empty($patient['birth_date'])) <span class="text-gray-400">({{ $patient['birth_date'] }})</span> @endif
                        </p>
                    </div>

                    {{-- Grade administrativa (padrão SBAR) --}}
                    <div class="bg-slate-50 rounded-lg px-2.5 py-1.5 space-y-1 text-[11px] leading-tight">
                        <div class="flex items-center gap-2">
                            <span class="text-gray-400 shrink-0">Nº</span>
                            <span class="text-gray-900 font-semibold tabular-nums">{{ $patient['nr_atendimento'] ?? '—' }}</span>
                            <span class="text-gray-200">·</span>
                            <span class="text-gray-400">Int:</span>
                            <span class="text-gray-800 font-semibold">{{ isset($patient['internment_days']) ? ceil($patient['internment_days']).'d' : '—' }}</span>
                            <span class="text-gray-200">·</span>
                            <span class="text-gray-400">Conv:</span>
                            <span class="text-gray-800 font-medium truncate">{{ $patient['convenio'] ?? '—' }}</span>
                        </div>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <i class="fas fa-arrow-right-from-bracket text-[9px] {{ $edd ? 'text-orange-500' : 'text-gray-300' }}"></i>
                            <span class="{{ $edd ? 'text-orange-600' : 'text-gray-400' }} font-medium shrink-0">Prv.Alta:</span>
                            <span class="{{ $edd ? 'text-orange-700 font-bold' : 'text-gray-400' }} shrink-0">{{ $edd ?? '—' }}</span>
                            @if($within24h)
                                <span class="ml-auto px-1.5 py-0.5 rounded bg-blue-600 text-white text-[9px] font-bold uppercase animate-pulse shrink-0">≤24h</span>
                            @endif
                        </div>
                        <div class="flex items-center gap-1.5 min-w-0">
                            <span class="text-gray-400 shrink-0">Dr:</span>
                            <span class="text-gray-800 font-medium truncate">{{ $medicoAbrev ?? '—' }}</span>
                        </div>
                    </div>

                    {{-- Escalas (padrão SBAR) --}}
                    <div class="flex flex-wrap gap-1 justify-center">
                        @foreach(['Braden' => 'braden_score', 'Morse' => 'morse_score', 'Dor' => 'pain_score', 'TEV' => 'vte_score'] as $label => $key)
                            <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border border-gray-200 bg-white">
                                <strong class="text-gray-600">{{ $label }}:</strong>
                                <span class="ml-1 text-gray-800">{{ $patient[$key] ?? '-' }}</span>
                            </span>
                        @endforeach
                    </div>
                </div>

                {{-- Corpo: contadores + pendências / round --}}
                <div class="flex-1 overflow-y-auto px-3 pb-2 space-y-2 custom-scrollbar">

                    <div class="flex items-center gap-3 text-[11px]">
                        <span class="inline-flex items-center gap-1 font-semibold text-red-700">
                            <span class="w-2 h-2 rounded-full bg-red-500"></span>{{ $redCount }} red
                        </span>
                        <span class="inline-flex items-center gap-1 font-semibold text-green-700">
                            <span class="w-2 h-2 rounded-full bg-green-500"></span>{{ $greenCount }} green
                        </span>
                        <span class="text-gray-400">na internação</span>
                    </div>

                    {{-- Dias consecutivos na mesma cor (Red2Green) --}}
                    @if($streak && ($streak['days'] ?? 0) > 0)
                        <div class="flex items-center gap-1.5 text-[11px]">
                            <i class="fas fa-calendar-day {{ ($streak['color'] ?? '') === 'green' ? 'text-green-500' : 'text-red-500' }}"></i>
                            <span class="text-gray-600">
                                <strong class="{{ ($streak['color'] ?? '') === 'green' ? 'text-green-700' : 'text-red-700' }}">{{ $streak['days'] }} {{ $streak['days'] == 1 ? 'dia' : 'dias' }}</strong>
                                seguido{{ $streak['days'] == 1 ? '' : 's' }} em {{ ($streak['color'] ?? '') === 'green' ? 'green' : 'red' }}
                            </span>
                        </div>
                    @endif

                    {{-- Selo de exames pendentes (destaque) --}}
                    <div class="flex items-center gap-2 rounded-lg px-2.5 py-1.5 text-[11px] font-semibold {{ $examesPend > 0 ? 'bg-amber-50 text-amber-800 border border-amber-200' : 'bg-slate-50 text-gray-500 border border-gray-100' }}">
                        <i class="fas fa-flask {{ $examesPend > 0 ? 'text-amber-500' : 'text-gray-300' }}"></i>
                        @if($examesPend > 0)
                            <span>{{ $examesPend }} {{ $examesPend == 1 ? 'exame pendente' : 'exames pendentes' }}</span>
                        @else
                            <span>Sem exames pendentes</span>
                        @endif
                    </div>

                    <div class="grid grid-cols-2 gap-1">
                        @foreach($categories as $cat)
                            @php
                                $sig = $cat['force'] ?? ($cat['item'] ? ($signals[$cat['item']] ?? null) : (($cat['done'] ?? false) ? 'green' : null));
                                $dot = $sig === 'red' ? 'bg-red-500' : ($sig === 'green' ? 'bg-green-500' : 'bg-gray-300');
                            @endphp
                            <div class="flex items-center gap-1 bg-slate-50 rounded px-1.5 py-0.5 text-[10px]">
                                <span class="w-2 h-2 rounded-full {{ $dot }} shrink-0"></span>
                                <span class="text-gray-700 truncate">{{ $cat['label'] }}</span>
                                @if(! is_null($cat['count']) && $cat['count'] > 0)
                                    <span class="ml-auto font-bold text-gray-800">{{ $cat['count'] }}</span>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>

                {{-- Botão de detalhe (checklist, comentários, auditoria) --}}
                <div class="mt-auto flex-shrink-0 p-1.5 border-t border-gray-100">
                    <button
                        type="button"
                        class="w-full bg-slate-100 text-gray-800 px-3 py-2 rounded-md flex items-center justify-center gap-2 shadow-sm text-sm font-medium hover:bg-slate-200 transition-colors"
                        @click.prevent="$dispatch('openModal', {{ \Illuminate\Support\Js::from($openPayload) }})"
                    >
                        <i class="fas fa-notes-medical text-gray-500"></i>
                        <span>Detalhes</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
