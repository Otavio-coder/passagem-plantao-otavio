@extends('layouts.app')

@push('scripts')
@livewireScripts
@endpush

@push('head')
<style>
    #dashboard-page {
        position: fixed;
        top: 4rem; /* navbar h-16 */
        left: 0; right: 0; bottom: 0;
        z-index: 30;
        overflow-y: auto;
        background: #f9fafb; /* bg-gray-50 */
        display: flex;
        flex-direction: column;
    }
</style>
@endpush

@section('content')

<div id="dashboard-page" class="text-blue-700">
    <div class="flex-grow flex items-center justify-center w-full px-3 py-10 sm:py-16">
        <div class="w-full max-w-7xl px-3 sm:px-6 lg:px-8">
            <div class="text-center mb-8 sm:mb-12">
                <h1 class="text-2xl sm:text-4xl md:text-5xl font-bold tracking-tight text-santacasa-100 leading-tight">
                    Bem-vindo ao {{ env('APP_NAME') }}
                </h1>
                <p class="mt-3 sm:mt-4 md:mt-6 max-w-2xl mx-auto text-sm sm:text-base md:text-lg text-gray-600 leading-relaxed">
                    Facilitando uma passagem de plantão segura, clara e padronizada para garantir a continuidade do cuidado.
                </p>
            </div>

            <div class="mt-4 sm:mt-8 md:mt-12">
                <h2 class="text-xs sm:text-base md:text-lg text-santacasa-100 font-semibold text-center mb-3 sm:mb-5 md:mb-8 tracking-wide">
                    Acesso rápido
                </h2>

                <div class="grid grid-cols-2 sm:grid-cols-2 lg:grid-cols-3 gap-2 sm:gap-4 md:gap-6">

                    {{-- Passagem de Plantão — todos --}}
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('sbar.report') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <i class="fa fa-hospital text-sm sm:text-lg"></i>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Passagem de Plantão</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Painel de leitos com SBAR estruturada para enfermeiros</p>
                            </div>
                        </a>
                    </div>

                    {{-- Meus Setores — todos --}}
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('user.preferences.index') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <i class="fa fa-cog text-sm sm:text-lg"></i>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Meus Setores</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Configure quais setores você quer visualizar no sistema</p>
                            </div>
                        </a>
                    </div>

                    {{-- Feedback — todos --}}
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('feedback') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <i class="fa fa-comment text-sm sm:text-lg"></i>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Feedback</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Envie suas sugestões de melhoria ou reporte problemas no sistema</p>
                            </div>
                        </a>
                    </div>

                    {{-- Manual do Sistema — todos --}}
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('manual.index') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <i class="fa fa-book text-sm sm:text-lg"></i>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Manual do Sistema</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Documentação e ajuda do sistema</p>
                            </div>
                        </a>
                    </div>

                    {{-- Histórico de Avaliações — Coordenador + Administrador --}}
                    @can('ver historico chat')
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('admin.dashboard') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <i class="fa fa-clock-rotate-left text-sm sm:text-lg"></i>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Panorama do Sistema</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Panorama de usuários, setores, anotações e passagens de plantão</p>
                            </div>
                        </a>
                    </div>
                    @endcan

                    {{-- Relatório de Pendências --}}
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('pending.report') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <i class="fa fa-list-check text-sm sm:text-lg"></i>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Relatório de Pendências</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Consulte pendências clínicas por hospital e setor em formato tabular</p>
                            </div>
                        </a>
                    </div>

                    {{-- Usuários — Coordenador + Administrador --}}
                    @can('ver usuarios')
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('users.index') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-5 h-5 sm:w-6 sm:h-6">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                </svg>
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Usuários</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Gerencie o cadastro de usuários que acessam o sistema</p>
                            </div>
                        </a>
                    </div>
                    @endcan
                    {{-- Usuários — Coordenador + Administrador --}}
                    @role('Administrador')
                    <div class="border shadow-md rounded-lg cursor-pointer hover:shadow-2xl bg-white">
                        <a href="{{ route('huddle.report') }}" class="quick-card flex flex-col sm:flex-row items-center gap-2 sm:gap-3 p-3 sm:p-4 h-full">
                            <div class="flex-shrink-0 flex items-center justify-center h-10 w-10 sm:h-11 sm:w-11 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                <x-healthicons-o-discharge class="text-sm sm:text-lg" />
                            </div>
                            <div class="text-center sm:text-left">
                                <p class="text-xs sm:text-sm font-semibold text-sky-600 leading-tight">Huddle de Alta</p>
                                <p class="hidden sm:block text-xs text-gray-500 mt-0.5">Em breve — área em desenvolvimento</p>
                            </div>
                        </a>
                    </div>
                    @endrole
                </div>
            </div>
        </div>
    </div>

    {{-- Onboarding: primeiro login sem setores configurados --}}
    @php $needsOnboarding = auth()->check() && ! auth()->user()->hasConfiguredSectors(); @endphp
    @if($needsOnboarding)
        @include('configuration.system.sector-selector-modal', [
            'autoOpen'        => true,
            'mandatory'       => true,
            'initialSelected' => [],
            'sectorsData'     => \App\Models\EMR\Core\Sector::allowedForPreferencesGroupedByHospital(),
        ])
        <div x-data @sectors-configured.window="window.location.reload()"></div>
    @endif

    {{-- Footer do Dashboard --}}
    <footer class="w-full py-3 text-center text-xs text-gray-400">
        <span class="text-gray-500 font-medium">Centro de Inovação</span>
        &middot;
        <a href="{{ env('DEVELOPED_BY_URL') }}" target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center gap-1 text-gray-500 hover:text-blue-600 transition-colors font-medium">
            <span>Desenvolvido por</span>
            <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512" class="w-3 h-3 text-[#0A66C2]" xmlns="http://www.w3.org/2000/svg">
                <path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/>
            </svg>
            {{ env('DEVELOPED_BY') }}
        </a>
    </footer>
</div>

@endsection

{{-- Script de clique rápido --}}
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('a.quick-card').forEach(function (cardLink) {
        cardLink.addEventListener('click', function (e) {
            if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey || (e.button && e.button === 1)) {
                return;
            }
            e.preventDefault();
            const href = cardLink.getAttribute('href');
            const target = cardLink.getAttribute('target');

            if (target === '_blank') {
                window.open(href, '_blank');
                return;
            }

            window.dispatchEvent(new Event('sbar:loading:show'));
            window.location.href = href;
        });
    });
});
</script>
