@extends('layouts.app')

@section('content')

<div class="w-full px-3 my-6 text-blue-700">

    <div class="py-12">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center">
                <p class="mt-2 text-4xl sm:text-2xl leading-tight sm:leading-8 font-bold tracking-tight text-santacasa-100 sm:tracking-tight">
                    Bem-vindo ao {{ env('APP_NAME') }}
                </p>
                
                <p class="mt-4 max-w-2xl text-base sm:text-xl text-gray-500 mx-auto text-center">
                    Facilitando uma passagem de plantão, segura, clara e padronizada para garantir a continuidade do cuidado.
                </p>
            </div>

            <div class="mt-16">
                <h2 class="text-lg text-santacasa-100 font-semibold text-center mb-10">
                    Acesso rápido às funções do sistema
                </h2>

                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">

                    {{-- Passagem de Plantão — todos --}}
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('sbar.report') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-hospital text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Passagem de Plantão</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Painel de leitos com SBAR estruturada para enfermeiros</dd>
                        </a>
                    </div>

                    {{-- Meus Setores — todos --}}
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('user.preferences.index') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-cog text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Meus Setores</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Configure quais setores você quer visualizar no sistema</dd>
                        </a>
                    </div>

                    {{-- Feedback — todos --}}
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('feedback') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-comment text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Feedback</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Envie suas sugestões de melhoria ou reporte problemas no sistema</dd>
                        </a>
                    </div>

                    {{-- Manual do Sistema — todos --}}
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('manual.index') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-book text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Manual do Sistema</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Documentação e ajuda do sistema</dd>
                        </a>
                    </div>

                    {{-- Histórico de Avaliações — Coordenador + Administrador --}}
                    @can('ver historico chat')
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('chat.archive.index') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-clock-rotate-left text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Histórico de Avaliações</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Consulte as anotações de plantão por paciente e período</dd>
                        </a>
                    </div>
                    @endcan

                    {{-- Relatório de Pendências — Coordenador + Administrador --}}
                    @can('ver relatorio pendencias')
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('pending.report') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-list-check text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Relatório de Pendências</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Consulte pendências clínicas por hospital e setor em formato tabular</dd>
                        </a>
                    </div>
                    @endcan

                    {{-- Usuários — Coordenador + Administrador --}}
                    @can('ver usuarios')
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('users.index') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Usuários</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Gerencie o cadastro de usuários que acessam o sistema</dd>
                        </a>
                    </div>
                    @endcan

                    {{-- Perfis — Administrador --}}
                    @can('ver perfis')
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('profiles.index') }}" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <svg class="w-6 h-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                                    </svg>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Perfis</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Gerencie perfis e permissões de acesso ao sistema</dd>
                        </a>
                    </div>
                    @endcan

                    {{-- Logs — Administrador --}}
                    @can('ver logs')
                    <div class="border shadow-md p-4 rounded-lg cursor-pointer hover:shadow-2xl bg-white min-h-28 lg:min-h-36 flex items-center">
                        <a href="{{ route('log-viewer.index') }}" target="_blank" class="relative quick-card">
                            <dt>
                                <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                    <i class="fa fa-file-lines text-xl"></i>
                                </div>
                                <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Logs do Sistema</p>
                            </dt>
                            <dd class="mt-2 ml-16 text-base text-gray-500">Visualize registros de erros e eventos do sistema</dd>
                        </a>
                    </div>
                    @endcan

                </div>
            </div>
        </div>
    </div>
</div>

@auth
    @livewire('sector-selector-modal')
@endauth

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

            const redirect = function () {
                if (target === '_blank') {
                    window.open(href, '_blank');
                } else {
                    window.location.href = href;
                }
            };

            setTimeout(redirect, 120);
        });
    });
});
</script>
