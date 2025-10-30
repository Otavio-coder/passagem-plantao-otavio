@extends('layouts.app')

@section('content')
    <div class="w-full min-h-screen bg-gradient-to-br from-gray-50 to-blue-50/30 font-montserrat">
        <div class="max-w-7xl mx-auto px-3 sm:px-4 lg:px-6 py-4 sm:py-6 lg:py-8">

            {{-- Header com aviso importante --}}
            <div class="mb-6 sm:mb-8">
                <div class="flex flex-col sm:flex-row items-start gap-3 sm:gap-4 mb-4 sm:mb-6">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 sm:w-12 sm:h-12 bg-[#004D9D] rounded-xl flex items-center justify-center">
                            <svg class="w-5 h-5 sm:w-6 sm:h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z" />
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h1 class="text-2xl sm:text-3xl font-bold text-[#004D9D] mb-1 sm:mb-2">Configuração do Sistema</h1>
                        <p class="text-gray-600 text-xs sm:text-sm">Defina quais hospitais, setores e leitos estarão disponíveis na aplicação</p>
                    </div>
                </div>

                {{-- Aviso Crítico --}}
                <div class="bg-gradient-to-r from-amber-50 to-orange-50 border-l-4 border-amber-500 rounded-lg p-3 sm:p-4 shadow-sm">
                    <div class="flex items-start gap-2 sm:gap-3">
                        <svg class="w-5 h-5 sm:w-6 sm:h-6 text-amber-600 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                        </svg>
                        <div class="flex-1 min-w-0">
                            <h3 class="font-bold text-amber-900 mb-1 text-sm sm:text-base">⚠️ Atenção: Configuração Global do Sistema</h3>
                            <p class="text-xs sm:text-sm text-amber-800 leading-relaxed">
                                As configurações realizadas aqui <strong>afetam TODOS os usuários do sistema</strong> em tempo real.
                                Qualquer alteração será aplicada imediatamente para toda a equipe.
                                <strong class="block mt-1">Certifique-se de que está adicionando itens, não removendo acidentalmente todos os registros.</strong>
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Success Message --}}
            @if(session('success'))
                <div class="bg-gradient-to-r from-green-50 to-emerald-50 border-l-4 border-green-500 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6 shadow-sm animate-[fadeIn_0.3s_ease-in]">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-green-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-green-800 font-medium text-sm sm:text-base">{{ session('success') }}</span>
                    </div>
                </div>
            @endif

            {{-- Error Message --}}
            @if(session('error'))
                <div class="bg-gradient-to-r from-red-50 to-pink-50 border-l-4 border-red-500 rounded-lg p-3 sm:p-4 mb-4 sm:mb-6 shadow-sm">
                    <div class="flex items-center gap-2 sm:gap-3">
                        <svg class="w-4 h-4 sm:w-5 sm:h-5 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span class="text-red-800 font-medium text-sm sm:text-base">{{ session('error') }}</span>
                    </div>
                </div>
            @endif

            <form method="POST" action="{{ route('system-configuration.update') }}" class="space-y-4 sm:space-y-6" id="configForm">
                @csrf

                @php
                    $hospitalColors = [
                        '1' => ['color' => '#8C3134', 'name' => 'Santo Antônio'],
                        '8' => ['color' => '#42909A', 'name' => 'São Francisco'],
                        '2' => ['color' => '#A96538', 'name' => 'São José'],
                        '6' => ['color' => '#3E6B35', 'name' => 'Pereira Filho'],
                        '4' => ['color' => '#4586B4', 'name' => 'Dom Scherer'],
                        '3' => ['color' => '#9574A1', 'name' => 'Santa Rita'],
                        '25' => ['color' => '#CE7D74', 'name' => 'Nora Teixeira'],
                        '7' => ['color' => '#563174', 'name' => 'Santa Clara'],
                        '18' => ['color' => '#073772', 'name' => 'Dom João Becker'],
                    ];
                @endphp

                {{-- Hospitais --}}
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#004D9D] to-[#0071B9] px-4 sm:px-6 py-3 sm:py-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-base sm:text-lg font-bold text-white">Hospitais</h2>
                                    <p class="text-xs text-blue-100 hidden sm:block">Selecione os hospitais que estarão disponíveis</p>
                                </div>
                            </div>
                            <span class="px-2 sm:px-3 py-1 bg-white/20 rounded-full text-white text-xs sm:text-sm font-semibold whitespace-nowrap" id="hospitalCount">0 selecionado(s)</span>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-3">
                            @foreach($hospitals as $hospital)
                                @php
                                    $code = data_get($hospital, 'code');
                                    $hospitalColor = $hospitalColors[$code]['color'] ?? '#004D9D';
                                    $isChecked = in_array($code, $selectedHospitals);
                                @endphp
                                <label class="group relative flex items-center gap-2 sm:gap-3 p-3 sm:p-4 border-2 rounded-lg sm:rounded-xl cursor-pointer transition-all duration-200 hover:shadow-md bg-white
                                {{ $isChecked ? 'shadow-md' : 'border-gray-200 hover:border-gray-300' }}"
                                       style="border-color: {{ $isChecked ? $hospitalColor : '' }}; background-color: {{ $isChecked ? $hospitalColor . '10' : '' }};"
                                       data-hospital-code="{{ $code }}"
                                >
                                    <input
                                        type="checkbox"
                                        name="hospital_codes[]"
                                        value="{{ $code }}"
                                        @if($isChecked) checked @endif
                                        onchange="updateCounts()"
                                        class="w-4 h-4 sm:w-5 sm:h-5 border-gray-300 rounded focus:ring-2 focus:ring-offset-2 cursor-pointer flex-shrink-0"
                                        style="color: {{ $hospitalColor }};"
                                    >
                                    <div class="flex-1 min-w-0">
                                        <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate">{{ data_get($hospital, 'name') }}</p>
                                        <p class="text-[10px] sm:text-xs text-gray-500">Código: {{ $code }}</p>
                                    </div>
                                    <div class="absolute top-2 right-2 w-3 h-3 rounded-full {{ $isChecked ? 'opacity-100' : 'opacity-0' }} transition-opacity" style="background-color: {{ $hospitalColor }};"></div>
                                </label>
                            @endforeach
                        </div>
                        <p class="text-[10px] sm:text-xs text-gray-500 mt-3 sm:mt-4 flex items-center gap-2">
                            <svg class="w-3 h-3 sm:w-4 sm:h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            Cada hospital possui sua cor identificadora
                        </p>
                    </div>
                </div>

                {{-- Setores --}}
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#004D9D] to-[#0071B9] px-4 sm:px-6 py-3 sm:py-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2V6zM14 6a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2V6zM4 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2H6a2 2 0 01-2-2v-2zM14 16a2 2 0 012-2h2a2 2 0 012 2v2a2 2 0 01-2 2h-2a2 2 0 01-2-2v-2z" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-base sm:text-lg font-bold text-white">Setores</h2>
                                    <p class="text-xs text-blue-100 hidden sm:block">Setores disponíveis nos hospitais selecionados</p>
                                </div>
                            </div>
                            <span class="px-2 sm:px-3 py-1 bg-white/20 rounded-full text-white text-xs sm:text-sm font-semibold whitespace-nowrap" id="sectorCount">0 selecionado(s)</span>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="mb-3 sm:mb-4 flex flex-wrap items-center gap-2 sm:gap-3">
                            <button
                                type="button"
                                onclick="selectAllVisible('sectors', true)"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-[#0071B9] text-white rounded-lg text-xs sm:text-sm font-medium hover:bg-[#004D9D] transition-colors duration-200 shadow-sm hover:shadow-md flex items-center gap-1.5 sm:gap-2"
                            >
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Selecionar Todos
                            </button>
                            <button
                                type="button"
                                onclick="selectAllVisible('sectors', false)"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-xs sm:text-sm font-medium hover:bg-gray-300 transition-colors duration-200 flex items-center gap-1.5 sm:gap-2"
                            >
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Desselecionar Todos
                            </button>
                        </div>
                        <div class="max-h-64 sm:max-h-96 overflow-y-auto border border-gray-200 rounded-lg sm:rounded-xl p-3 sm:p-4 bg-gray-50/50 custom-scrollbar">
                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 sm:gap-3">
                                @foreach($sectors as $sector)
                                    @php
                                        $hospitalId = data_get($sector, 'hospital_id');
                                        $sectorColor = $hospitalColors[$hospitalId]['color'] ?? '#004D9D';
                                        $isChecked = in_array(data_get($sector, 'code'), $selectedSectors);
                                    @endphp
                                    <label class="group relative flex items-center gap-2 sm:gap-3 p-2.5 sm:p-3 border-2 rounded-lg cursor-pointer transition-all duration-200 hover:shadow-sm bg-white sector-checkbox
                                    {{ $isChecked ? 'shadow-sm' : 'border-gray-200' }}"
                                           style="border-color: {{ $isChecked ? $sectorColor : '' }}; background-color: {{ $isChecked ? $sectorColor . '08' : '' }}; {{ !in_array($hospitalId, $selectedHospitals) ? 'display: none;' : '' }}"
                                           data-sector-hospital="{{ $hospitalId }}"
                                    >
                                        <input
                                            type="checkbox"
                                            name="sector_codes[]"
                                            value="{{ data_get($sector, 'code') }}"
                                            @if($isChecked) checked @endif
                                            onchange="updateCounts()"
                                            class="w-4 h-4 border-gray-300 rounded focus:ring-2 focus:ring-offset-2 cursor-pointer flex-shrink-0"
                                            style="color: {{ $sectorColor }};"
                                        >
                                        <div class="flex-1 min-w-0">
                                            <p class="font-medium text-gray-900 text-xs sm:text-sm truncate">{{ data_get($sector, 'name') }}</p>
                                            <div class="flex items-center gap-1 mt-0.5">
                                                <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $sectorColor }};"></div>
                                                <p class="text-[10px] sm:text-xs text-gray-500">Código: {{ data_get($sector, 'code') }}</p>
                                            </div>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Leitos --}}
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg border border-gray-100 overflow-hidden">
                    <div class="bg-gradient-to-r from-[#004D9D] to-[#0071B9] px-4 sm:px-6 py-3 sm:py-4">
                        <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3">
                            <div class="flex items-center gap-2 sm:gap-3">
                                <div class="w-8 h-8 sm:w-10 sm:h-10 bg-white/20 rounded-lg flex items-center justify-center flex-shrink-0">
                                    <svg class="w-4 h-4 sm:w-5 sm:h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6" />
                                    </svg>
                                </div>
                                <div class="min-w-0">
                                    <h2 class="text-base sm:text-lg font-bold text-white">Leitos</h2>
                                    <p class="text-xs text-blue-100 hidden sm:block">Leitos disponíveis nos setores selecionados</p>
                                </div>
                            </div>
                            <span class="px-2 sm:px-3 py-1 bg-white/20 rounded-full text-white text-xs sm:text-sm font-semibold whitespace-nowrap" id="bedCount">0 selecionado(s)</span>
                        </div>
                    </div>
                    <div class="p-4 sm:p-6">
                        <div class="mb-3 sm:mb-4 flex flex-wrap items-center gap-2 sm:gap-3">
                            <button
                                type="button"
                                onclick="selectAllVisible('beds', true)"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-[#0071B9] text-white rounded-lg text-xs sm:text-sm font-medium hover:bg-[#004D9D] transition-colors duration-200 shadow-sm hover:shadow-md flex items-center gap-1.5 sm:gap-2"
                            >
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                </svg>
                                Selecionar Todos
                            </button>
                            <button
                                type="button"
                                onclick="selectAllVisible('beds', false)"
                                class="px-3 sm:px-4 py-1.5 sm:py-2 bg-gray-200 text-gray-700 rounded-lg text-xs sm:text-sm font-medium hover:bg-gray-300 transition-colors duration-200 flex items-center gap-1.5 sm:gap-2"
                            >
                                <svg class="w-3 h-3 sm:w-4 sm:h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Desselecionar Todos
                            </button>
                        </div>
                        <div class="max-h-64 sm:max-h-96 overflow-y-auto border border-gray-200 rounded-lg sm:rounded-xl p-3 sm:p-4 bg-gray-50/50 custom-scrollbar">
                            @php
                                // Mapear setor para hospital para pegar a cor
                                $sectorToHospital = [];
                                foreach($sectors as $s) {
                                    $sectorToHospital[data_get($s, 'code')] = data_get($s, 'hospital_id');
                                }
                            @endphp
                            <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-5 gap-2">
                                @foreach($bedunits as $bed)
                                    @php
                                        $sectorCode = data_get($bed, 'cd_setor_atendimento');
                                        $hospitalId = $sectorToHospital[$sectorCode] ?? null;
                                        $bedColor = $hospitalId ? ($hospitalColors[$hospitalId]['color'] ?? '#004D9D') : '#004D9D';
                                        $bedValue = data_get($bed, 'code') . '|' . $sectorCode;
                                        $isChecked = in_array($bedValue, $selectedBeds);
                                    @endphp
                                    <label class="group relative flex flex-col gap-1 p-2.5 sm:p-3 border-2 rounded-lg cursor-pointer transition-all duration-200 hover:shadow-sm bg-white bed-checkbox
                                    {{ $isChecked ? 'shadow-sm' : 'border-gray-200' }}"
                                           style="border-color: {{ $isChecked ? $bedColor : '' }}; background-color: {{ $isChecked ? $bedColor . '08' : '' }}; {{ !in_array($sectorCode, $selectedSectors) ? 'display: none;' : '' }}"
                                           data-bed-sector="{{ $sectorCode }}"
                                    >
                                        <div class="flex items-center gap-1.5 sm:gap-2">
                                            <input
                                                type="checkbox"
                                                name="bed_codes[]"
                                                value="{{ $bedValue }}"
                                                @if($isChecked) checked @endif
                                                onchange="updateCounts()"
                                                class="w-4 h-4 border-gray-300 rounded focus:ring-2 focus:ring-offset-2 cursor-pointer flex-shrink-0"
                                                style="color: {{ $bedColor }};"
                                            >
                                            <p class="font-semibold text-gray-900 text-xs sm:text-sm truncate flex-1">{{ data_get($bed, 'name') }}</p>
                                        </div>
                                        <div class="flex items-center gap-1 pl-5 sm:pl-6">
                                            <div class="w-2 h-2 rounded-full flex-shrink-0" style="background-color: {{ $bedColor }};"></div>
                                            <p class="text-[10px] sm:text-xs text-gray-500">Setor {{ $sectorCode }}</p>
                                        </div>
                                    </label>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Legenda e Botões de Ação --}}
                <div class="bg-white rounded-xl sm:rounded-2xl shadow-lg border border-gray-100 p-4 sm:p-6">
                    {{-- Legenda de Cores dos Hospitais --}}
                    <div class="mb-4 sm:mb-6 pb-4 sm:pb-6 border-b border-gray-200">
                        <h3 class="text-sm font-bold text-gray-700 mb-3 flex items-center gap-2">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 21a4 4 0 01-4-4V5a2 2 0 012-2h4a2 2 0 012 2v12a4 4 0 01-4 4zm0 0h12a2 2 0 002-2v-4a2 2 0 00-2-2h-2.343M11 7.343l1.657-1.657a2 2 0 012.828 0l2.829 2.829a2 2 0 010 2.828l-8.486 8.485M7 17h.01" />
                            </svg>
                            Legenda de Cores
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-5 gap-2">
                            @foreach($hospitalColors as $code => $info)
                                <div class="flex items-center gap-2 p-2 rounded-lg bg-gray-50">
                                    <div class="w-3 h-3 rounded-full flex-shrink-0" style="background-color: {{ $info['color'] }};"></div>
                                    <span class="text-xs text-gray-700 truncate">{{ $info['name'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>

                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <div class="flex items-center gap-2 sm:gap-3 w-full sm:w-auto">
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                            </svg>
                            <p class="text-xs sm:text-sm text-gray-600" id="totalSummary">
                                Total: <span class="font-semibold">0</span> hospitais,
                                <span class="font-semibold">0</span> setores,
                                <span class="font-semibold">0</span> leitos
                            </p>
                        </div>
                        <button
                            type="submit"
                            class="w-full sm:w-auto inline-flex items-center justify-center px-6 sm:px-8 py-2.5 sm:py-3 rounded-xl text-white transition-all duration-200 bg-gradient-to-r from-[#0071B9] to-[#004D9D] hover:shadow-xl hover:scale-105 focus:outline-none focus:ring-2 focus:ring-[#0071B9]/50 focus:ring-offset-2 text-sm sm:text-base font-bold"
                            onclick="return confirm('Confirma salvar as configurações? Isso afetará TODOS os usuários imediatamente.');"
                        >
                            <svg class="w-4 h-4 sm:w-5 sm:h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                            </svg>
                            Salvar Configurações
                        </button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <style>
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(-10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .custom-scrollbar::-webkit-scrollbar {
            width: 8px;
            height: 8px;
        }

        .custom-scrollbar::-webkit-scrollbar-track {
            background: #f1f5f9;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 4px;
        }

        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: #94a3b8;
        }
    </style>

    <script>
        // Dados do servidor
        const sectors = @json($sectors);
        const bedunits = @json($bedunits);

        // Criar mapa de setor -> hospital
        const sectorToHospital = {};
        sectors.forEach(s => {
            sectorToHospital[s.code] = s.hospital_id;
        });

        // Função para atualizar contadores
        function updateCounts() {
            // Contar hospitais
            const hospitalCount = document.querySelectorAll('input[name="hospital_codes[]"]:checked').length;
            document.getElementById('hospitalCount').textContent = hospitalCount + ' selecionado(s)';

            // Contar setores visíveis e marcados
            const visibleSectors = Array.from(document.querySelectorAll('.sector-checkbox'))
                .filter(label => window.getComputedStyle(label).display !== 'none');
            const checkedSectors = visibleSectors.filter(label =>
                label.querySelector('input[type="checkbox"]').checked
            );
            const sectorCount = checkedSectors.length;
            document.getElementById('sectorCount').textContent = sectorCount + ' selecionado(s)';

            // Contar leitos visíveis e marcados
            const visibleBeds = Array.from(document.querySelectorAll('.bed-checkbox'))
                .filter(label => window.getComputedStyle(label).display !== 'none');
            const checkedBeds = visibleBeds.filter(label =>
                label.querySelector('input[type="checkbox"]').checked
            );
            const bedCount = checkedBeds.length;
            document.getElementById('bedCount').textContent = bedCount + ' selecionado(s)';

            // Atualizar resumo total
            document.getElementById('totalSummary').innerHTML = `
                Total: <span class="font-semibold">${hospitalCount}</span> hospitais,
                <span class="font-semibold">${sectorCount}</span> setores,
                <span class="font-semibold">${bedCount}</span> leitos
            `;
        }

        // Função para atualizar visibilidade dos setores
        function updateSectorVisibility() {
            const selectedHospitals = Array.from(
                document.querySelectorAll('input[name="hospital_codes[]"]:checked')
            ).map(cb => cb.value);

            document.querySelectorAll('.sector-checkbox').forEach(label => {
                const checkbox = label.querySelector('input');
                const sectorCode = checkbox.value;
                const sectorHospital = sectorToHospital[sectorCode];

                if (selectedHospitals.length === 0 || selectedHospitals.includes(sectorHospital)) {
                    label.style.display = 'flex';
                } else {
                    label.style.display = 'none';
                    checkbox.checked = false;
                }
            });

            updateBedVisibility();
            updateCounts();
        }

        // Função para atualizar visibilidade dos leitos
        function updateBedVisibility() {
            const selectedSectors = Array.from(document.querySelectorAll('.sector-checkbox'))
                .filter(label => window.getComputedStyle(label).display !== 'none')
                .map(label => label.querySelector('input[type="checkbox"]'))
                .filter(cb => cb.checked)
                .map(cb => cb.value);

            document.querySelectorAll('.bed-checkbox').forEach(label => {
                const checkbox = label.querySelector('input');
                const bedValue = checkbox.value;
                const bedSector = bedValue.split('|')[1];

                if (selectedSectors.length === 0 || selectedSectors.includes(bedSector)) {
                    label.style.display = 'flex';
                } else {
                    label.style.display = 'none';
                    checkbox.checked = false;
                }
            });

            updateCounts();
        }

        // Função para selecionar/desselecionar todos os itens visíveis
        function selectAllVisible(type, select) {
            let selector = '';
            if (type === 'sectors') {
                selector = '.sector-checkbox';
            } else if (type === 'beds') {
                selector = '.bed-checkbox';
            }

            const visibleLabels = Array.from(document.querySelectorAll(selector))
                .filter(label => window.getComputedStyle(label).display !== 'none');

            visibleLabels.forEach(label => {
                const checkbox = label.querySelector('input[type="checkbox"]');
                if (checkbox) {
                    checkbox.checked = select;
                }
            });

            if (type === 'sectors') {
                updateBedVisibility();
            }

            updateCounts();
        }

        // Event listeners
        document.addEventListener('DOMContentLoaded', function() {
            // Listener para hospitais
            document.querySelectorAll('input[name="hospital_codes[]"]').forEach(cb => {
                cb.addEventListener('change', updateSectorVisibility);
            });

            // Listener para setores
            document.querySelectorAll('input[name="sector_codes[]"]').forEach(cb => {
                cb.addEventListener('change', updateBedVisibility);
            });

            // Inicializar
            updateSectorVisibility();
            updateCounts();
        });
    </script>
@endsection
