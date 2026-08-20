@extends('layouts.app')

@section('content')
    @push('head')
        <meta name="description" content="Consulta do Huddle — Round Unidade e Checklists de Pacientes">
    @endpush

    <div class="w-full my-2 text-[#004D9D] font-montserrat" x-data="{ tab: 'round' }">

        {{-- Abas de navegação --}}
        <div class="max-w-full mx-auto px-2 lg:px-3 xl:px-4 pt-4 lg:pt-6">
            <div class="flex items-center gap-1 bg-white/80 border border-gray-200 rounded-xl p-1 shadow-sm w-fit mx-auto sm:mx-0">
                <button @click="tab = 'round'"
                        :class="tab === 'round'
                            ? 'bg-[#004D9D] text-white shadow-md'
                            : 'text-gray-600 hover:bg-gray-100 hover:text-[#004D9D]'"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200">
                    <i class="fas fa-shield-halved text-xs"></i>
                    Round Unidade
                </button>
                <button @click="tab = 'checklist'"
                        :class="tab === 'checklist'
                            ? 'bg-[#004D9D] text-white shadow-md'
                            : 'text-gray-600 hover:bg-gray-100 hover:text-[#004D9D]'"
                        class="flex items-center gap-2 px-4 py-2.5 rounded-lg text-sm font-semibold transition-all duration-200">
                    <i class="fas fa-clipboard-check text-xs"></i>
                    Checklist de Pacientes
                </button>
            </div>
        </div>

        {{-- Conteúdo: Round Unidade --}}
        <div x-show="tab === 'round'" x-cloak>
            @livewire('huddle-safety-report')
        </div>

        {{-- Conteúdo: Checklist de Pacientes --}}
        <div x-show="tab === 'checklist'" x-cloak>
            @livewire('huddle-checklist-report')
        </div>
    </div>
@endsection
