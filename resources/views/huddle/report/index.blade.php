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
                <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                    {{-- Header --}}
                    <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 z-50 shadow-lg">
                        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-3">

                            <div class="flex items-center justify-center lg:justify-start gap-2">
                                <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white">
                                    Huddle — Gestão de Altas
                                </h1>
                            </div>

                            <div class="flex flex-wrap items-center justify-center lg:justify-end gap-2">
                                {{-- Hospital --}}
                                <select
                                    wire:change="changeHospital($event.target.value)"
                                    wire:loading.attr="disabled"
                                    wire:target="changeHospital,changeSector,refreshData"
                                    class="rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium px-3 py-2 shadow-sm focus:ring-2 focus:ring-white/60 disabled:opacity-60">
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
                                    class="rounded-lg border-0 bg-white/95 text-gray-800 text-sm font-medium px-3 py-2 shadow-sm focus:ring-2 focus:ring-white/60 disabled:opacity-60">
                                    @foreach($sectors as $sector)
                                        <option value="{{ $sector['cd_setor_atendimento'] }}" @selected($sector['cd_setor_atendimento'] == $selectedSector)>
                                            {{ $sector['ds_setor_atendimento'] }}
                                        </option>
                                    @endforeach
                                </select>

                                {{-- Atualizar --}}
                                <button
                                    wire:click="refreshData"
                                    wire:loading.attr="disabled"
                                    wire:target="refreshData"
                                    class="inline-flex items-center gap-1.5 px-3 py-2 rounded-lg text-white bg-[#0071B9] hover:bg-[#004D9D] shadow-md text-sm font-medium disabled:opacity-60">
                                    <i class="fas fa-rotate-right" wire:loading.class="animate-spin" wire:target="refreshData"></i>
                                    <span class="hidden sm:inline">Atualizar</span>
                                </button>
                            </div>
                        </div>

                        @if($lastRefresh)
                            <div class="mt-1 text-right text-white/70 text-xs">
                                Última atualização: {{ $lastRefresh }}
                            </div>
                        @endif
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
                                    <div wire:key="huddle-patient-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}">
                                        <x-huddle-card
                                            :patient="$patient"
                                            :current-hospital-name="$currentHospitalName"
                                        />
                                    </div>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    {{-- Modal de detalhe do paciente (reutiliza o componente existente) --}}
    @livewire('huddle-patient-modal')
</div>
