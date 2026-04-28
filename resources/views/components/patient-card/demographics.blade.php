@props(['patient', 'showAdminData' => false, 'showScales' => false])

{{-- Row 2: Patient Name + Gender + Age --}}
<div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
    <div class="flex items-center gap-2">
        @if(($patient['sexo'] ?? '') === 'F')
            <x-iconoir-female class="text-pink-600 h-4 w-4 flex-shrink-0" />
        @elseif(($patient['sexo'] ?? '') === 'M')
            <x-iconoir-male class="text-blue-600 h-4 w-4 flex-shrink-0" />
        @endif
        <p class="text-gray-900 text-sm font-bold truncate flex-1 min-w-0">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
        <span class="text-gray-700 text-xs sm:text-sm font-semibold flex-shrink-0">{{ $patient['age'] ?? '?' }}a</span>
        <span class="text-gray-700 text-xs font-semibold flex-shrink-0">({{ $patient['birth_date'] ?? '?' }})</span>
    </div>
</div>

@if($showAdminData)
{{-- Row 3: Administrative Data grid --}}
@php
    $prevAltaRaw = $patient['discharge_info']['dt_previsto_alta_formatted'] ?? null;
    $hasPrevAlta = in_array(($patient['discharge_display']['type'] ?? ''), ['previsao_alta', 'alta_medica']) && !empty($prevAltaRaw);
    $prevAlta = $prevAltaRaw ? (substr($prevAltaRaw, 0, 5) . ' ' . substr($prevAltaRaw, -5)) : null;
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
<div class="bg-white/70 rounded-lg px-2 py-0.5 shadow-sm">
    <div class="grid grid-cols-3 gap-x-2 gap-y-0 text-[9px] leading-snug">
        {{-- Row 1: Nº | Alta | Int --}}
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="text-gray-500 shrink-0">Nº:</span>
            <span class="text-gray-900 font-medium overflow-hidden">{{ $patient['nr_atendimento'] ?? '—' }}</span>
        </div>
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            @if($hasPrevAlta)
                <span class="text-orange-600 shrink-0"> Prev. Alta:</span>
                <span class="text-orange-700 font-semibold overflow-hidden">{{ $prevAlta }}</span>
            @else
                <span class="text-gray-500 shrink-0">Prev. Alta:</span>
                <span class="text-gray-400">—</span>
            @endif
        </div>
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="text-gray-500 shrink-0">Int:</span>
            @if($patient['is_new_patient'] ?? false)
                <span class="text-green-700 font-semibold">Hoje</span>
            @elseif(isset($patient['internment_days']) && $patient['internment_days'] !== null)
                <span class="text-gray-900 font-medium">{{ ceil($patient['internment_days']) }}d</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </div>
        {{-- Row 2: Dr | Enf | Educ --}}
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="text-gray-500 shrink-0">Dr:</span>
            @if($medicoAbrev)
                <span class="text-gray-900 font-medium overflow-hidden">{{ $medicoAbrev }}</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </div>
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="text-gray-500 shrink-0">Enf:</span>
            @if($avalEnfOk)
                <span class="text-gray-900 font-medium">{{ $avalEnfDisplay }}</span>
            @else
                <span class="text-gray-400">N/A</span>
            @endif
        </div>
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="text-gray-500 shrink-0">Educ:</span>
            @if($peOk)
                <span class="text-gray-900 font-medium">{{ $peData }}</span>
            @else
                <span class="text-gray-400">N/A</span>
            @endif
        </div>
        {{-- Row 3: Hemoc (2 cols) | Conv --}}
        <div class="col-span-2 flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="{{ $hemocPend ? 'text-purple-600 font-semibold shrink-0' : 'text-gray-500 shrink-0' }}">Hemoc:</span>
            @if($hemocPend)
                <span class="text-purple-700 font-semibold shrink-0">Pend.</span>
                @if($hemocDate)<span class="text-purple-500 font-normal overflow-hidden"> últ.{{ $hemocDate }}</span>@endif
            @elseif($hemocDate)
                <span class="text-gray-900 font-medium overflow-hidden">{{ $hemocDate }}</span>
            @else
                <span class="text-gray-400">—</span>
            @endif
        </div>
        <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
            <span class="text-gray-500 shrink-0">Conv:</span>
            <span class="text-gray-900 font-medium overflow-hidden">{{ $patient['convenio_short'] ?? '—' }}</span>
        </div>
    </div>
</div>
@endif

@if($showScales)
{{-- Row 4: Risk Scales (clickable → opens lightweight scales modal) --}}
<div x-data="{ showScalesModal: false }">
    <div
        class="bg-white/70 rounded-lg px-2 py-1 shadow-sm cursor-pointer hover:bg-white/90 transition-colors"
        title="Ver escalas de avaliação"
        @click="showScalesModal = true; document.body.style.overflow = 'hidden'"
    >
        <div class="flex flex-wrap gap-1 justify-center items-center min-h-[18px]">
            @if($patient['has_patient'] ?? false)
                {{-- Braden --}}
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
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
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
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
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
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
                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
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
