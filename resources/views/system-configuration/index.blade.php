@extends('layouts.app')

@section('content')
<div class="w-full px-3 my-2 text-gray-600">
    
    {{-- Header --}}
    <div class="flex justify-between items-center mb-6">
        <h1 class="text-2xl font-medium text-santacasa-100">
            <i class="fas fa-hospital mr-2"></i>
            Meus Setores
        </h1>
        <a href="{{ route('home') }}" class="text-santacasa-100 hover:text-santacasa-200 transition-colors">
            <i class="fas fa-arrow-left mr-1"></i> Voltar
        </a>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="mb-4 bg-green-50 border border-green-200 rounded-lg p-3 flex items-center gap-2">
            <i class="fas fa-check-circle text-green-600"></i>
            <span class="text-sm text-green-800">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3 flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-600"></i>
            <span class="text-sm text-red-800">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Card Principal --}}
    <div class="bg-white rounded-xl shadow-lg border border-gray-200">
        
        {{-- Header Azul --}}
        <div class="bg-[#004D9D] px-6 py-4 rounded-t-xl">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-lg font-semibold text-white">Configuração de Setores</h2>
                    <p class="text-sm text-white/80 mt-1">Selecione os setores de internação que deseja acompanhar</p>
                </div>
                <div class="flex items-center gap-2 bg-white/10 px-4 py-2 rounded-lg">
                    <span id="headerCount" class="text-white font-bold text-2xl">{{ count($selectedSectors) }}</span>
                    <span class="text-white/70 text-sm">selecionado(s)</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('user.preferences.update') }}">
            @csrf

            {{-- Search --}}
            <div class="px-6 py-4 border-b border-gray-200 bg-gray-50">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400"></i>
                    <input 
                        type="text" 
                        id="searchInput"
                        placeholder="Buscar setor ou hospital..."
                        class="w-full pl-10 pr-4 py-2.5 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D]"
                        onkeyup="filterSectors(this.value)"
                    >
                </div>
            </div>

            {{-- Lista de Hospitais --}}
            <div class="p-4 max-h-[500px] overflow-y-auto">
                @php
                    $hospitalColors = [
                        '1' => '#8C3134', '8' => '#42909A', '2' => '#A96538',
                        '6' => '#3E6B35', '4' => '#4586B4', '3' => '#9574A1',
                        '25' => '#CE7D74', '7' => '#563174', '18' => '#073772',
                    ];
                @endphp

                @forelse($sectorsByHospital as $hospitalCode => $sectors)
                    @php
                        $color = $hospitalColors[$hospitalCode] ?? '#004D9D';
                        $selectedCount = $sectors->filter(fn($s) => in_array($s['sector_code'], $selectedSectors))->count();
                        $isExpanded = $selectedCount > 0;
                    @endphp
                    
                    <div class="hospital-group mb-3 border border-gray-200 rounded-xl overflow-hidden" data-hospital-name="{{ strtolower($sectors->first()['hospital_name']) }}">
                        
                        {{-- Accordion Header --}}
                        <div class="flex items-center justify-between px-4 py-3 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors"
                             onclick="toggleAccordion('{{ $hospitalCode }}')">
                            <div class="flex items-center gap-3 min-w-0">
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center text-white text-sm font-bold flex-shrink-0" style="background: {{ $color }}">
                                    {{ substr($sectors->first()['hospital_name'], 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-800 truncate">{{ $sectors->first()['hospital_name'] }}</h3>
                                    <span class="text-xs text-gray-500">{{ $sectors->count() }} setores</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                @if($selectedCount > 0)
                                    <span class="bg-[#004D9D] text-white text-xs px-2 py-1 rounded-full">{{ $selectedCount }}</span>
                                @endif
                                <button type="button" 
                                        onclick="event.stopPropagation(); toggleHospital('{{ $hospitalCode }}')"
                                        class="text-xs font-medium px-3 py-1.5 rounded-md bg-white border border-gray-300 hover:bg-gray-50">
                                    Selecionar todos
                                </button>
                                <i id="icon-{{ $hospitalCode }}" class="fas fa-chevron-down text-gray-400 transition-transform duration-200 {{ $isExpanded ? 'rotate-180' : '' }}"></i>
                            </div>
                        </div>
                        
                        {{-- Accordion Content --}}
                        <div id="content-{{ $hospitalCode }}" class="transition-all duration-200 overflow-hidden {{ $isExpanded ? '' : 'hidden' }}">
                            <div class="p-3 bg-white border-t border-gray-100">
                                <div class="flex flex-wrap gap-2">
                                    @foreach($sectors as $sector)
                                        @php
                                            $isChecked = in_array($sector['sector_code'], $selectedSectors);
                                        @endphp
                                        <label class="sector-item inline-flex items-center gap-2 px-3 py-2 rounded-lg border cursor-pointer transition-all hover:shadow-sm {{ $isChecked ? 'bg-blue-50 border-blue-300' : 'bg-white border-gray-200 hover:bg-gray-50' }}"
                                               data-sector-name="{{ strtolower($sector['sector_name']) }}">
                                            <input type="checkbox" name="sector_codes[]" value="{{ $sector['sector_code'] }}" {{ $isChecked ? 'checked' : '' }} class="hidden" onchange="updateCheckbox(this)">
                                            <div class="w-4 h-4 rounded border flex items-center justify-center flex-shrink-0 {{ $isChecked ? 'bg-[#004D9D] border-[#004D9D]' : 'border-gray-300 bg-white' }}">
                                                @if($isChecked)
                                                    <i class="fas fa-check text-white text-xs"></i>
                                                @endif
                                            </div>
                                            <span class="text-sm text-gray-700 whitespace-nowrap">{{ $sector['sector_name'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-gray-400">
                        <i class="fas fa-inbox text-4xl mb-2"></i>
                        <p>Nenhum setor disponível.</p>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="px-6 py-4 bg-gray-50 border-t border-gray-200 rounded-b-xl">
                <div class="flex flex-col sm:flex-row items-center justify-between gap-4">
                    <div class="text-center sm:text-left">
                        <span id="footerCount" class="font-bold text-[#004D9D] text-xl">{{ count($selectedSectors) }}</span>
                        <span class="text-sm text-gray-600">setor(es) selecionado(s)</span>
                    </div>
                    <div class="flex gap-3">
                        <a href="{{ route('home') }}" class="px-5 py-2 text-sm font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors">
                            Cancelar
                        </a>
                        <button type="submit" class="px-6 py-2 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] rounded-lg transition-colors shadow-md">
                            <i class="fas fa-save mr-1"></i> Salvar
                        </button>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
    function toggleAccordion(hospitalCode) {
        const content = document.getElementById('content-' + hospitalCode);
        const icon = document.getElementById('icon-' + hospitalCode);
        
        if (content.classList.contains('hidden')) {
            content.classList.remove('hidden');
            icon.classList.add('rotate-180');
        } else {
            content.classList.add('hidden');
            icon.classList.remove('rotate-180');
        }
    }

    function toggleHospital(hospitalCode) {
        const checkboxes = document.querySelectorAll('#content-' + hospitalCode + ' input[type="checkbox"]');
        const allChecked = Array.from(checkboxes).every(cb => cb.checked);
        
        checkboxes.forEach(cb => {
            cb.checked = !allChecked;
            updateCheckbox(cb);
        });
    }

    function updateCheckbox(checkbox) {
        const label = checkbox.closest('label');
        const indicator = label.querySelector('.w-4');
        
        if (checkbox.checked) {
            label.classList.add('bg-blue-50', 'border-blue-300');
            label.classList.remove('bg-white', 'border-gray-200');
            indicator.classList.add('bg-[#004D9D]', 'border-[#004D9D]');
            indicator.classList.remove('border-gray-300', 'bg-white');
            indicator.innerHTML = '<i class="fas fa-check text-white text-xs"></i>';
        } else {
            label.classList.remove('bg-blue-50', 'border-blue-300');
            label.classList.add('bg-white', 'border-gray-200');
            indicator.classList.remove('bg-[#004D9D]', 'border-[#004D9D]');
            indicator.classList.add('border-gray-300', 'bg-white');
            indicator.innerHTML = '';
        }
        
        const count = document.querySelectorAll('input[name="sector_codes[]"]:checked').length;
        document.getElementById('headerCount').textContent = count;
        document.getElementById('footerCount').textContent = count;
    }

    function filterSectors(query) {
        const term = query.toLowerCase().trim();
        const hospitals = document.querySelectorAll('.hospital-group');
        
        hospitals.forEach(hospital => {
            const hospitalName = hospital.dataset.hospitalName;
            const sectors = hospital.querySelectorAll('.sector-item');
            let hasVisible = false;
            
            sectors.forEach(sector => {
                const sectorName = sector.dataset.sectorName;
                if (!term || sectorName.includes(term) || hospitalName.includes(term)) {
                    sector.style.display = 'inline-flex';
                    hasVisible = true;
                } else {
                    sector.style.display = 'none';
                }
            });
            
            hospital.style.display = hasVisible ? 'block' : 'none';
        });
    }
</script>
@endsection
