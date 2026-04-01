@extends('layouts.app')

@push('head')
<style>
    #sector-config-page {
        position: fixed;
        top: 4rem; /* navbar h-16 */
        left: 0; right: 0; bottom: 0;
        z-index: 30;
        overflow: hidden;
        display: flex;
        flex-direction: column;
    }
</style>
@endpush

@section('content')
<div id="sector-config-page" class="px-3 pt-1 pb-2 text-gray-600 bg-gray-50">

    {{-- Header --}}
    <div class="flex justify-between items-center mb-2 flex-shrink-0">
        <span class="text-base md:text-xl font-medium text-santacasa-100">
            <i class="fas fa-hospital mr-1"></i>
            Meus Setores
        </span>
        <a href="{{ route('home') }}"
           class="inline-flex justify-center items-center px-3 py-1.5 text-santacasa-100 hover:text-santacasa-200 transition text-sm font-medium">
            <i class="fas fa-arrow-left mr-1"></i>
            <span class="hidden md:inline">Voltar</span>
        </a>
    </div>

    {{-- Alertas --}}
    @if(session('success'))
        <div class="mb-2 bg-green-50 border border-green-200 rounded-lg px-3 py-2 flex items-center gap-2 flex-shrink-0">
            <i class="fas fa-check-circle text-green-600"></i>
            <span class="text-xs text-green-800">{{ session('success') }}</span>
        </div>
    @endif

    @if(session('error'))
        <div class="mb-2 bg-red-50 border border-red-200 rounded-lg px-3 py-2 flex items-center gap-2 flex-shrink-0">
            <i class="fas fa-exclamation-circle text-red-600"></i>
            <span class="text-xs text-red-800">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Card Principal --}}
    <div class="flex flex-col flex-1 min-h-0 bg-white rounded-xl shadow-lg border border-gray-200 overflow-hidden">

        {{-- Header Azul --}}
        <div class="bg-[#004D9D] px-4 py-2.5 rounded-t-xl flex-shrink-0">
            <div class="flex items-center justify-between">
                <div>
                    <h2 class="text-sm font-semibold text-white">Configuração de Setores</h2>
                    <p class="text-xs text-white/80 mt-0.5">Selecione os setores que deseja acompanhar</p>
                </div>
                <div class="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-lg">
                    <span id="headerCount" class="text-white font-bold text-xl">{{ count($selectedSectors) }}</span>
                    <span class="text-white/70 text-xs">selecionado(s)</span>
                </div>
            </div>
        </div>

        <form method="POST" action="{{ route('user.preferences.update') }}" class="flex flex-col flex-1 min-h-0">
            @csrf

            {{-- Search --}}
            <div class="px-4 py-2 border-b border-gray-200 bg-gray-50 flex-shrink-0">
                <div class="relative">
                    <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                    <input
                        type="text"
                        id="searchInput"
                        placeholder="Buscar setor ou hospital..."
                        class="w-full pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#004D9D] focus:border-[#004D9D]"
                        onkeyup="filterSectors(this.value)"
                    >
                </div>
            </div>

            {{-- Lista de Hospitais --}}
            <div class="flex-1 min-h-0 p-3 overflow-y-auto">
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

                    <div class="hospital-group mb-2 border border-gray-200 rounded-lg overflow-hidden" data-hospital-name="{{ strtolower($sectors->first()['hospital_name']) }}">

                        {{-- Accordion Header --}}
                        <div class="flex items-center justify-between px-3 py-2 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors"
                             onclick="toggleAccordion('{{ $hospitalCode }}')">
                            <div class="flex items-center gap-2 min-w-0">
                                <div class="w-6 h-6 rounded-md flex items-center justify-center text-white text-xs font-bold flex-shrink-0" style="background: {{ $color }}">
                                    {{ substr($sectors->first()['hospital_name'], 0, 1) }}
                                </div>
                                <div class="min-w-0">
                                    <h3 class="font-semibold text-gray-800 truncate text-sm">{{ $sectors->first()['hospital_name'] }}</h3>
                                    <span class="text-xs text-gray-500">{{ $sectors->count() }} setores</span>
                                </div>
                            </div>
                            <div class="flex items-center gap-2 flex-shrink-0 ml-2">
                                @if($selectedCount > 0)
                                    <span class="bg-[#004D9D] text-white text-xs px-2 py-0.5 rounded-full">{{ $selectedCount }}</span>
                                @endif
                                <button type="button"
                                        onclick="event.stopPropagation(); toggleHospital('{{ $hospitalCode }}')"
                                        class="text-xs font-medium px-2 py-1 rounded-md bg-white border border-gray-300 hover:bg-gray-50">
                                    Todos
                                </button>
                                <i id="icon-{{ $hospitalCode }}" class="fas fa-chevron-down text-gray-400 transition-transform duration-200 text-xs {{ $isExpanded ? 'rotate-180' : '' }}"></i>
                            </div>
                        </div>

                        {{-- Accordion Content --}}
                        <div id="content-{{ $hospitalCode }}" class="transition-all duration-200 overflow-hidden {{ $isExpanded ? '' : 'hidden' }}">
                            <div class="p-2 bg-white border-t border-gray-100">
                                <div class="flex flex-wrap gap-1.5">
                                    @foreach($sectors as $sector)
                                        @php
                                            $isChecked = in_array($sector['sector_code'], $selectedSectors);
                                        @endphp
                                        <label class="sector-item inline-flex items-center gap-1.5 px-2.5 py-1.5 rounded-lg border cursor-pointer transition-all hover:shadow-sm {{ $isChecked ? 'bg-blue-50 border-blue-300' : 'bg-white border-gray-200 hover:bg-gray-50' }}"
                                               data-sector-name="{{ strtolower($sector['sector_name']) }}">
                                            <input type="checkbox" name="sector_codes[]" value="{{ $sector['sector_code'] }}" {{ $isChecked ? 'checked' : '' }} class="hidden" onchange="updateCheckbox(this)">
                                            <div data-indicator class="w-3.5 h-3.5 rounded border flex items-center justify-center flex-shrink-0 {{ $isChecked ? 'bg-[#004D9D] border-[#004D9D]' : 'border-gray-300 bg-white' }}">
                                                @if($isChecked)
                                                    <i class="fas fa-check text-white" style="font-size: 0.5rem"></i>
                                                @endif
                                            </div>
                                            <span class="text-xs text-gray-700 whitespace-nowrap">{{ $sector['sector_name'] }}</span>
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
            <div class="px-4 py-2.5 bg-gray-50 border-t border-gray-200 rounded-b-xl flex-shrink-0">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <span id="footerCount" class="font-bold text-[#004D9D] text-lg">{{ count($selectedSectors) }}</span>
                        <span class="text-xs text-gray-600">setor(es) selecionado(s)</span>
                    </div>
                    <div class="flex gap-2">
                        <a href="{{ route('home') }}" class="px-4 py-1.5 text-xs font-medium text-gray-600 hover:text-gray-800 hover:bg-gray-200 rounded-lg transition-colors">
                            Cancelar
                        </a>
                        <button type="submit" class="px-5 py-1.5 text-xs font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] rounded-lg transition-colors shadow-md">
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
        const indicator = label.querySelector('[data-indicator]');

        if (checkbox.checked) {
            label.classList.add('bg-blue-50', 'border-blue-300');
            label.classList.remove('bg-white', 'border-gray-200');
            indicator.classList.add('bg-[#004D9D]', 'border-[#004D9D]');
            indicator.classList.remove('border-gray-300', 'bg-white');
            indicator.innerHTML = '<i class="fas fa-check text-white" style="font-size:0.5rem"></i>';
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
