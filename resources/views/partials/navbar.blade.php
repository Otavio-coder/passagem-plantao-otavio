<nav class="bg-gradient-to-l from-santacasa-100 via-santacasa-default to-santacasa-300 border-b shadow-md">
    <div class="mx-auto px-2 sm:px-6 lg:px-8">
        <div class="flex items-center justify-between h-16">
            <!-- Mobile menu button-->
            <div class="flex items-center lg:hidden">
                <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-white hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" aria-controls="mobile-menu" aria-expanded="false" onclick="toggleNavbar('mobile-menu')">
                    <span class="sr-only">Abrir menu</span>
                    <svg class="block h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" /></svg>
                </button>
            </div>

            <!-- Logo -->
            <div class="flex-shrink-0 flex items-center">
                <img class="block lg:hidden h-12 w-auto" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="Logo Santa Casa" loading="lazy">
                <img class="hidden lg:block h-12 w-auto" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="Logo Santa Casa" loading="lazy">
            </div>

            <!-- Desktop Menu -->
            <div class="hidden lg:flex flex-1 items-center justify-between">
                <div class="flex items-center space-x-2 xl:space-x-4 ml-6">
                    <a href="{{ route('home') }}" id="nav-inicio-desktop"
                       class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-2 xl:px-3 py-1.5 xl:py-2 text-xs xl:text-sm font-medium flex items-center gap-1.5 xl:gap-2 min-w-[90px]">
                        <i class="fa fa-home"></i> <span class="hidden sm:inline">Início</span>
                    </a>

                    <a href="{{ route('sbar.report') }}" id="nav-sbar-desktop"
                       class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-2 xl:px-3 py-1.5 xl:py-2 text-xs xl:text-sm font-medium flex items-center gap-1.5 xl:gap-2 min-w-[90px]">
                        <i class="fa fa-hospital"></i> <span class="hidden sm:inline">Passagem</span>
                    </a>

                    <a href="{{ route('user.preferences.index') }}" id="nav-preferences-desktop"
                       class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-2 xl:px-3 py-1.5 xl:py-2 text-xs xl:text-sm font-medium flex items-center gap-1.5 xl:gap-2 min-w-[90px]">
                        <i class="fa fa-cog"></i> <span class="hidden sm:inline">Meus Setores</span>
                    </a>

                    <a href="{{ route('feedback') }}" id="nav-feedback-desktop"
                       class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-2 xl:px-3 py-1.5 xl:py-2 text-xs xl:text-sm font-medium flex items-center gap-1.5 xl:gap-2 min-w-[90px]">
                        <i class="fa fa-comment"></i> <span class="hidden sm:inline">Feedback</span>
                    </a>

                    @canany(['ver usuarios', 'ver perfis', 'ver logs', 'configurar sistema'])
                        <div class="relative group">
                            <button type="button" id="nav-administracao-desktop"
                                    class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-2 xl:px-3 py-1.5 xl:py-2 text-xs xl:text-sm font-medium flex items-center gap-1.5 xl:gap-2 min-w-[120px] focus:outline-none"
                                    onclick="toggleNavbar('admin-menu')">
                                <i class="fa fa-gear"></i> <span class="hidden sm:inline">Administração</span>
                                <i class="fa fa-chevron-down ml-1 text-xs"></i>
                            </button>
                            <div class="origin-top-left absolute left-0 mt-2 w-56 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-20 hidden" id="admin-menu">
                                @can('ver usuarios')
                                    <a href="{{ route('users.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-2 text-sm rounded transition">
                                        <svg class="h-5 w-5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                        </svg>
                                        Usuários
                                    </a>
                                @endcan

                                @can('ver perfis')
                                    <a href="{{ route('profiles.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-2 text-sm rounded transition">
                                        <svg class="w-5 h-5 text-sky-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                            <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                                        </svg>
                                        Perfis
                                    </a>
                                @endcan



                                @can('ver logs')
                                    <a href="{{ route('logs') }}" target="_blank" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-2 text-sm rounded transition">
                                        <svg class="h-5 w-5 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                        </svg>
                                        Logs
                                    </a>
                                @endcan
                            </div>
                        </div>
                    @endcanany

                    <a href="/doc-passagem.pdf" target="_blank" id="nav-manual-desktop"
                       class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-2 xl:px-3 py-1.5 xl:py-2 text-xs xl:text-sm font-medium flex items-center gap-1.5 xl:gap-2 min-w-[110px]">
                        <i class="fa fa-book"></i> <span class="hidden sm:inline">Manual do Sistema</span>
                    </a>
                </div>

                <div class="flex items-center">
                    <span class="text-white font-medium mr-2 xl:mr-3 text-xs xl:text-sm">Olá, {{ strtok(auth()->user()->name, ' ') }}</span>
                    <div class="relative">
                        <button type="button" class="max-w-xs bg-gray-800 rounded-full flex items-center text-xs xl:text-sm focus:outline-none" id="user-menu-button" aria-expanded="false" aria-haspopup="true" onclick="toggleNavbar('user-options')">
                            <span class="sr-only">Abrir menu do usuário</span>
                            <div class="flex items-center justify-center text-white rounded-full h-8 w-8 xl:h-10 xl:w-10">
                                @if( auth()->user()->photo() )
                                    <img class="h-8 w-8 xl:h-10 xl:w-10 rounded-full" src="data:image/png;base64,{{ auth()->user()->photo() }}" alt="Foto do usuário">
                                @else
                                    <i class="fa fa-user"></i>
                                @endif
                            </div>
                        </button>
                        <div class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10 hidden" role="menu" aria-orientation="vertical" aria-labelledby="user-menu-button" id="user-options">
                            <a href="#" class="block px-4 py-2 text-sm text-gray-700 hover:bg-blue-100" role="menuitem" onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 inline mr-2" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                                </svg>
                                Sair
                            </a>
                            <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Menu -->
    <div class="lg:hidden">
        <div class="fixed inset-0 z-40 hidden" id="mobile-menu-overlay" onclick="toggleNavbar('mobile-menu')">
            <div class="absolute inset-0 bg-gray-800 opacity-75"></div>
        </div>

        <div class="fixed top-0 left-0 w-3/4 max-w-sm h-full bg-white z-50 transform -translate-x-full transition-transform duration-200 ease-in-out hidden" id="mobile-menu">
            <div class="flex justify-end p-3">
                <button onclick="event.preventDefault(); toggleNavbar('mobile-menu')" class="p-1 rounded-md focus:outline-none text-gray-600">
                    <span class="sr-only">Fechar menu</span>
                    <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" /></svg>
                </button>
            </div>

            <div class="flex flex-col divide-y mt-4">
                <a href="{{ route('home') }}" id="nav-inicio-mobile" class="flex items-center gap-3 text-sm text-gray-700 py-3 px-4">
                    <i class="fa fa-home text-sky-600"></i>
                    <div class="flex flex-col">
                        <span class="font-semibold">Início</span>
                        <span class="text-xs text-gray-500">Página inicial do sistema</span>
                    </div>
                </a>

                <a href="{{ route('sbar.report') }}" id="nav-sbar-mobile" class="flex items-center gap-3 text-sm text-gray-700 py-3 px-4">
                    <i class="fa fa-hospital text-sky-600"></i>
                    <div class="flex flex-col">
                        <span class="font-semibold">Passagem de Plantão</span>
                        <span class="text-xs text-gray-500">SBAR - Sistema de Plantão</span>
                    </div>
                </a>

                <a href="{{ route('user.preferences.index') }}" id="nav-preferences-mobile" class="flex items-center gap-3 text-sm text-gray-700 py-3 px-4">
                    <i class="fa fa-cog text-sky-600"></i>
                    <div class="flex flex-col">
                        <span class="font-semibold">Meus Setores</span>
                        <span class="text-xs text-gray-500">Configure seus setores de internação</span>
                    </div>
                </a>

                <a href="{{ route('feedback') }}" id="nav-feedback-mobile" class="flex items-center gap-3 text-sm text-gray-700 py-3 px-4">
                    <i class="fa fa-comment text-sky-600"></i>
                    <div class="flex flex-col">
                        <span class="font-semibold">Feedback</span>
                        <span class="text-xs text-gray-500">Envie sua opinião sobre o sistema</span>
                    </div>
                </a>

                @canany(['ver usuarios', 'ver perfis', 'ver logs', 'configurar sistema'])
                    <div class="flex flex-col">
                        <button type="button" class="flex items-center gap-3 w-full text-sm text-gray-700 py-3 px-4 focus:outline-none" onclick="toggleNavbar('mobile-admin-menu')">
                            <i class="fa fa-gear text-sky-600"></i>
                            <span class="font-semibold">Administração</span>
                            <i class="fa fa-chevron-down ml-auto text-xs"></i>
                        </button>
                        <div class="flex-col bg-gray-50 rounded-md shadow-inner ml-8 my-1 hidden" id="mobile-admin-menu">
                            @can('ver usuarios')
                                <a href="{{ route('users.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-2 text-sm rounded transition">
                                    <svg class="h-5 w-5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    Usuários
                                </a>
                            @endcan

                            @can('ver perfis')
                                <a href="{{ route('profiles.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-2 text-sm rounded transition">
                                    <svg class="w-5 h-5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                                    </svg>
                                    Perfis
                                </a>
                            @endcan

                            @can('ver logs')
                                <a href="{{ route('logs') }}" target="_blank" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-2 text-sm rounded transition">
                                    <svg class="h-5 w-5 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                    </svg>
                                    Logs
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany

                <a href="/doc-passagem.pdf" target="_blank" id="nav-manual-mobile" class="flex items-center gap-3 text-sm text-gray-700 py-3 px-4">
                    <i class="fa fa-book text-sky-600"></i>
                    <div class="flex flex-col">
                        <span class="font-semibold">Manual do Sistema</span>
                        <span class="text-xs text-gray-500">Documentação e ajuda</span>
                    </div>
                </a>

                <div class="flex items-center px-4 mt-4">
                    <div class="flex items-center justify-center text-white bg-gray-800 rounded-full h-10 w-10 mr-2">
                        @if( auth()->user()->photo() )
                            <img class="h-10 w-10 rounded-full" src="data:image/png;base64,{{ auth()->user()->photo() }}" alt="Foto do usuário">
                        @else
                            <i class="fa fa-user"></i>
                        @endif
                    </div>
                    <span class="text-gray-700 font-medium">Olá, {{ strtok(auth()->user()->name, ' ') }}</span>
                </div>

                <a href="#" class="flex items-center gap-3 text-sm text-sky-600 py-3 px-4" onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();">
                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                    </svg>
                    <div class="flex flex-col">
                        <span class="font-semibold">Sair</span>
                        <span class="text-xs text-gray-400">Encerrar a sessão no sistema</span>
                    </div>
                </a>
                <form id="logout-form-mobile" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
            </div>
        </div>
    </div>
