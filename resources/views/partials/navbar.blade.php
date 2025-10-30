<nav class="bg-gradient-to-l from-santacasa-100 via-santacasa-default to-santacasa-300 border-b shadow-md">
    <div class="mx-auto px-2 sm:px-6 lg:px-8">
        <div class="relative flex items-center justify-between h-16">

            <div class="absolute inset-y-0 left-0 flex items-center sm:hidden">
                <!-- Mobile menu button-->
                <button type="button" class="inline-flex items-center justify-center p-2 rounded-md text-white hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white" aria-controls="mobile-menu" aria-expanded="false" onclick="toggleNavbar('mobile-menu')">
                    <span class="sr-only">Abrir menu</span>

                    <svg class="block h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                    </svg>

                    <svg class="hidden h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="flex-1 flex items-center justify-center sm:items-stretch sm:justify-start">
                <div class="flex-shrink-0 flex items-center">
                    <img class="block lg:hidden h-12 w-auto" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="">
                    <img class="hidden lg:block h-12 w-auto" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="">
                </div>

                <div class="hidden sm:block sm:ml-6">
                    <div class="flex space-x-4">
                        <a href="{{ route( 'home' ) }}" class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-3 py-2 text-sm font-medium">Inicio</a>
                        <a href="{{ route('feedback') }}" class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-3 py-2 text-sm font-medium">
                            Feedback
                        </a>
                    </div>
                </div>
                @canany( ['ver usuarios','ver perfis','ver logs','configurar sistema'] )
                    <div class="relative">
                        <div class="hidden sm:block sm:ml-6">
                            <div class="flex space-x-4">
                                <a href="#" class="transition duration-200 ease-in-out border-b-4 hover:border-blue-200 text-white hover:text-blue-200 px-3 py-2 text-sm font-medium" aria-expanded="false" aria-haspopup="true" onclick="event.preventDefault(); toggleNavbar('admin-menu')" onblur="closeMenu('admin-menu')">Administração</a>
                            </div>
                        </div>
                        <div class="origin-top-left absolute left-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10 hidden menu" role="menu" aria-orientation="vertical" aria-labelledby="admin-menu" id="admin-menu">

                            @can( 'ver usuarios' )
                                <a href="{{ route( 'users.index' ) }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-4 lg:py-2 flex items-center text-xs rounded" role="menuitem">
                                    <svg class="h-5 w-5 text-sky-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z" />
                                    </svg>
                                    <p class="ml-3 text-sm">Usuários</p>
                                </a>
                            @endcan

                            @can( 'ver perfis' )
                                <a href="{{ route( 'profiles.index' ) }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-4 lg:py-2 flex items-center text-xs rounded" role="menuitem">
                                    <svg class="w-5 h-5 text-sky-600" aria-hidden="true" xmlns="http://www.w3.org/2000/svg" width="24" height="24" fill="none" viewBox="0 0 24 24">
                                        <path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 9h3m-3 3h3m-3 3h3m-6 1c-.306-.613-.933-1-1.618-1H7.618c-.685 0-1.312.387-1.618 1M4 5h16a1 1 0 0 1 1 1v12a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V6a1 1 0 0 1 1-1Zm7 5a2 2 0 1 1-4 0 2 2 0 0 1 4 0Z"/>
                                    </svg>
                                    <p class="ml-3 text-sm">Perfis</p>
                                </a>
                            @endcan
                            @can('configurar sistema')
                                <a href="{{ route('system-configuration.index') }}" class="text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-4 lg:py-2 flex items-center text-xs rounded" role="menuitem">
                                    <x-iconoir-bed class="text-sky-600 h-4 w-4 flex-shrink-0" />
                                    <p class="ml-3 text-sm">Config. Leitos</p>
                                </a>
                            @endcan
                            @can( 'ver logs' )
                                <a href="{{ route( 'logs' ) }}" target="_blank" class="text-gray-600 hover:text-gray-800 hover:bg-blue-100 px-3 py-4 lg:py-2 flex items-center text-xs rounded" role="menuitem">
                                    <svg class="h-5 w-5 text-sky-600" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"></path>
                                    </svg>
                                    <p class="ml-3 text-sm">Logs</p>
                                </a>
                            @endcan
                        </div>
                    </div>
                @endcanany
            </div>

            <div class="absolute inset-y-0 right-0 flex items-center pr-2 sm:static sm:inset-auto sm:ml-6 sm:pr-0">

                <span class="hidden md:block text-white font-medium">Olá, {{ auth()->user()->name }}</span>

                <!-- Profile dropdown -->
                <div class="ml-3 relative">
                    <div>
                        <button type="button" class="sm:hidden max-w-xs bg-gray-800 rounded-full flex items-center text-sm focus:outline-none" id="user-menu" aria-expanded="false" aria-haspopup="true" onclick="toggleNavbar('profile-menu')">
                            <div class="flex items-center justify-center text-white rounded-full h-10 w-10">
                                @if( auth()->user()->photo() )
                                    <img class="h-10 w-10 rounded-full" src="data:image/png;base64,{{ auth()->user()->photo() }}" alt="">
                                @else
                                    <i class="fa fa-user"></i>
                                @endif
                            </div>
                        </button>
                        <button type="button" class="hidden sm:block max-w-xs bg-gray-800 rounded-full flex items-center text-sm focus:outline-none" id="user-menu" aria-expanded="false" aria-haspopup="true" onclick="toggleNavbar('user-options')">
                            <div class="flex items-center justify-center text-white rounded-full h-10 w-10">
                                @if( auth()->user()->photo() )
                                    <img class="h-10 w-10 rounded-full" src="data:image/png;base64,{{ auth()->user()->photo() }}" alt="">
                                @else
                                    <i class="fa fa-user"></i>
                                @endif
                            </div>
                        </button>
                    </div>

                    <div class="origin-top-right absolute right-0 mt-2 w-48 rounded-md shadow-lg py-1 bg-white ring-1 ring-black ring-opacity-5 focus:outline-none z-10 hidden" role="menu" aria-orientation="vertical" aria-labelledby="user-menu" id="user-options">

                        <span class="md:hidden text-gray-600 text-xs font-medium px-3">Olá, {{ strtok(auth()->user()->name, " ") }}</span>

                        <a class="text-gray-600 hover:text-gray-800 px-3 py-4 lg:py-2 flex items-center text-xs rounded" role="menuitem"
                           href="#" onclick="event.preventDefault(); document.getElementById('logout-form').submit();" style="transition: all .15s ease">
                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                            <p class="ml-3">Sair</p>
                        </a>
                        <form id="logout-form" action="{{ route('logout') }}" method="POST" class="hidden">
                            @csrf
                        </form>
                    </div>
                </div>
            </div>

        </div>
    </div>

</nav>

