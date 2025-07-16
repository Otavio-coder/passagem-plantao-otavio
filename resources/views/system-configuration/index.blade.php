@extends('layouts.app')

@section('content')
<div class="container mx-auto max-w-3xl py-8 font-montserrat">
    <h1 class="text-2xl font-bold mb-6 text-[#004D9D] text-center">Configuração de Hospitais, Setores e Leitos</h1>

    @if(session('success'))
        <div class="bg-green-50 border-l-4 border-green-400 text-green-800 px-4 py-2 rounded mb-4 shadow-sm">
            {{ session('success') }}
        </div>
    @endif

    <form method="POST" action="{{ route('system-configuration.update') }}" class="space-y-8">
        @csrf

        <!-- Hospitais -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <label class="block text-[#004D9D] text-sm font-semibold mb-2">Hospitais Permitidos:</label>
            <select name="hospital_codes[]" id="hospitalSelect" multiple class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-2 pr-4 text-sm w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors">
                @foreach($hospitals as $hospital)
                    <option value="{{ $hospital->code }}"
                        @if(in_array($hospital->code, $selectedHospitals)) selected @endif>
                        {{ $hospital->name }} ({{ $hospital->code }})
                    </option>
                @endforeach
            </select>
            <small class="text-gray-500 block mt-1">Segure Ctrl para selecionar múltiplos.</small>
        </div>

        <!-- Setores e Leitos em abas minimizáveis -->
        <div class="bg-white rounded-xl shadow-lg border border-gray-100 p-6">
            <div x-data="{ openTab: 'setores' }">
                <div class="flex space-x-2 mb-4">
                    <button type="button" @click="openTab = 'setores'" :class="openTab === 'setores' ? 'bg-[#004D9D] text-white' : 'bg-gray-100 text-gray-700'" class="px-4 py-2 rounded-t-lg font-semibold transition-colors">Setores</button>
                    <button type="button" @click="openTab = 'leitos'" :class="openTab === 'leitos' ? 'bg-[#004D9D] text-white' : 'bg-gray-100 text-gray-700'" class="px-4 py-2 rounded-t-lg font-semibold transition-colors">Leitos</button>
                </div>
                <!-- Setores -->
                <div x-show="openTab === 'setores'" class="transition-all duration-300">
                    <label class="block text-[#004D9D] text-sm font-semibold mb-2">Setores Permitidos:</label>
                    <div class="max-h-64 overflow-y-auto border rounded-lg bg-blue-50/40 p-2">
                        <select name="sector_codes[]" id="sectorSelect" multiple class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-2 pr-4 text-sm w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors">
                        @foreach($sectors as $sector)
                            @if(in_array($sector->hospital_id, $selectedHospitals))
                                <option value="{{ $sector->code }}"
                                    @if(in_array($sector->code, $selectedSectors)) selected @endif>
                                    {{ $sector->name }} 
                                </option>
                            @endif
                        @endforeach
                        </select>
                    </div>
                    <small class="text-gray-500 block mt-1">Segure Ctrl para selecionar múltiplos.</small>
                </div>
                <!-- Leitos -->
                <div x-show="openTab === 'leitos'" class="transition-all duration-300">
                    <label class="block text-[#004D9D] text-sm font-semibold mb-2">Leitos Permitidos:</label>
                    <div class="max-h-64 overflow-y-auto border rounded-lg bg-blue-50/40 p-2">
                        <button type="button" id="selectAllBeds" class="mb-2 px-3 py-1 bg-[#0071B9] text-white rounded text-xs hover:bg-[#004D9D]">
                            Selecionar Todos os Leitos Visíveis
                        </button>
                        <select name="bed_codes[]" id="bedSelect" multiple class="appearance-none bg-white text-gray-700 border border-gray-300 rounded py-2 px-2 pr-4 text-sm w-full focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 hover:border-[#0071B9]/30 transition-colors">
                        @foreach($bedunits as $bed)
                            @if(in_array($bed->cd_setor_atendimento, $selectedSectors))
                                <option value="{{ $bed->code }}|{{ $bed->cd_setor_atendimento }}"
                                    @if(in_array($bed->code . '|' . $bed->cd_setor_atendimento, $selectedBeds)) selected @endif>
                                    {{ $bed->name }} ({{ $bed->code }}) - Setor {{ $bed->cd_setor_atendimento }}
                                </option>
                            @endif
                        @endforeach
                        </select>
                    </div>
                    <small class="text-gray-500 block mt-1">Segure Ctrl para selecionar múltiplos.</small>
                </div>
            </div>
        </div>

        <div class="flex justify-end">
            <button type="submit" class="inline-flex items-center px-6 py-2 rounded text-white transition-all duration-200 bg-[#0071B9] hover:bg-[#004D9D] shadow-md hover:shadow-lg focus:outline-none focus:ring-1 focus:ring-[#0071B9]/50 text-base font-semibold">
                <svg class="w-5 h-5 mr-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                </svg>
                Salvar Configurações
            </button>
        </div>
    </form>
</div>
@endsection

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
<script>
var sectorsByHospitalUrl = "{{ route('system-configuration.sectors-by-hospital') }}";
var bedsBySectorUrl = "{{ route('system-configuration.beds-by-sector') }}";
var csrfToken = "{{ csrf_token() }}";

document.addEventListener('DOMContentLoaded', function() {
    const hospitalSelect = document.getElementById('hospitalSelect');
    const sectorSelect = document.getElementById('sectorSelect');

    const bedSelect = document.getElementById('bedSelect');
    const selectAllBedsBtn = document.getElementById('selectAllBeds');

    if (selectAllBedsBtn && bedSelect) {
        selectAllBedsBtn.addEventListener('click', function() {
            for (let i = 0; i < bedSelect.options.length; i++) {
                bedSelect.options[i].selected = true;
            }
            bedSelect.dispatchEvent(new Event('change'));
        });
    }

    hospitalSelect.addEventListener('change', function() {
        const selectedHospitals = Array.from(hospitalSelect.selectedOptions).map(opt => opt.value);
        fetch(sectorsByHospitalUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ hospital_ids: selectedHospitals })
        })
        .then(res => res.json())
        .then(sectors => {
            sectorSelect.innerHTML = '';
            sectors.forEach(sector => {
                const option = document.createElement('option');
                option.value = sector.code;
                option.textContent = `${sector.name} (${sector.code})`;
                sectorSelect.appendChild(option);
            });
            sectorSelect.dispatchEvent(new Event('change'));
        });
    });

    sectorSelect.addEventListener('change', function() {
        const selectedSectors = Array.from(sectorSelect.selectedOptions).map(opt => opt.value);
        fetch(bedsBySectorUrl, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrfToken
            },
            body: JSON.stringify({ sector_ids: selectedSectors })
        })
        .then(res => res.json())
        .then(beds => {
            bedSelect.innerHTML = '';
            beds.forEach(bed => {
                const option = document.createElement('option');
                option.value = bed.code + '|' + bed.cd_setor_atendimento;
                option.textContent = `${bed.name} (${bed.code}) - Setor ${bed.cd_setor_atendimento}`;
                bedSelect.appendChild(option);
            });
        });
    });
    
});
</script>
@endpush