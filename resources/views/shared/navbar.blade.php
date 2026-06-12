<nav class="bg-gradient-to-l from-santacasa-100 via-santacasa-default to-santacasa-300 border-b shadow-md">
    <div class="mx-auto px-2 sm:px-6 lg:px-8">
        <div class="relative flex items-center justify-between h-16">

            {{-- Mobile hamburger --}}
            <div class="absolute inset-y-0 left-0 flex items-center md:hidden">
                <button type="button"
                        class="inline-flex items-center justify-center p-2 rounded-md text-white hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                        aria-controls="mobile-menu"
                        aria-expanded="false"
                        onclick="toggleNavbar('mobile-menu')">
                    <span class="sr-only">Abrir menu</span>
                    <svg class="block h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>
                </button>
            </div>

            {{-- Logo + Desktop nav --}}
            <div class="flex-1 flex items-center justify-center md:items-stretch md:justify-start">
                <div class="flex-shrink-0 flex items-center">
                    <img class="h-12 w-auto" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="Logo Santa Casa" loading="lazy">
                </div>

                {{-- Desktop nav links --}}
                {{-- md (768-1023px): ícones apenas | lg (1024-1279px): ícones + label curto | xl (1280px+): label completo --}}
                <div class="hidden md:block md:ml-4 lg:ml-6 min-w-0">
                    <div class="flex items-center space-x-1 lg:space-x-0.5">

                        <a href="{{ route('home') }}"
                           title="Início"
                           class="nav-link {{ request()->routeIs('home') ? 'border-blue-200' : 'border-transparent' }}">
                            <i class="fa fa-home"></i>
                            <span class="hidden lg:inline">Início</span>
                        </a>

                        <a href="{{ route('sbar.report') }}"
                           title="Passagem de Plantão"
                           class="nav-link {{ request()->routeIs('sbar.*') ? 'border-blue-200' : 'border-transparent' }}">
                            <i class="fa fa-hospital"></i>
                            <span class="hidden xl:inline">Passagem de Plantão</span>
                            <span class="hidden lg:inline xl:hidden">Plantão</span>
                        </a>

                        <a href="{{ route('user.preferences.index') }}"
                           title="Meus Setores"
                           class="nav-link {{ request()->routeIs('user.preferences.*') ? 'border-blue-200' : 'border-transparent' }}">
                            <i class="fa fa-cog"></i>
                            <span class="hidden xl:inline">Meus Setores</span>
                            <span class="hidden lg:inline xl:hidden">Setores</span>
                        </a>

                        <a href="{{ route('feedback') }}"
                           title="Feedback"
                           class="nav-link {{ request()->routeIs('feedback') ? 'border-blue-200' : 'border-transparent' }}">
                            <i class="fa fa-comment"></i>
                            <span class="hidden lg:inline">Feedback</span>
                        </a>

                        <a href="{{ route('pending.report') }}"
                           title="Relatório de Pendências"
                           class="nav-link {{ request()->routeIs('pending.report*') ? 'border-blue-200' : 'border-transparent' }}">
                            <i class="fas fa-list-check"></i>
                            <span class="hidden xl:inline">Pendências</span>
                            <span class="hidden lg:inline xl:hidden">Pend.</span>
                        </a>

                        <button type="button"
                                title="Consulta Rápida de Paciente (beta)"
                                class="nav-link border-transparent"
                                onclick="window.dispatchEvent(new CustomEvent('open-patient-quick-search'))">
                            <i class="fas fa-magnifying-glass-plus"></i>
                            <span class="hidden lg:inline">Busca</span>
                        </button>

                        @canany(['ver usuarios', 'ver perfis', 'ver logs', 'configurar sistema', 'ver historico chat'])
                            <div class="relative">
                                <button type="button"
                                        id="admin-dropdown-btn"
                                        title="Administração"
                                        class="nav-link {{ request()->routeIs('users.*') || request()->routeIs('profiles.*') || request()->routeIs('logs') || request()->routeIs('admin.dashboard') ? 'border-blue-200' : 'border-transparent' }}"
                                        aria-haspopup="true"
                                        onclick="toggleNavbar('admin-dropdown')">
                                    <i class="fa fa-gear"></i>
                                    <span class="hidden xl:inline">Administração</span>
                                    <span class="hidden lg:inline xl:hidden">Admin</span>
                                    <i class="fa fa-chevron-down text-[10px] ml-0.5 transition-transform duration-200"></i>
                                </button>

                                <div id="admin-dropdown"
                                     class="hidden origin-top-left absolute left-0 mt-2 w-64 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-[1000]"
                                     role="menu">
                                    @can('ver usuarios')
                                        <a href="{{ route('users.index') }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-50 px-3 py-2 flex items-center text-sm rounded {{ request()->routeIs('users.*') ? 'bg-blue-50 text-blue-700' : '' }}" role="menuitem">
                                            <svg class="h-4 w-4 text-sky-600 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                            </svg>
                                            <span class="ml-2">Usuários</span>
                                        </a>
                                    @endcan
                                    @can('ver perfis')
                                        <a href="{{ route('profiles.index') }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-50 px-3 py-2 flex items-center text-sm rounded {{ request()->routeIs('profiles.*') ? 'bg-blue-50 text-blue-700' : '' }}" role="menuitem">
                                            <svg class="w-4 h-4 text-sky-600 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                                            </svg>
                                            <span class="ml-2">Perfis</span>
                                        </a>
                                    @endcan
                                    @can('ver historico chat')
                                        <a href="{{ route('admin.dashboard') }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-50 px-3 py-2 flex items-center text-sm rounded {{ request()->routeIs('admin.dashboard') ? 'bg-blue-50 text-blue-700' : '' }}" role="menuitem">
                                            <i class="fas fa-clock-rotate-left w-4 text-sky-600 text-sm flex-shrink-0"></i>
                                            <span class="ml-2">Panorama do Sistema</span>
                                        </a>
                                    @endcan
                                    @can('configurar sistema')
                                        <a href="{{ route('admin.commands') }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-50 px-3 py-2 flex items-center text-sm rounded {{ request()->routeIs('admin.commands') ? 'bg-blue-50 text-blue-700' : '' }}" role="menuitem">
                                            <i class="fas fa-terminal w-4 text-sky-600 text-sm flex-shrink-0"></i>
                                            <span class="ml-2">Comandos</span>
                                        </a>
                                    @endcan
                                    @can('ver logs')
                                        <a href="{{ route('log-viewer.index') }}" target="_blank" class="text-gray-600 hover:text-gray-800 hover:bg-blue-50 px-3 py-2 flex items-center text-sm rounded" role="menuitem">
                                            <svg class="h-4 w-4 text-sky-600 flex-shrink-0" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z" />
                                            </svg>
                                            <span class="ml-2">Logs</span>
                                        </a>
                                    @endcan
                                </div>
                            </div>
                        @endcanany

                        <a href="{{ route('manual.index') }}"
                           title="Manual do Sistema"
                           class="nav-link {{ request()->routeIs('manual.index') ? 'border-blue-200' : 'border-transparent' }}">
                            <i class="fa fa-book"></i>
                            <span class="hidden xl:inline">Manual do Sistema</span>
                            <span class="hidden lg:inline xl:hidden">Manual</span>
                        </a>

                    </div>
                </div>
            </div>

            {{-- Right: user greeting + avatar --}}
            @php
                $navUserRole = trim((string) auth()->user()->getUserRole());
            @endphp
            <div class="absolute inset-y-0 right-0 flex items-center pr-2 sm:static sm:inset-auto sm:ml-6 sm:pr-0">
                <div class="hidden lg:flex flex-col items-end mr-3">
                    <span class="text-white font-medium text-sm leading-tight">Olá, {{ strtok(auth()->user()->name, ' ') }}</span>
                    @if($navUserRole !== '')
                        <span class="text-white/70 text-[11px] leading-tight">{{ $navUserRole }}</span>
                    @endif
                </div>

                <div class="ml-3 relative">
                    {{-- Mobile avatar button --}}
                    <button type="button"
                            class="md:hidden max-w-xs rounded-full flex items-center text-sm focus:outline-none"
                            onclick="toggleNavbar('profile-menu')">
                        <x-ui.user-avatar :photo="auth()->user()->photo()" :name="auth()->user()->name" format="png" class="h-9 w-9" />
                    </button>

                    {{-- Desktop avatar button --}}
                    <button type="button"
                            class="hidden md:flex max-w-xs rounded-full items-center text-sm focus:outline-none"
                            id="user-menu-button"
                            aria-expanded="false"
                            aria-haspopup="true"
                            onclick="toggleNavbar('user-options')">
                        <x-ui.user-avatar :photo="auth()->user()->photo()" :name="auth()->user()->name" format="png" class="h-9 w-9 lg:h-10 lg:w-10" />
                    </button>

                    {{-- Desktop dropdown --}}
                    <div class="origin-top-right absolute right-0 mt-2 w-60 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10 hidden"
                         role="menu"
                         id="user-options">
                        <div class="px-3 py-2.5 border-b border-gray-100">
                            <p class="text-sm font-semibold text-gray-800 leading-tight">{{ auth()->user()->name }}</p>
                            @if($navUserRole !== '')
                                <p class="text-xs text-gray-500 mt-0.5">{{ $navUserRole }}</p>
                            @endif
                            <p class="text-[10px] text-gray-400 mt-1 font-mono">
                                Sessão ativa até {{ now()->addMinutes((int) config('session.lifetime'))->format('H:i') }}
                            </p>
                        </div>

                        <a class="text-gray-600 hover:text-gray-800 hover:bg-red-50 px-3 py-2.5 flex items-center gap-2.5 text-xs rounded"
                           role="menuitem"
                           href="#"
                           onclick="event.preventDefault(); document.getElementById('logout-form').submit();">
                            <i class="fas fa-sign-out-alt text-red-400 w-4 text-center"></i>
                            <p>Sair do sistema</p>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</nav>

