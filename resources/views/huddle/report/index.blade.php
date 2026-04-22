<div class="w-full my-2 text-[#004D9D] relative font-montserrat">
    <div class="py-6 lg:py-8">
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4">
            <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden font-montserrat">

                {{-- Header --}}
                <div class="bg-[#004D9D]/90 px-2 sm:px-3 lg:px-4 py-2 sm:py-2.5 lg:py-3 shadow-lg">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">

                        <h1 class="text-xl sm:text-2xl lg:text-3xl font-bold text-white font-montserrat">
                            Huddle de Alta
                        </h1>

                        <div class="flex items-center gap-2">
                            <select wire:change="changeHospital($event.target.value)"
                                    class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs lg:text-sm focus:ring-2 focus:ring-white/40">
                                @foreach($this->hospitals as $hospital)
                                    <option value="{{ $hospital['hospital_id'] }}"
                                            {{ $selectedHospital == $hospital['hospital_id'] ? 'selected' : '' }}>
                                        {{ $hospital['hospital_name'] }}
                                    </option>
                                @endforeach
                            </select>

                            <select wire:change="changeSector($event.target.value)"
                                    class="bg-white text-gray-700 border border-gray-300 rounded-lg py-1.5 px-2 text-xs lg:text-sm focus:ring-2 focus:ring-white/40">
                                @foreach($this->sectors as $sector)
                                    <option value="{{ $sector['cd_setor_atendimento'] }}"
                                            {{ $selectedSector == $sector['cd_setor_atendimento'] ? 'selected' : '' }}>
                                        {{ $sector['ds_setor_atendimento'] }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                    </div>
                </div>

                {{-- Cards --}}
                <div class="p-2 sm:p-3 lg:p-4 bg-white min-h-[60vh]">
                    @if($errorMessage)
                        <div class="bg-red-50 border border-red-200 text-red-700 px-6 py-4 rounded-lg flex items-center gap-2">
                            <x-heroicon-o-exclamation-triangle class="w-5 h-5 flex-shrink-0" />
                            {{ $errorMessage }}
                        </div>
                    @elseif(empty($this->patients))
                        <div class="bg-yellow-50 border border-yellow-200 text-yellow-800 px-6 py-4 rounded-lg flex items-center gap-2">
                            <x-heroicon-o-information-circle class="w-5 h-5 flex-shrink-0" />
                            Nenhum leito encontrado.
                        </div>
                    @else
                        @php $currentHospitalName = collect($this->hospitals)->firstWhere('hospital_id', $selectedHospital)['hospital_name'] ?? ''; @endphp
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 2xl:grid-cols-5 gap-3">
                            @foreach($this->patients as $index => $patient)
                                <div wire:key="huddle-bed-{{ $patient['nr_atendimento'] ?? 'empty-' . $index }}">
                                    <x-patient-card :patient="$patient" :current-hospital-name="$currentHospitalName" />
                                </div>
                            @endforeach
                        </div>
                    @endif
                </div>

            </div>
        </div>
    </div>
    <livewire:huddle-patient-modal />
</div>
