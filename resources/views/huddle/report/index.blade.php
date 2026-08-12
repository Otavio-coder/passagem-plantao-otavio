<div class="w-full my-2 text-[#004D9D] relative font-montserrat" wire:init="loadPatients">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">

            @if(isset($errorMessage) && $errorMessage)
                <div class="flex items-center justify-center min-h-[60vh]">
                    <div class="bg-white rounded-xl shadow-lg border border-gray-200 p-8 max-w-md text-center">
                        <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i class="fas fa-triangle-exclamation text-2xl text-amber-600"></i>
                        </div>
                        <h2 class="text-xl font-bold text-gray-900 mb-2">Aviso</h2>
                        <p class="text-gray-600 mb-6">{{ $errorMessage }}</p>
                        <a href="{{ route('user.preferences.index') }}"
                           class="inline-flex items-center gap-2 px-4 py-2 rounded-lg bg-[#004D9D] text-white text-sm font-medium hover:bg-[#003a78] transition-colors">
                            Configurar setores
                        </a>
                    </div>
                </div>
            @else
                <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat"
                     x-data="{ searchText: '', showLegend: false }">

                    {{-- Header --}}
                    <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 z-50 shadow-lg">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">

                            <div class="flex items-center justify-center lg:justify-start gap-2">
                                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white">
                                    Huddle — Gestão de Altas
                                </h1>
                                <button @click="showLegend = true" title="Legenda e orientações"
                                        class="px-2 sm:px-3 py-1.5 sm:py-2 text-white text-lg sm:text-sm font-bold rounded hover:bg-white/20 transition-colors flex-shrink-0">
                                    <span class="hidden sm:inline">Legenda e orientações</span>
                                    <span class="sm:hidden">?</span>
                                </button>
                            </div>

                            <div class="flex flex-wrap items-center justify-center lg:justify-end gap-2">
                                {{-- Hospital --}}
                                <select
                                    wire:change="changeHospital($event.target.value)"
                                    wire:loading.attr="disabled"
                                    wire:target="changeHospital,changeSector,refreshData"
                                    title="{{ collect($hospitals)->firstWhere('hospital_id', $selectedHospital)['hospital_name'] ?? '' }}"
                                    class="min-w-[16rem] max-w-full rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium pl-3 pr-8 py-2 shadow-sm focus:ring-2 focus:ring-white/60 disabled:opacity-60">
                                    @foreach($hospitals as $hospital)
                                        <option value="{{ $hospital['hospital_id'] }}" @selected($hospital['hospital_id'] == $selectedHospital)>
                                            {{ $hospital['hospital_name'] }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Setor --}}
                                <select
                                    wire:change="changeSector($event.target.value)"
                                    wire:loading.attr="disabled"
                                    wire:target="changeHospital,changeSector,refreshData"
                                    title="{{ collect($sectors)->firstWhere('cd_setor_atendimento', $selectedSector)['ds_setor_atendimento'] ?? '' }}"
                                    class="min-w-[12rem] max-w-full rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium pl-3 pr-8 py-2 shadow-sm focus:ring-2 focus:ring-white/60 disabled:opacity-60">
                                    @foreach($sectors as $sector)
                                        <option value="{{ $sector['cd_setor_atendimento'] }}" @selected($sector['cd_setor_atendimento'] == $selectedSector)>
                                            {{ $sector['ds_setor_atendimento'] }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Busca por leito ou paciente --}}
                                <div class="relative flex items-center flex-shrink-0">
                                    <i class="fas fa-search absolute left-2.5 top-1/2 -translate-y-1/2 text-gray-400 text-xs pointer-events-none"></i>
                                    <input type="text" x-model="searchText" placeholder="Leito ou paciente..."
                                           class="bg-white text-gray-700 border border-gray-300 rounded-lg py-2 pl-7 pr-7 text-sm focus:ring-2 focus:ring-white/60 w-44 xl:w-52" autocomplete="off">
                                    <button x-show="searchText" x-cloak @click="searchText = ''"
                                            class="absolute right-2 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
                                        <i class="fas fa-times text-xs"></i>
                                    </button>
                                </div>

                                {{-- Atualizar --}}
                                <button
                                    wire:click="refreshData"
                                    wire:loading.attr="disabled"
                                    wire:target="refreshData"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-sm font-medium disabled:opacity-60">
                                    <i class="fas fa-rotate-right" wire:loading.class="animate-spin" wire:target="refreshData"></i>
                                    <span wire:loading.remove wire:target="refreshData" class="hidden sm:inline">Atualizar</span>
                                    <span wire:loading wire:target="refreshData" class="hidden sm:inline">Atualizando...</span>
                                </button>
                            </div>
                        </div>

                        @if($lastRefresh)
                            <div class="mt-1 text-right text-white/70 text-xs">
                                Última atualização: {{ $lastRefresh }}
                            </div>
                        @endif
                    </div>

                    {{-- Botão centralizado Round Unidade (abaixo do header azul) --}}
                    @php
                        $currentSectorLabel = collect($sectors)->firstWhere('cd_setor_atendimento', $selectedSector)['ds_setor_atendimento'] ?? '';
                        $roundDone = collect($patients)->first(fn($p) => !empty($p['has_patient']))['huddle_unit_round_done'] ?? false;
                    @endphp
                    <div class="bg-white/80 border-b border-gray-200 px-2 sm:px-3 lg:px-4 py-2 flex items-center justify-start">
                        <button type="button"
                                x-data="{ done: {{ $roundDone ? 'true' : 'false' }} }"
                                @huddle-round-saved.window="done = true"
                                class="inline-flex items-center gap-2 px-4 py-2 rounded-lg text-sm font-bold uppercase tracking-wide shadow-sm transition-colors bg-[#004D9D] text-white hover:bg-[#003a78]"
                                @click.prevent="$dispatch('openUnitSafety', {
                                    sectorId: {{ (int) $selectedSector }},
                                    hospital: {{ \Illuminate\Support\Js::from($currentHospitalName) }},
                                    sectorLabel: {{ \Illuminate\Support\Js::from($currentSectorLabel) }}
                                })">
                            <i class="fas fa-clipboard-check"></i>
                            <span>Round Unidade</span>
                            <i class="fas fa-check text-green-300" x-show="done" x-cloak title="Preenchido hoje"></i>
                        </button>
                    </div>

                    {{-- Corpo --}}
                    <div class="p-2 sm:p-3 lg:p-4">

                        {{-- Carregando --}}
                        @if($isLoading)
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                @for($i = 0; $i < 10; $i++)
                                    @include('huddle.report.partials.skeleton-card')
                                @endfor
                            </div>
                        @elseif(empty($patients))
                            <div class="bg-white/70 border border-gray-200 rounded-lg px-6 py-10 text-center text-gray-600">
                                Nenhum paciente encontrado para este setor.
                            </div>
                        @else
                            <script>window.__huddleModalPatients = @json($patients);</script>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                                @foreach($patients as $index => $patient)
                                    @php $huddleNeedle = \Illuminate\Support\Str::lower(trim(($patient['cd_unidade_basica'] ?? '').' '.($patient['nm_pessoa_fisica'] ?? ''))); @endphp
                                    <div wire:key="huddle-patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}"
                                         x-show="searchText.trim() === '' || @js($huddleNeedle).includes(searchText.toLowerCase().trim())">
                                        <x-huddle-card
                                            :patient="$patient"
                                            :current-hospital-name="$currentHospitalName"
                                            :sector-id="$selectedSector"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>

                    {{-- Legenda e orientações --}}
                    <div x-show="showLegend" x-cloak class="fixed inset-0 z-[9997]" style="display: none;"
                         @keydown.escape.window="showLegend = false">
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showLegend = false"></div>
                        <div class="absolute inset-0 flex items-center justify-center p-4">
                            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg max-h-[85vh] overflow-y-auto font-montserrat">
                                <div class="sticky top-0 bg-[#004D9D] text-white px-4 py-3 flex items-center justify-between z-10">
                                    <p class="font-bold text-base"><i class="fas fa-circle-info mr-1"></i>Legenda e orientações</p>
                                    <button @click="showLegend = false" class="p-1.5 rounded hover:bg-white/20"><i class="fas fa-times"></i></button>
                                </div>

                                <div class="p-4 space-y-5 text-sm text-gray-700">
                                    {{-- Status do paciente --}}
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Status do paciente (Red2Green)</p>
                                        <div class="space-y-2">
                                            <div class="flex items-center gap-2"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase text-white bg-red-500"><span class="w-1.5 h-1.5 rounded-full bg-white/90"></span>Red</span><span>Dia vermelho — plano de alta não avançou. Todo dia começa vermelho.</span></div>
                                            <div class="flex items-center gap-2"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase text-white bg-green-500"><span class="w-1.5 h-1.5 rounded-full bg-white/90"></span>Green</span><span>Dia verde — o plano do dia avançou a jornada de alta.</span></div>
                                            <div class="flex items-center gap-2"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase text-white bg-slate-700"><i class="fas fa-users-line"></i>Round</span><span>Sem previsão de alta em 72h — discutir em Round.</span></div>
                                        </div>
                                        <p class="text-[11px] text-gray-400 mt-2">A borda do card também segue essa cor.</p>
                                    </div>

                                    {{-- Pendências --}}
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Pendências (Exames, Procedimentos, etc.)</p>
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-red-500"></span><span>Pendente</span></div>
                                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-green-500"></span><span>Resolvido / OK</span></div>
                                            <div class="flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full bg-gray-300"></span><span>Sem informação</span></div>
                                        </div>
                                    </div>

                                    {{-- Selos do card --}}
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Selos do card</p>
                                        <ul class="space-y-1.5 list-none">
                                            <li class="flex items-center gap-2"><i class="fas fa-flask text-amber-500"></i><span><strong>Exames pendentes</strong> — quantidade de exames a resolver.</span></li>
                                            <li class="flex items-center gap-2"><i class="fas fa-calendar-day text-red-500"></i><span><strong>X dias seguidos</strong> — sequência do paciente na mesma cor.</span></li>
                                        </ul>
                                    </div>

                                    {{-- Botões --}}
                                    <div>
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-gray-400 mb-2">Ações</p>
                                        <ul class="space-y-2 list-none">
                                            <li class="flex items-start gap-2"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold uppercase text-white bg-[#004D9D] mt-0.5"><i class="fas fa-clipboard-check"></i>Round Unidade</span><span>Formulário da unidade (por setor/dia). Fica <strong class="text-red-600">vermelho</strong> quando o paciente é red; o <i class="fas fa-check text-green-600"></i> indica que já foi preenchido hoje. Após salvar, não pode ser alterado.</span></li>
                                            <li class="flex items-start gap-2"><span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold text-gray-700 bg-slate-100 mt-0.5"><i class="fas fa-notes-medical"></i>Detalhes</span><span>Checklist do paciente, comentários (obrigatórios quando red) e auditoria (quem preencheu e quando).</span></li>
                                        </ul>
                                    </div>

                                    {{-- Orientações --}}
                                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-3">
                                        <p class="text-[11px] font-bold uppercase tracking-wide text-amber-700 mb-1">Orientações</p>
                                        <ul class="space-y-1 list-disc list-inside text-amber-800 text-[13px]">
                                            <li>A rotina do Huddle não ocorre em fins de semana e feriados.</li>
                                            <li>Use a busca por <strong>leito ou paciente</strong> para localizar rapidamente.</li>
                                            <li>Clique em <strong>Atualizar</strong> para buscar os dados mais recentes do Tasy.</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal de detalhe do paciente (reutiliza o componente existente) --}}
    @livewire('huddle-patient-modal')

    {{-- Modal do Huddle de Segurança por unidade (botão "Round Unidade" centralizado no header) --}}
    @livewire('huddle-unit-safety-modal')
</div>