{{-- Mobile menu (Slide lateral esquerda) --}}
<div class="fixed w-3/4 max-w-sm block z-50 hidden" id="mobile-menu">
    <div class="fixed inset-0 transition-opacity -z-10" onclick="toggleNavbar('mobile-menu')">
        <div class="absolute inset-0 bg-gray-500/75 backdrop-blur-sm"></div>
    </div>
    <div class="bg-white h-screen p-3 relative z-10">

        <div class="flex justify-end">
            <button onclick="event.preventDefault(); toggleNavbar('mobile-menu')" class="p-1 rounded-md focus:outline-none text-gray-600">
                <svg class="h-8 w-8" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="flex flex-col divide-y">
            <a href="{{ route('home') }}" class="flex justify-between items-center text-sm text-gray-700 mt-4">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Início</span>
                    <span class="text-xs text-gray-400">Página inicial do sistema</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>

            <a href="{{ route('sbar.report') }}" class="flex justify-between items-center text-sm text-gray-700">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Passagem de Plantão</span>
                    <span class="text-xs text-gray-400">SBAR - Sistema de Plantão</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>

            <a href="{{ route('user.preferences.index') }}" class="flex justify-between items-center text-sm text-gray-700">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Meus Setores</span>
                    <span class="text-xs text-gray-400">Configure seus setores de internação</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>

            <button type="button"
                    onclick="toggleNavbar('mobile-menu'); window.dispatchEvent(new CustomEvent('open-patient-quick-search'))"
                    class="flex justify-between items-center text-sm text-gray-700 w-full text-left">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">
                        Consulta Rápida
                        <span class="text-[9px] font-medium text-gray-400 ml-1">BETA</span>
                    </span>
                    <span class="text-xs text-gray-400">Cirurgias, procedimentos e coletas pendentes</span>
                </div>
                <i class="fas fa-magnifying-glass-plus text-sm text-gray-400"></i>
            </button>

            <a href="{{ route('feedback') }}" class="flex justify-between items-center text-sm text-gray-700">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Feedback</span>
                    <span class="text-xs text-gray-400">Envie sua opinião sobre o sistema</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>

            <a href="{{ route('pending.report') }}" class="flex justify-between items-center text-sm text-gray-700">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Relatório de Pendências</span>
                    <span class="text-xs text-gray-400">Pendências assistenciais do setor</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>

            @canany(['ver usuarios', 'ver perfis', 'ver logs', 'configurar sistema', 'ver historico chat'])
                <div class="flex flex-col">
                    <button type="button" class="flex justify-between items-center text-sm text-gray-700 w-full" onclick="toggleNavbar('mobile-admin-menu')">
                        <div class="px-2 pt-2 pb-3 flex flex-col text-left">
                            <span class="font-semibold">Administração</span>
                            <span class="text-xs text-gray-400">Usuários, perfis e ferramentas</span>
                        </div>
                        <i class="fas fa-chevron-right text-sm text-gray-400"></i>
                    </button>
                    <div class="flex-col bg-gray-50 rounded-md shadow-inner ml-4 my-1 hidden" id="mobile-admin-menu">
                        @can('ver usuarios')
                            <a href="{{ route('users.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-3 text-sm rounded transition">
                                <i class="fas fa-users text-sky-600 w-5"></i>
                                <span>Usuários</span>
                            </a>
                        @endcan
                        @can('ver perfis')
                            <a href="{{ route('profiles.index') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-3 text-sm rounded transition">
                                <i class="fas fa-id-card text-sky-600 w-5"></i>
                                <span>Perfis</span>
                            </a>
                        @endcan
                        @can('ver historico chat')
                            <a href="{{ route('admin.dashboard') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-3 text-sm rounded transition">
                                <i class="fas fa-clock-rotate-left text-sky-600 w-5"></i>
                                <span>Panorama do Sistema</span>
                            </a>
                        @endcan
                        @can('configurar sistema')
                            <a href="{{ route('admin.commands') }}" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-3 text-sm rounded transition">
                                <i class="fas fa-terminal text-sky-600 w-5"></i>
                                <span>Comandos</span>
                            </a>
                        @endcan
                        @can('ver logs')
                            <a href="{{ route('log-viewer.index') }}" target="_blank" class="flex items-center gap-2 text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-3 text-sm rounded transition">
                                <i class="fas fa-file-lines text-sky-600 w-5"></i>
                                <span>Logs</span>
                            </a>
                        @endcan
                    </div>
                </div>
            @endcanany

            <a href="{{ route('manual.index') }}" class="flex justify-between items-center text-sm text-gray-700">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Manual do Sistema</span>
                    <span class="text-xs text-gray-400">Documentação e ajuda</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
        </div>
    </div>
