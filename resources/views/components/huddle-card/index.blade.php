@props([
    'patient',
    'currentHospitalName' => '',
    'sectorId' => 0,
])

@php
    $isPatient = (bool) ($patient['has_patient'] ?? false);
    $styling = $patient['huddle_styling'] ?? [
        'gradient' => 'from-red-50 to-red-100',
        'border' => 'border-2 border-red-400',
        'badge' => 'bg-red-500 text-white',
        'text' => 'text-red-700',
    ];
    $redCount = (int) ($patient['huddle_red_count'] ?? 0);
    $greenCount = (int) ($patient['huddle_green_count'] ?? 0);
    $edd = $patient['huddle_expected_discharge'] ?? null;
    // Round automático: sem previsão de alta nas próximas 72h (vindo do Tasy)
    $isRound = ! ($patient['huddle_discharge_within_72h'] ?? false);

    $signals = $patient['huddle_checklist'] ?? [];   // item_code => 'red'|'green'
    $pend = $patient['huddle_pending'] ?? [];         // exames/procedimentos/terapias/multidisciplinar

    // Transporte e orientação vindos do Tasy (green/red/null)
    $transporteTasy = $patient['huddle_transporte'] ?? null;
    $transporteSig = $transporteTasy === 'organizado' ? 'green' : ($transporteTasy === 'pendente' ? 'red' : null);
    $orientacaoTasy = $patient['huddle_orientacao'] ?? null;
    $orientacaoSig = $orientacaoTasy === 'feita' ? 'green' : ($orientacaoTasy === 'pendente' ? 'red' : null);

    // Categorias obrigatórias no card: rótulo, item do checklist (sinal) e contagem do Tasy.
    $categories = [
        ['label' => 'Exames',        'item' => 'exames_laudo',  'count' => $pend['exames'] ?? null],
        ['label' => 'Procedimentos', 'item' => 'procedimentos', 'count' => $pend['procedimentos'] ?? null],
        ['label' => 'Multidisc.',    'item' => 'consultorias',  'count' => $pend['multidisciplinar'] ?? null],
        ['label' => 'Terapias',      'item' => 'terapias',      'count' => $pend['terapias'] ?? null],
        ['label' => 'Orientação',    'item' => 'orientacao_alta', 'count' => null, 'force' => $orientacaoSig],
        ['label' => 'Transporte',    'item' => 'transporte',    'count' => null, 'force' => $transporteSig],
        ['label' => 'Presc. alta',   'item' => null,            'count' => null, 'done' => $patient['huddle_prescricao_alta'] ?? false],
    ];
@endphp