</nav>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        function toggleNavbar(menuId) {
            if (menuId === 'mobile-menu') {
                const menu = document.getElementById('mobile-menu');
                const overlay = document.getElementById('mobile-menu-overlay');
                if (menu && overlay) {
                    const isOpen = !menu.classList.contains('hidden');
                    if (isOpen) {
                        menu.classList.add('hidden');
                        menu.classList.add('-translate-x-full');
                        overlay.classList.add('hidden');
                    } else {
                        menu.classList.remove('hidden');
                        setTimeout(() => menu.classList.remove('-translate-x-full'), 10);
                        overlay.classList.remove('hidden');
                    }
                }
                const mobileAdminMenu = document.getElementById('mobile-admin-menu');
                if (mobileAdminMenu) mobileAdminMenu.classList.add('hidden');
                return;
            }

            if (menuId === 'admin-menu') {
                const menu = document.getElementById('admin-menu');
                if (menu) menu.classList.toggle('hidden');
                return;
            }

            if (menuId === 'mobile-admin-menu') {
                const menu = document.getElementById('mobile-admin-menu');
                if (menu) menu.classList.toggle('hidden');
                return;
            }

            if (menuId === 'user-options') {
                const menu = document.getElementById('user-options');
                if (menu) menu.classList.toggle('hidden');
                return;
            }
        }

        window.toggleNavbar = toggleNavbar;

        document.addEventListener('click', function(e) {
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileMenuOverlay = document.getElementById('mobile-menu-overlay');
            const mobileMenuBtn = document.querySelector('button[onclick*="toggleNavbar(\'mobile-menu\')"]');

            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                if (!mobileMenu.contains(e.target) && (!mobileMenuBtn || !mobileMenuBtn.contains(e.target)) && (!mobileMenuOverlay || !mobileMenuOverlay.contains(e.target))) {
                    mobileMenu.classList.add('hidden');
                    mobileMenu.classList.add('-translate-x-full');
                    if (mobileMenuOverlay) mobileMenuOverlay.classList.add('hidden');
                }
            }

            const adminBtn = document.getElementById('nav-administracao-desktop');
            const adminMenu = document.getElementById('admin-menu');
            if (adminMenu && !adminMenu.classList.contains('hidden')) {
                if (!adminMenu.contains(e.target) && (!adminBtn || !adminBtn.contains(e.target))) {
                    adminMenu.classList.add('hidden');
                }
            }

            const userBtn = document.getElementById('user-menu-button');
            const userMenu = document.getElementById('user-options');
            if (userMenu && !userMenu.classList.contains('hidden')) {
                if (!userMenu.contains(e.target) && (!userBtn || !userBtn.contains(e.target))) {
                    userMenu.classList.add('hidden');
                }
            }

            const mobileAdminMenu = document.getElementById('mobile-admin-menu');
            const mobileAdminBtn = document.querySelector('button[onclick*="toggleNavbar(\'mobile-admin-menu\')"]');
            if (mobileAdminMenu && !mobileAdminMenu.classList.contains('hidden')) {
                if (!mobileAdminMenu.contains(e.target) && (!mobileAdminBtn || !mobileAdminBtn.contains(e.target))) {
                    mobileAdminMenu.classList.add('hidden');
                }
            }
        });

        document.getElementById('mobile-menu')?.addEventListener('click', function(e) {
            e.stopPropagation();
        });
    });
</script>