</div>

{{-- Menu Perfil Mobile (Slide lateral direita) --}}
<div class="fixed w-3/4 max-w-sm block z-50 hidden right-0 top-0 h-full" id="profile-menu">
    <div class="fixed inset-0 transition-opacity -z-10" onclick="toggleNavbar('profile-menu')">
        <div class="absolute inset-0 bg-gray-500/75 backdrop-blur-sm"></div>
    </div>
    <div class="bg-white h-screen p-3 relative z-10 ml-auto">
        <div class="flex justify-start">
            <button onclick="event.preventDefault(); toggleNavbar('profile-menu')" class="rounded-full flex items-center">
                <x-ui.user-avatar :photo="auth()->user()->photo()" :name="auth()->user()->name" format="png" class="h-9 w-9" />
            </button>
        </div>

        <div class="flex flex-col divide-y mt-4">
            <div class="px-2 pt-2 pb-3">
                <span class="text-sm font-semibold text-gray-700">{{ auth()->user()->name }}</span>
                @if($navUserRole !== '')
                    <p class="text-xs text-gray-400 mt-0.5">{{ $navUserRole }}</p>
                @endif
            </div>

            {{-- Sessão info --}}
            <div class="px-2 py-2">
                <p class="text-[10px] text-gray-400 font-mono">
                    Sessão até {{ now()->addMinutes((int) config('session.lifetime'))->format('H:i') }}
                </p>
            </div>

            <a href="#" onclick="event.preventDefault(); document.getElementById('logout-form-mobile').submit();"
               class="flex justify-between items-center text-sm text-gray-700 mt-2">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold text-red-600">Sair do sistema</span>
                    <span class="text-xs text-gray-400">Encerrar a sessão completamente</span>
                </div>
                <i class="fas fa-chevron-right text-sm text-gray-400"></i>
            </a>
            <form id="logout-form-mobile" action="{{ route('logout') }}" method="POST" class="hidden">@csrf</form>
        </div>
    </div>
