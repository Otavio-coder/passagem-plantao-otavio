@extends( 'layouts.app' )

@section( 'content' )

    <div class="w-full px-3 my-2 text-blue-700">

        <div class="py-12">
            <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
                <div class="text-center">
                    <p class="mt-2 text-2xl leading-8 font-bold tracking-tight text-santacasa-100 sm:text-4xl sm:tracking-tight">Bem vindo ao {{ env( 'APP_NAME' ) }}</p>
                    <p class="mt-4 max-w-2xl text-xl text-gray-500 lg:mx-auto">Facilitando uma passagem de plantão segura, clara e padronizada para garantir a continuidade do cuidado.</p>
                </div>

                <div class="mt-16">
                    <h2 class="text-lg text-santacasa-100 font-semibold text-center mb-10">Acesso rápido às funções do sistema</h2>
                    <dl class="space-y-10 md:space-y-0 md:grid md:grid-cols-2 md:gap-x-8 md:gap-y-10">

                        <div class="border shadow-md p-2 rounded-lg cursor-pointer hover:shadow-2xl bg-white lg:h-20">
                            <a href="{{ route( 'users.index' ) }}" class="relative">
                                <dt>
                                    <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="w-6 h-6">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                    </div>
                                    <p class="ml-16 text-lg leading-6 font-medium text-santacasa-100">Menu Usuários</p>
                                </dt>
                                <dd class="mt-2 ml-16 text-base text-gray-500">Gerencie o cadastro de usuários que acessam este sistema</dd>
                            </a>
                        </div>

                        <div class="border shadow-md p-2 rounded-lg cursor-pointer hover:shadow-2xl bg-white lg:h-20">
                            <a href="{{ route( 'profiles.index' ) }}" class="relative">
                                <dt>
                                    <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                        <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                                        </svg>
                                    </div>
                                    <p class="ml-16 text-lg leading-6 font-medium text-sky-600">Menu Perfis</p>
                                </dt>
                                <dd class="mt-2 ml-16 text-base text-gray-500">Gerencie o cadastro de perfis no sistema</dd>
                            </a>
                        </div>
                        <div class="border shadow-md p-2 rounded-lg cursor-pointer hover:shadow-2xl bg-white lg:h-20">
                            <a href="{{ route('sbar.report') }}" class="relative">
                                <dt>
                                    <div class="absolute flex items-center justify-center h-12 w-12 rounded-full bg-gradient-to-tr from-santacasa-100 to-santacasa-default text-white">
                                        <svg class="w-6 h-6" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 14h.01M12 14h.01M15 11h.01M12 11h.01M9 11h.01M7 21h10a1 1 0 0 0 1-1V5a1 1 0 0 0-1-1H7a1 1 0 0 0-1 1v15a1 1 0 0 0 1 1Z"/>
                                        </svg>
                                    </div>
                                    <p class="ml-16 text-lg leading-6 font-medium text-santacasa-100">SBAR - Passagem de Plantão</p>
                                </dt>
                                <dd class="mt-2 ml-16 text-base text-gray-500">Sistema de passagem de plantão estruturada para enfermeiros</dd>
                            </a>
                        </div>
                    </dl>
                </div>
            </div>
        </div>

    </div>

@endsection
