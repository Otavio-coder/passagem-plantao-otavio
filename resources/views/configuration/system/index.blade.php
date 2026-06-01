@extends('layouts.app')

@push('head')
<style>
    #sector-config-page {
        position: fixed;
        top: 4rem;
        left: 0; right: 0; bottom: 0;
        display: flex;
        flex-direction: column;
        overflow: hidden;
    }
    .tab-panel { display: none; }
    .tab-panel.active { display: flex; flex-direction: column; flex: 1 1 0; min-height: 0; }
    #tab-leitos.active { display: flex; flex-direction: column; flex: 1 1 0; min-height: 0; overflow: hidden; }
</style>
@endpush

@section('content')
<div id="sector-config-page" class="px-4 pt-4 pb-2 bg-gray-50 gap-4">

    {{-- Header --}}
    <div class="flex items-center justify-between flex-shrink-0">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-xl bg-[#004D9D]/10 flex items-center justify-center flex-shrink-0">
                <i class="fas fa-hospital text-[#004D9D]"></i>
            </div>
            <div>
                <h1 class="text-xl font-bold text-gray-900 leading-tight">Meus Setores</h1>
                <p class="text-sm text-gray-500 mt-0.5">Preferências de setores e leitos de passagem</p>
            </div>
        </div>
        <a href="{{ route('home') }}"
           class="inline-flex items-center gap-1.5 px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-gray-700 hover:bg-gray-100 rounded-lg transition-colors">
            <i class="fas fa-arrow-left"></i>
            <span class="hidden sm:inline">Voltar</span>
        </a>
    </div>

    @if(session('success'))
        <div class="flex-shrink-0 bg-emerald-50 border border-emerald-200 rounded-xl px-4 py-2.5 flex items-center gap-2">
            <i class="fas fa-check-circle text-emerald-600 text-sm"></i>
            <span class="text-sm text-emerald-800">{{ session('success') }}</span>
        </div>
    @endif
    @if(session('error'))
        <div class="flex-shrink-0 bg-red-50 border border-red-200 rounded-xl px-4 py-2.5 flex items-center gap-2">
            <i class="fas fa-exclamation-circle text-red-600 text-sm"></i>
            <span class="text-sm text-red-800">{{ session('error') }}</span>
        </div>
    @endif

    {{-- Card --}}
    <div class="flex flex-col flex-1 min-h-0 bg-white rounded-xl shadow-sm border border-gray-200 overflow-hidden">

        {{-- Tab bar --}}
        @php $isNurse = auth()->user()->isNurse(); @endphp
        <div class="bg-[#004D9D] px-4 pt-3 pb-0 flex-shrink-0">
            <div class="flex items-center justify-between mb-3">
                <div>
                    <p class="text-sm font-semibold text-white">Preferências</p>
                    <p class="text-xs text-white/60 mt-0.5">Selecione seus setores e leitos de atuação</p>
                </div>
                <div id="sector-badge" class="flex items-center gap-2 bg-white/10 px-3 py-1.5 rounded-lg" style="{{ $isNurse ? 'display:none' : '' }}">
                    <span id="headerCount" class="text-white font-bold text-xl">{{ count($selectedSectors) }}</span>
                    <span class="text-white/70 text-xs">selecionado(s)</span>
                </div>
            </div>
            <div class="flex gap-1">
                <button onclick="switchTab('setores')" id="tab-btn-setores"
                        class="tab-btn px-5 py-3 text-sm font-semibold rounded-t-lg transition-colors {{ $isNurse ? 'text-white/60 hover:text-white hover:bg-white/10' : 'bg-white text-[#004D9D]' }}">
                    <i class="fas fa-hospital mr-1.5"></i>Meus Setores
                </button>
                @if($isNurse)
                    <button onclick="switchTab('leitos')" id="tab-btn-leitos"
                            class="tab-btn px-5 py-3 text-sm font-semibold rounded-t-lg transition-colors bg-white text-[#004D9D]">
                        <i class="fas fa-bed mr-1.5"></i>Meus Leitos
                    </button>
                @endif
            </div>
        </div>

        {{-- Tab: Meus Setores --}}
        <div id="tab-setores" class="tab-panel {{ $isNurse ? '' : 'active' }}">
            <form method="POST" action="{{ route('user.preferences.update') }}" class="flex flex-col flex-1 min-h-0">
                @csrf
                {{-- Search --}}
                <div class="px-4 py-2.5 border-b border-gray-100 bg-gray-50 flex-shrink-0">
                    <div class="relative">
                        <i class="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-xs"></i>
                        <input type="text" id="searchInput" placeholder="Buscar setor ou hospital..."
                               class="w-full pl-9 pr-4 py-2 border border-gray-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-[#004D9D]/30 focus:border-[#004D9D]"
                               onkeyup="filterSectors(this.value)">
                    </div>
                </div>
                {{-- List --}}
                <div class="flex-1 min-h-0 p-3 overflow-y-auto space-y-2">
                    @forelse($hospitalSections as $hospital)
                        <div class="hospital-group border border-gray-200 rounded-xl overflow-hidden"
                             data-hospital-name="{{ $hospital['name_lower'] }}">
                            <div class="flex items-center justify-between px-4 py-4 bg-gray-50 cursor-pointer hover:bg-gray-100 transition-colors"
                                 onclick="toggleAccordion('{{ $hospital['code'] }}')">
                                <div class="flex items-center gap-2 min-w-0">
                                    <div class="w-7 h-7 rounded-lg flex items-center justify-center text-white text-xs font-bold flex-shrink-0"
                                         style="background:{{ $hospital['color'] }}">{{ $hospital['icon_label'] }}</div>
                                    <div class="min-w-0">
                                        <h3 class="font-semibold text-gray-800 truncate text-sm">{{ $hospital['name'] }}</h3>
                                        <span class="text-xs text-gray-400">{{ $hospital['total_sectors'] }} setores</span>
                                    </div>
                                </div>
                                <div class="flex items-center gap-2 ml-2 flex-shrink-0">
                                    @if($hospital['selected_count'] > 0)
                                        <span class="bg-[#004D9D] text-white text-xs px-2 py-0.5 rounded-full">{{ $hospital['selected_count'] }}</span>
                                    @endif
                                    <button type="button"
                                            onclick="event.stopPropagation(); toggleHospital('{{ $hospital['code'] }}')"
                                            class="text-sm font-medium px-3 py-2 rounded-lg bg-white border border-gray-200 hover:bg-gray-50 text-gray-600">
                                        Todos
                                    </button>
                                    <i id="icon-{{ $hospital['code'] }}"
                                       class="fas fa-chevron-down text-gray-400 transition-transform duration-200 text-xs {{ $hospital['is_expanded'] ? 'rotate-180' : '' }}"></i>
                                </div>
                            </div>
                            <div id="content-{{ $hospital['code'] }}"
                                 class="{{ $hospital['is_expanded'] ? '' : 'hidden' }} border-t border-gray-100">
                                <div class="p-3 bg-white">
                                    <div class="flex flex-wrap gap-1.5">
                                        @foreach($hospital['sectors'] as $sector)
                                            <label class="sector-item inline-flex items-center gap-2 px-3 py-3 rounded-xl border cursor-pointer transition-all hover:shadow-sm {{ $sector['is_checked'] ? 'bg-blue-50 border-blue-300' : 'bg-white border-gray-200 hover:bg-gray-50' }}"
                                                   data-sector-name="{{ $sector['name_lower'] }}">
                                                <input type="checkbox" name="sector_codes[]" value="{{ $sector['code'] }}"
                                                       {{ $sector['is_checked'] ? 'checked' : '' }}
                                                       class="hidden" onchange="updateCheckbox(this)">
                                                <div data-indicator class="w-3.5 h-3.5 rounded border flex items-center justify-center flex-shrink-0 {{ $sector['is_checked'] ? 'bg-[#004D9D] border-[#004D9D]' : 'border-gray-300 bg-white' }}">
                                                    @if($sector['is_checked'])
                                                        <i class="fas fa-check text-white" style="font-size:.5rem"></i>
                                                    @endif
                                                </div>
                                                <span class="text-sm text-gray-700 whitespace-nowrap">{{ $sector['name'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-12 text-gray-400">
                            <i class="fas fa-inbox text-4xl mb-3"></i>
                            <p class="text-sm">Nenhum setor disponível.</p>
                        </div>
                    @endforelse
                </div>
                {{-- Footer --}}
                <div class="px-4 py-3 bg-gray-50 border-t border-gray-200 flex-shrink-0">
                    <div class="flex items-center justify-between gap-3">
                        <div>
                            <span id="footerCount" class="font-bold text-[#004D9D] text-lg">{{ count($selectedSectors) }}</span>
                            <span class="text-sm text-gray-500 ml-1">setor(es) selecionado(s)</span>
                        </div>
                        <div class="flex gap-2">
                            <a href="{{ route('home') }}"
                               class="px-4 py-1.5 text-sm font-medium text-gray-500 hover:bg-gray-200 rounded-lg transition-colors">
                                Cancelar
                            </a>
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 px-5 py-1.5 text-sm font-semibold text-white bg-[#004D9D] hover:bg-[#003d7a] rounded-lg transition-colors shadow-sm">
                                <i class="fas fa-save"></i>
                                Salvar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>

        {{-- Tab: Meus Leitos (só para enfermeiros) --}}
        @if($isNurse)
            <div id="tab-leitos" class="tab-panel active">
                @livewire('nurse-handover-setup')
            </div>
        @endif

    </div>
</div>

<script>
    function switchTab(name) {
        document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
        document.querySelectorAll('.tab-btn').forEach(b => {
            b.classList.remove('bg-white', 'text-[#004D9D]');
            b.classList.add('text-white/60', 'hover:text-white', 'hover:bg-white/10');
        });
        document.getElementById('tab-' + name).classList.add('active');
        const btn = document.getElementById('tab-btn-' + name);
        btn.classList.add('bg-white', 'text-[#004D9D]');
        btn.classList.remove('text-white/60', 'hover:text-white', 'hover:bg-white/10');
        document.getElementById('sector-badge').style.display = name === 'setores' ? 'flex' : 'none';
    }

    function toggleAccordion(code) {
        const c = document.getElementById('content-' + code);
        const i = document.getElementById('icon-' + code);
        const hidden = c.classList.toggle('hidden');
        i.classList.toggle('rotate-180', !hidden);
    }

    function toggleHospital(code) {
        const boxes = document.querySelectorAll('#content-' + code + ' input[type="checkbox"]');
        const all = Array.from(boxes).every(b => b.checked);
        boxes.forEach(b => { b.checked = !all; updateCheckbox(b); });
    }

    function updateCheckbox(cb) {
        const label = cb.closest('label');
        const ind = label.querySelector('[data-indicator]');
        if (cb.checked) {
            label.classList.add('bg-blue-50', 'border-blue-300');
            label.classList.remove('bg-white', 'border-gray-200');
            ind.classList.add('bg-[#004D9D]', 'border-[#004D9D]');
            ind.classList.remove('border-gray-300', 'bg-white');
            ind.innerHTML = '<i class="fas fa-check text-white" style="font-size:.5rem"></i>';
        } else {
            label.classList.remove('bg-blue-50', 'border-blue-300');
            label.classList.add('bg-white', 'border-gray-200');
            ind.classList.remove('bg-[#004D9D]', 'border-[#004D9D]');
            ind.classList.add('border-gray-300', 'bg-white');
            ind.innerHTML = '';
        }
        const n = document.querySelectorAll('input[name="sector_codes[]"]:checked').length;
        document.getElementById('headerCount').textContent = n;
        document.getElementById('footerCount').textContent = n;
    }

    function filterSectors(query) {
        const term = query.toLowerCase().trim();
        document.querySelectorAll('.hospital-group').forEach(h => {
            const hName = h.dataset.hospitalName;
            let vis = false;
            h.querySelectorAll('.sector-item').forEach(s => {
                const match = !term || s.dataset.sectorName.includes(term) || hName.includes(term);
                s.style.display = match ? 'inline-flex' : 'none';
                if (match) vis = true;
            });
            h.style.display = vis ? 'block' : 'none';
        });
    }
</script>
@endsection