<div class="relative w-full">
    <div class="flex flex-col rounded-xl shadow-lg overflow-hidden h-[300px] bg-gradient-to-br {{ $styling['gradient'] }} {{ $styling['border'] }}">

        @if(! $isPatient)
            <x-huddle-card.empty-bed :patient="$patient" />
        @else
            <div class="flex flex-col h-full overflow-hidden">

                {{-- Cabeçalho: leito + badge Red/Green --}}
                <div class="flex-shrink-0 p-3 flex flex-col gap-2">
                    <div class="flex justify-between items-center gap-2">
                        <span class="inline-flex items-center bg-white/80 text-gray-800 text-sm md:text-base font-bold px-3 py-1.5 rounded-full shadow-sm">
                            Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                        </span>

                        <span class="inline-flex items-center gap-1 px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wide shadow-sm {{ $styling['badge'] }}">
                            <span class="w-2 h-2 rounded-full bg-white/80"></span>
                            {{ $patient['huddle_color_label'] ?? 'Red' }}
                        </span>
                    </div>

                    {{-- Identificação --}}
                    <div class="min-w-0">
                        <p class="font-bold text-gray-900 text-sm md:text-base truncate" title="{{ $patient['nm_pessoa_fisica'] ?? '' }}">
                            {{ $patient['nm_pessoa_fisica'] ?? 'Paciente' }}
                        </p>
                        <p class="text-xs text-gray-600 mt-0.5">
                            {{ $patient['age_label'] ?? '—' }}
                            @if(isset($patient['internment_days']) && $patient['internment_days'] >= 0)
                                <span class="mx-1">•</span> {{ $patient['internment_days'] }} dia(s) internado
                            @endif
                        </p>
                    </div>
                </div>

                {{-- Corpo: previsão de alta + contadores + motivos --}}
                <div class="flex-1 overflow-y-auto px-3 pb-2 custom-scrollbar space-y-2">

                    {{-- Previsão de alta (EDD) + selo de alta em 24h --}}
                    <div class="flex items-center gap-2 text-xs bg-white/60 rounded-lg px-2.5 py-1.5">
                        <i class="fas fa-calendar-day text-gray-500"></i>
                        <span class="text-gray-600">Previsão de alta:</span>
                        <span class="font-semibold {{ $edd ? 'text-gray-900' : 'text-amber-600' }}">
                            {{ $edd ?? 'não definida' }}
                        </span>
                        @if($patient['huddle_discharge_within_24h'] ?? false)
                            <span class="ml-auto inline-flex items-center gap-1 px-1.5 py-0.5 rounded bg-blue-600 text-white text-[10px] font-bold uppercase animate-pulse">
                                Alta ≤24h
                            </span>
                        @endif
                    </div>

                    {{-- Contadores da internação --}}
                    <div class="flex items-center gap-3 text-xs">
                        <span class="inline-flex items-center gap-1.5 font-semibold text-red-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-red-500"></span>
                            {{ $redCount }} red
                        </span>
                        <span class="inline-flex items-center gap-1.5 font-semibold text-green-700">
                            <span class="w-2.5 h-2.5 rounded-full bg-green-500"></span>
                            {{ $greenCount }} green
                        </span>
                        <span class="text-gray-400">na internação</span>
                    </div>

                    {{-- Pendências (obrigatórias): categoria + status Red/Green + contagem do Tasy --}}
                    @if($isRound)
                        <div class="flex items-center justify-center gap-2 w-full rounded-lg bg-slate-700 text-white px-3 py-2 shadow-sm">
                            <i class="fas fa-users-line text-sm"></i>
                            <span class="text-xs font-bold uppercase tracking-wide">Discutir em Round</span>
                        </div>
                    @else
                        <div class="grid grid-cols-2 gap-1">
                            @foreach($categories as $cat)
                                @php
                                    // Prioridade: status forçado do Tasy (ex.: transporte) > sinal do checklist > "feito"
                                    $sig = $cat['force'] ?? ($cat['item'] ? ($signals[$cat['item']] ?? null) : (($cat['done'] ?? false) ? 'green' : null));
                                    $dot = $sig === 'red' ? 'bg-red-500' : ($sig === 'green' ? 'bg-green-500' : 'bg-gray-300');
                                @endphp
                                <div class="flex items-center gap-1 bg-white/60 rounded px-1.5 py-0.5 text-[10px]">
                                    <span class="w-2 h-2 rounded-full {{ $dot }} shrink-0"></span>
                                    <span class="text-gray-700 truncate">{{ $cat['label'] }}</span>
                                    @if(! is_null($cat['count']) && $cat['count'] > 0)
                                        <span class="ml-auto font-bold text-gray-800">{{ $cat['count'] }}</span>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

                {{-- Botão de detalhe --}}
                <div class="mt-auto flex-shrink-0 p-1.5 border-t border-white/30">
                    <button
                        type="button"
                        class="w-full bg-white/40 text-gray-800 px-3 py-2 rounded-md flex items-center justify-center gap-2 shadow-sm text-sm font-medium hover:bg-white/60 transition-colors"
                        @click.prevent="$dispatch('openModal', {
                            attendanceNumber: {{ (int) ($patient['nr_atendimento'] ?? 0) }},
                            hospital: {{ \Illuminate\Support\Js::from($currentHospitalName) }},
                            sectorId: {{ (int) $sectorId }},
                            sbarPatient: {{ \Illuminate\Support\Js::from($patient) }},
                            patients: window.__huddleModalPatients ?? []
                        })"
                    >
                        <i class="fas fa-notes-medical text-gray-500"></i>
                        <span>Detalhes</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>
