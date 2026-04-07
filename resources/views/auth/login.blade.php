<!doctype html>
<html lang="pt-br" class="h-full">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" type="image/x-icon" href="{{ secure_asset('images/favicon.ico') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <title>{{ env('APP_NAME') }} - Login</title>

    <style>

        .login-fade-in {
            animation: fadeInUp 0.7s ease-out;
        }
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .solid-input {
            background: #f3f4f6;
            border: 1px solid #e5e7eb;
            color: #1e3a5f;
            transition: border-color 0.2s, box-shadow 0.2s;
        }
        .solid-input::placeholder { color: #9ca3af; }
        .solid-input:focus {
            outline: none;
            background: #fff;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59,130,246,0.15);
        }
    </style>
</head>

<body class="h-full">

    {{-- ── Full-screen background ── --}}
    <div class="relative min-h-full flex items-center justify-center overflow-hidden">

        {{-- Hospital background photo --}}
        <img
            class="absolute inset-0 w-full h-full object-cover object-center"
            src="{{ asset('images/login-photo.png') }}"
            alt=""
            aria-hidden="true"
        />

        {{-- Deep navy gradient overlay --}}
        <div class="absolute inset-0" style="background: linear-gradient(155deg, rgba(0,20,70,0.90) 0%, rgba(0,55,130,0.70) 50%, rgba(0,15,60,0.92) 100%);"></div>

        {{-- Radial glow for depth --}}
        <div class="absolute inset-0" style="background: radial-gradient(ellipse 85% 55% at 50% 45%, rgba(56,130,246,0.18) 0%, transparent 70%);"></div>

        {{-- ── Login card ── --}}
        <div class="relative z-10 w-full max-w-sm px-5 py-8 sm:px-0 login-fade-in">

            {{-- Branding --}}
            <div class="text-center mb-7">
                <img
                    class="mx-auto w-44 mb-5 drop-shadow-lg"
                    src="{{ asset('images/santacasa-horizontal-branco.svg') }}"
                    alt="Santa Casa de Porto Alegre"
                />
                <h1 class="text-lg font-bold text-white tracking-wide leading-snug">Sistema de Passagem de Plantão</h1>
                <p class="text-blue-200 text-sm mt-1 font-light">Santa Casa de Porto Alegre</p>
            </div>

            {{-- Card --}}
            <div class="bg-white rounded-2xl p-7 shadow-2xl">

                <h2 class="text-sm font-medium text-gray-600 text-center mb-6">Acesse com suas credenciais da rede</h2>

                <form method="post" action="{{ route('login') }}" id="login-form">
                    @csrf

                    {{-- Username --}}
                    <div>
                        <input
                            required
                            placeholder="Usuário"
                            id="username"
                            name="username"
                            aria-labelledby="username"
                            type="text"
                            autocomplete="username"
                            class="solid-input rounded-xl text-sm py-3.5 w-full px-4"
                        />
                    </div>

                    {{-- Password --}}
                    <div class="mt-4">
                        <div class="relative flex items-center">
                            <input
                                required
                                placeholder="Senha"
                                id="password"
                                name="password"
                                type="password"
                                autocomplete="current-password"
                                class="solid-input rounded-xl text-sm py-3.5 w-full px-4 pr-11"
                            />
                            <a href="#" onclick="event.preventDefault(); showPassword('password')" class="absolute right-4 text-gray-400 hover:text-gray-600 transition-colors cursor-pointer">
                                <svg width="18" height="18" viewBox="0 0 16 16" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path d="M7.99978 2C11.5944 2 14.5851 4.58667 15.2124 8C14.5858 11.4133 11.5944 14 7.99978 14C4.40511 14 1.41444 11.4133 0.787109 8C1.41378 4.58667 4.40511 2 7.99978 2ZM7.99978 12.6667C9.35942 12.6664 10.6787 12.2045 11.7417 11.3568C12.8047 10.509 13.5484 9.32552 13.8511 8C13.5473 6.67554 12.8031 5.49334 11.7402 4.64668C10.6773 3.80003 9.35864 3.33902 7.99978 3.33902C6.64091 3.33902 5.32224 3.80003 4.25936 4.64668C3.19648 5.49334 2.45229 6.67554 2.14844 8C2.45117 9.32552 3.19489 10.509 4.25787 11.3568C5.32085 12.2045 6.64013 12.6664 7.99978 12.6667ZM7.99978 11C7.20413 11 6.44106 10.6839 5.87846 10.1213C5.31585 9.55871 4.99978 8.79565 4.99978 8C4.99978 7.20435 5.31585 6.44129 5.87846 5.87868C6.44106 5.31607 7.20413 5 7.99978 5C8.79543 5 9.55849 5.31607 10.1211 5.87868C10.6837 6.44129 10.9998 7.20435 10.9998 8C10.9998 8.79565 10.6837 9.55871 10.1211 10.1213C9.55849 10.6839 8.79543 11 7.99978 11ZM7.99978 9.66667C8.4418 9.66667 8.86573 9.49107 9.17829 9.17851C9.49085 8.86595 9.66644 8.44203 9.66644 8C9.66644 7.55797 9.49085 7.13405 9.17829 6.82149C8.86573 6.50893 8.4418 6.33333 7.99978 6.33333C7.55775 6.33333 7.13383 6.50893 6.82126 6.82149C6.5087 7.13405 6.33311 7.55797 6.33311 8C6.33311 8.44203 6.5087 8.86595 6.82126 9.17851C7.13383 9.49107 7.55775 9.66667 7.99978 9.66667Z" fill="currentColor"/>
                                </svg>
                            </a>
                        </div>
                    </div>

                    {{-- Submit --}}
                    <div class="mt-6">
                        <button
                            type="submit"
                            class="w-full py-3.5 px-4 bg-[#004D9D] text-white font-semibold rounded-xl text-sm hover:bg-blue-700 active:bg-blue-800 focus:outline-none focus:ring-2 focus:ring-blue-400 transition shadow-lg tracking-wide"
                        >
                            Entrar
                        </button>
                    </div>

                    {{-- Errors --}}
                    @if ($errors->any())
                        <div class="mt-5 rounded-xl px-4 py-3 bg-red-50 border border-red-300" role="alert">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-red-500 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <div>
                                    @foreach ($errors->all() as $error)
                                        <p class="text-sm text-red-700 font-medium">{!! errorMessageFormat($error) !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    @endif

                    {{-- Status flash --}}
                    @if(session()->has('status'))
                        @php($color = color(session('status')))
                        <div class="mt-5 bg-{{ $color }}-50 border border-{{ $color }}-300 rounded-xl px-4 py-3" role="alert">
                            <div class="flex items-start gap-3">
                                <svg class="w-5 h-5 text-{{ $color }}-500 mt-0.5 flex-shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/>
                                </svg>
                                <p class="text-sm text-{{ $color }}-700 font-medium">{!! errorMessageFormat(session('message')) !!}</p>
                            </div>
                        </div>
                    @endif

                </form>
            </div>

        </div>
    </div>

    {{-- Footer fixo na base --}}
    <div class="absolute bottom-0 left-0 right-0 z-10 pb-4 text-center space-y-1">
        <p class="text-xs text-blue-200/50">
            © {{ now()->format('Y') }} Santa Casa de Porto Alegre &middot; Todos os direitos reservados
        </p>
        <p class="text-xs text-blue-200/40">
            <span class="text-blue-200/60 font-medium">Centro de Inovação</span>
                &middot;
                <a href="{{ env('DEVELOPED_BY_URL') }}" target="_blank" rel="noopener noreferrer"
                   class="inline-flex items-center gap-1 text-blue-200/60 hover:text-blue-300 transition-colors font-medium">
                    <span>Desenvolvido por</span>
                    <svg stroke="currentColor" fill="currentColor" stroke-width="0" viewBox="0 0 448 512" class="w-3 h-3 text-[#0A66C2]" xmlns="http://www.w3.org/2000/svg">
                        <path d="M416 32H31.9C14.3 32 0 46.5 0 64.3v383.4C0 465.5 14.3 480 31.9 480H416c17.6 0 32-14.5 32-32.3V64.3c0-17.8-14.4-32.3-32-32.3zM135.4 416H69V202.2h66.5V416zm-33.2-243c-21.3 0-38.5-17.3-38.5-38.5S80.9 96 102.2 96c21.2 0 38.5 17.3 38.5 38.5 0 21.3-17.2 38.5-38.5 38.5zm282.1 243h-66.4V312c0-24.8-.5-56.7-34.5-56.7-34.6 0-39.9 27-39.9 54.9V416h-66.4V202.2h63.7v29.2h.9c8.9-16.8 30.6-34.5 62.9-34.5 67.2 0 79.7 44.3 79.7 101.9V416z"/>
                    </svg>
                    {{ env('DEVELOPED_BY') }}
                </a>
        </p>
    </div>

    {{-- Loading overlay --}}
    <div id="login-loading-overlay" style="display:none; position:fixed; inset:0; background:rgba(0,20,70,0.78); backdrop-filter:blur(4px); -webkit-backdrop-filter:blur(4px); z-index:1000; align-items:center; justify-content:center;">
        <div style="text-align:center;">
            <div class="w-10 h-10 border-4 border-t-white border-white/20 rounded-full animate-spin mx-auto mb-3"></div>
            <div class="text-white text-sm font-medium tracking-wide">Entrando...</div>
        </div>
    </div>

    <script>
        function showPassword(id) {
            const field = document.getElementById(id);
            field.type = field.type === 'password' ? 'text' : 'password';
        }

        document.addEventListener('DOMContentLoaded', function () {
            const form = document.getElementById('login-form');
            const overlay = document.getElementById('login-loading-overlay');
            if (form && overlay) {
                form.addEventListener('submit', function () {
                    overlay.style.display = 'flex';
                });
            }
        });
    </script>
</body>
</html>