</div>

<script>
    (function () {
        function closeAdminDropdown() {
            const menu = document.getElementById('admin-dropdown');
            const btn  = document.getElementById('admin-dropdown-btn');
            if (menu) menu.classList.add('hidden');
            if (btn) btn.querySelector('.fa-chevron-down')?.classList.remove('rotate-180');
        }

        function toggleNavbar(menuId) {
            if (menuId === 'mobile-menu') {
                const menu = document.getElementById('mobile-menu');
                const isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', isOpen);
                if (!isOpen) {
                    document.getElementById('profile-menu')?.classList.add('hidden');
                }
                return;
            }

            if (menuId === 'profile-menu') {
                const menu = document.getElementById('profile-menu');
                const isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', isOpen);
                if (!isOpen) {
                    document.getElementById('mobile-menu')?.classList.add('hidden');
                }
                return;
            }

            if (menuId === 'mobile-admin-menu') {
                document.getElementById('mobile-admin-menu')?.classList.toggle('hidden');
                return;
            }

            if (menuId === 'user-options') {
                const menu = document.getElementById('user-options');
                if (menu) menu.classList.toggle('hidden');
                return;
            }

            if (menuId === 'admin-dropdown') {
                const menu = document.getElementById('admin-dropdown');
                const btn  = document.getElementById('admin-dropdown-btn');
                if (!menu) return;
                const isOpen = !menu.classList.contains('hidden');
                menu.classList.toggle('hidden', isOpen);
                btn?.querySelector('.fa-chevron-down')?.classList.toggle('rotate-180', !isOpen);
                // Fecha o user-options se estiver aberto
                if (!isOpen) {
                    document.getElementById('user-options')?.classList.add('hidden');
                }
                return;
            }
        }

        window.toggleNavbar = toggleNavbar;

        document.addEventListener('click', function (e) {
            // Fecha admin-dropdown ao clicar fora
            const adminMenu = document.getElementById('admin-dropdown');
            const adminBtn  = document.getElementById('admin-dropdown-btn');
            if (adminMenu && !adminMenu.classList.contains('hidden')) {
                if (!adminMenu.contains(e.target) && (!adminBtn || !adminBtn.contains(e.target))) {
                    closeAdminDropdown();
                }
            }

            // Fecha mobile-menu ao clicar fora
            const mobileMenu = document.getElementById('mobile-menu');
            const mobileBtn  = document.querySelector('[onclick*="toggleNavbar(\'mobile-menu\')"]');
            if (mobileMenu && !mobileMenu.classList.contains('hidden')) {
                if (!mobileMenu.contains(e.target) && (!mobileBtn || !mobileBtn.contains(e.target))) {
                    mobileMenu.classList.add('hidden');
                }
            }

            // Fecha profile-menu ao clicar fora
            const profileMenu = document.getElementById('profile-menu');
            const profileBtn  = document.querySelector('[onclick*="toggleNavbar(\'profile-menu\')"]');
            if (profileMenu && !profileMenu.classList.contains('hidden')) {
                if (!profileMenu.contains(e.target) && (!profileBtn || !profileBtn.contains(e.target))) {
                    profileMenu.classList.add('hidden');
                }
            }

            // Fecha user-options ao clicar fora
            const userMenu = document.getElementById('user-options');
            const userBtn  = document.getElementById('user-menu-button');
            if (userMenu && !userMenu.classList.contains('hidden')) {
                if (!userMenu.contains(e.target) && (!userBtn || !userBtn.contains(e.target))) {
                    userMenu.classList.add('hidden');
                }
            }
        });
    })();
</script>
