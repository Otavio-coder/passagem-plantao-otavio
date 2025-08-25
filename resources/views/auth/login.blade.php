<!doctype html>
<html lang="pt-br" class="h-full">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="{{ secure_asset('images/favicon.ico') }}">

    <!-- Add Montserrat font from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>{{ env('APP_NAME') }} - Login</title>

    <style>
        body {
            font-family: 'Montserrat', sans-serif;
        }
        .login-fade-in {
            animation: fadeInUp 0.8s ease-out;
        }
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px) scale(0.95);
            }
            to {
                opacity: 1;
                transform: translateY(0) scale(1);
            }
        }
        .image-overlay {
            background: linear-gradient(135deg, rgba(0, 45, 100, 0.4) 0%, rgba(0, 35, 80, 0.6) 100%);
        }
    </style>
</head>

<body class="h-full bg-gray-50 font-montserrat">
    <div class="min-h-full flex">
        <!-- Left Section - Hospital Image -->
        <div class="hidden lg:flex lg:w-1/2 xl:w-3/5 relative">
            <div class="absolute inset-0 bg-gradient-to-br from-[#004D9D]/40 to-[#004D9D]/60"></div>
            <img 
                class="absolute inset-0 w-full h-full object-cover"
                src="{{ asset('images/login-photo.jpeg') }}" 
                alt="Hospital environment - Santa Casa de Porto Alegre"
                style="aspect-ratio: 4/5; object-fit: cover;"
            />
            <div class="absolute inset-0 image-overlay"></div>
            
            <!-- Overlay content -->
            <div class="relative z-10 flex flex-col justify-end p-12 text-white">
                <div class="max-w-md">
                    <img class="w-48 mb-6" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="Santa Casa de Porto Alegre" />
                    <h1 class="text-3xl font-bold mb-4">Sistema de Passagem de Plantão</h1>
                    <p class="text-lg text-gray-200 leading-relaxed">
                        Facilitando uma passagem de plantão segura, clara e padronizada para garantir a continuidade do cuidado.
                    </p>
                    <div class="mt-6 text-sm text-gray-300">
                        <p>© {{ now()->format('Y') }} Santa Casa de Porto Alegre</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Section - Login Form -->
        <div class="flex-1 flex flex-col justify-center py-12 px-4 sm:px-6 lg:px-20 xl:px-24 bg-white">
            <div class="mx-auto w-full max-w-sm lg:max-w-md">
                <!-- Mobile logo and title -->
                <div class="lg:hidden text-center mb-8">
                    <img class="mx-auto w-32 mb-4" src="{{ asset('images/santacasa-horizontal-branco.svg') }}" alt="Santa Casa" style="filter: brightness(0) saturate(100%) invert(25%) sepia(99%) saturate(1614%) hue-rotate(201deg) brightness(94%) contrast(101%);" />
                    <h1 class="text-2xl font-bold text-[#004D9D] mb-2">Sistema de Passagem de Plantão</h1>
                    <p class="text-gray-600 text-sm">Santa Casa de Porto Alegre</p>
                </div>

                <!-- Login Form -->
                <div class="login-fade-in">
                    <div class="bg-white rounded-2xl shadow-2xl p-8 border border-gray-100">
                        <div class="text-center mb-8">
                            <h2 class="text-2xl font-bold text-gray-900 mb-2">Bem-vindo de volta</h2>
                            <p class="text-gray-600">Acesse com suas credenciais LDAP/AD</p>
                        </div>

                        <form method="post" action="{{ route('login') }}" id="login-form">
                            @csrf
                            
                            <!-- Username Field -->
                            <div class="mt-6">
                                <input required placeholder="Usuário" id="username" name="username" aria-labelledby="username" type="text" class="bg-gray-100 border border-gray-200 focus:outline-none focus:border-sky-600 focus:ring-sky-600 focus:ring-1 rounded text-base font-medium leading-none text-sky-800 py-3 w-full pl-3 mt-2"/>
                            </div>

                            <!-- Password Field -->
                            <div class="mt-6 w-full">
                                <div class="relative flex items-center justify-center">
                                    <input required placeholder="Senha" id="password" name="password" type="password" class="bg-gray-100 border border-gray-200 focus:outline-none focus:border-sky-600 focus:ring-sky-600 focus:ring-1 rounded text-base font-medium leading-none text-sky-800 py-3 w-full pl-3 mt-2"/>
                                    <a href="#" onclick="event.preventDefault(); showPassword('password')" class="absolute right-0 mt-2 mr-3 cursor-pointer">
                                        <svg width="16" height="16" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                            <path d="M7.99978 2C11.5944 2 14.5851 4.58667 15.2124 8C14.5858 11.4133 11.5944 14 7.99978 14C4.40511 14 1.41444 11.4133 0.787109 8C1.41378 4.58667 4.40511 2 7.99978 2ZM7.99978 12.6667C9.35942 12.6664 10.6787 12.2045 11.7417 11.3568C12.8047 10.509 13.5484 9.32552 13.8511 8C13.5473 6.67554 12.8031 5.49334 11.7402 4.64668C10.6773 3.80003 9.35864 3.33902 7.99978 3.33902C6.64091 3.33902 5.32224 3.80003 4.25936 4.64668C3.19648 5.49334 2.45229 6.67554 2.14844 8C2.45117 9.32552 3.19489 10.509 4.25787 11.3568C5.32085 12.2045 6.64013 12.6664 7.99978 12.6667ZM7.99978 11C7.20413 11 6.44106 10.6839 5.87846 10.1213C5.31585 9.55871 4.99978 8.79565 4.99978 8C4.99978 7.20435 5.31585 6.44129 5.87846 5.87868C6.44106 5.31607 7.20413 5 7.99978 5C8.79543 5 9.55849 5.31607 10.1211 5.87868C10.6837 6.44129 10.9998 7.20435 10.9998 8C10.9998 8.79565 10.6837 9.55871 10.1211 10.1213C9.55849 10.6839 8.79543 11 7.99978 11ZM7.99978 9.66667C8.4418 9.66667 8.86573 9.49107 9.17829 9.17851C9.49085 8.86595 9.66644 8.44203 9.66644 8C9.66644 7.55797 9.49085 7.13405 9.17829 6.82149C8.86573 6.50893 8.4418 6.33333 7.99978 6.33333C7.55775 6.33333 7.13383 6.50893 6.82126 6.82149C6.5087 7.13405 6.33311 7.55797 6.33311 8C6.33311 8.44203 6.5087 8.86595 6.82126 9.17851C7.13383 9.49107 7.55775 9.66667 7.99978 9.66667Z" fill="#71717A"/>
                                        </svg>
                                    </a>
                                </div>
                            </div>

                            <!-- Submit Button -->
                            <div class="mt-8">
                                <button role="button" class="focus:ring-2 focus:ring-offset-2 focus:ring-blue-700 text-sm font-semibold leading-none text-white focus:outline-none bg-sky-800 border rounded hover:bg-sky-600 py-4 w-full">Entrar</button>
                            </div>

                            @if ($errors->any())
                                <div class="w-full mt-4">
                                    <div class="bg-red-100 border border-red-500 rounded text-red-900 px-4 py-3 mb-3 shadow-md" role="alert">
                                        <div class="flex items-center">
                                            <div class="py-1"><svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                                            <div>
                                                @foreach ($errors->all() as $error)
                                                    <p class="text-sm">{!! errorMessageFormat($error) !!}</p>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if(session()->has('status'))
                                @php($color = color(session('status')))
                                <div class="w-full mt-4">
                                    <div class="bg-{{ $color }}-100 border border-{{ $color }}-500 rounded text-{{ $color }}-900 px-4 py-3 mb-3 shadow-md" role="alert">
                                        <div class="flex items-center">
                                            <div class="py-1"><svg class="fill-current h-6 w-6 text-{{ $color }}-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                                            <div>
                                                <p class="text-sm">{!! errorMessageFormat(session('message')) !!}</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </form>
                    </div>
                </div>

                <!-- Footer -->
                <div class="mt-8 text-center text-sm text-gray-500">
                    <p>© {{ now()->format('Y') }} Santa Casa de Porto Alegre</p>
                    <p class="mt-1">Todos os direitos reservados</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Overlay de loading -->
    <div id="login-loading-overlay" style="display:none; position:fixed; inset:0; background:rgba(255,255,255,0.7); z-index:1000; align-items:center; justify-content:center;">
        <div style="text-align:center;">
            <div class="w-10 h-10 border-4 border-t-blue-500 border-gray-200 rounded-full animate-spin mx-auto mb-2"></div>
            <div class="text-gray-600 text-base font-medium">Entrando...</div>
        </div>
    </div>

    <script>
        function showPassword(id) {
            let _field = document.getElementById(id);
            if (_field.type === 'password') {
                _field.type = 'text'
            } else {
                _field.type = 'password'
            }
        }

        // Loading overlay on login submit
        document.addEventListener('DOMContentLoaded', function() {
            var form = document.getElementById('login-form');
            var overlay = document.getElementById('login-loading-overlay');
            if (form && overlay) {
                form.addEventListener('submit', function() {
                    overlay.style.display = 'flex';
                });
            }
        });
    </script>
</body>
</html>
