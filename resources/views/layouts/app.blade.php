<!DOCTYPE html>
<html class="scroll-smooth" lang="pt-BR">
<head>
    <!-- ... --->
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/noty/noty.css' ) }}"/>
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/noty/themes/nest.css' ) }}"/>
    <link rel="shortcut icon" type="image/x-icon" href="{{ secure_asset( 'images/favicon.ico') }}">
    <link rel="stylesheet" href="{{ secure_asset( '/vendor/fontawesome-free-6.3.0-web/css/all.min.css' ) }}"/>
    <link rel="stylesheet" href="https://cdn.datatables.net/2.2.2/css/dataTables.dataTables.min.css"/>

    <!-- Add Montserrat font from Google Fonts -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Add custom styles for Montserrat font -->
    <style>
        .font-montserrat {
            font-family: 'Montserrat', sans-serif;
        }
    </style>

    <!-- Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js', 'resources/js/patient-modal.js'])

    <title>{{ env( 'APP_NAME' ) }}</title>
</head>

<body class="flex flex-col h-screen text-gray-800 bg-gray-300 antialiased">

<div class="w-full h-full fixed block bg-white z-50" id="pre-loader">
    <span class="text-blue-500 opacity-75 top-1/2 mx-auto block relative text-center" style="top: 50%;">
        <i class="fas fa-spinner fa-3x animate-spin"></i>
    </span>
</div>

<!-- NAVBAR - Make it fixed -->
<header class="sticky top-0 z-40 w-full bg-white shadow-md">
    @include('partials.navbar')
</header>
<!-- NAVBAR -->

<!-- MENU MOBILE -->
@include('partials.menu-mobile')
<!-- MENU MOBILE -->

<!-- PRINCIPAL -->
<main class="flex-grow bg-gray-50 pt-2"> <!-- Added pt-2 to account for the fixed header -->

    <div class="relative pt-4 md:pt-6 pb-4 md:pb-10 flex justify-center"> <!-- Reduced padding from pt-10 to pt-6 -->

        <div class="container relative p-2 rounded pb-6">
            <div class="items-center flex flex-wrap">

                @if ( $errors->any() )
                    <div class="w-full px-4">
                        <div class="bg-red-100 border border-red-500 rounded text-red-900 px-4 py-3 mb-3 shadow-md" role="alert">
                            <div class="flex items-center">
                                <div class="py-1"><svg class="fill-current h-6 w-6 text-red-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                                <div>
                                    @foreach ( $errors->all() as $error )
                                        <p class="text-sm">{!! errorMessageFormat( $error ) !!}</p>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @if( session()->has( 'status' ) )
                    @php ( $color = color( session( 'status' ) ) )
                    <div class="w-full px-4">
                        <div class="hidden border-orange-500 bg-orange-100 text-orange-500 text-orange-900 border-red-500 bg-red-100 text-red-500 text-red-900 border-teal-500 bg-teal-100 text-teal-500 text-teal-900 bg-green-600 hover:bg-green-400 bg-sky-600 hover:bg-sky-400"></div>
                        <div class="bg-{{ $color }}-100 border border-{{ $color }}-500 rounded text-{{ $color }}-900 px-4 py-3 mb-3 shadow-md" role="alert">
                            <div class="flex items-center">
                                <div class="py-1"><svg class="fill-current h-6 w-6 text-{{ $color }}-500 mr-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20"><path d="M2.93 17.07A10 10 0 1 1 17.07 2.93 10 10 0 0 1 2.93 17.07zm12.73-1.41A8 8 0 1 0 4.34 4.34a8 8 0 0 0 11.32 11.32zM9 11V9h2v6H9v-4zm0-6h2v2H9V5z"/></svg></div>
                                <div>
                                    <p class="text-sm">{!! errorMessageFormat( session('message') ) !!}</p>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                @yield( 'content' )
            </div>
        </div>
    </div>
</main>
<!-- PRINCIPAL -->
@include( 'partials.footer' )

<script type="text/javascript" src="{{ asset( 'js/jquery.js' ) }}"></script>
<script src="{{ asset( '/js/common.js' . preventCache() ) }}"></script>
<script src="{{ asset( '/vendor/noty/noty.min.js' ) }}"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>

@stack( 'scripts' )

<!-- Moved from report.blade.php -->
<script src="{{ asset('js/sbar-autoscroll.js') }}"></script>
<script>
// Inicialização do modal de boas-vindas (primeira visita)
document.addEventListener('DOMContentLoaded', function() {
    const welcomeShown = localStorage.getItem('sbar_welcome_shown');
    if (welcomeShown) {
        const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
        if (welcomeElement && welcomeElement.__x) {
            welcomeElement.__x.$data.showWelcome = false;
        }
    }
});
</script>
<script>
// Inicialização adicional e verificação do Livewire
document.addEventListener('DOMContentLoaded', function() {
    const welcomeShown = localStorage.getItem('sbar_welcome_shown');
    if (welcomeShown) {
        const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
        if (welcomeElement && welcomeElement.__x) {
            welcomeElement.__x.$data.showWelcome = false;
        }
    }

    if (window.Livewire) {
        // Livewire carregado
        console.log('Livewire loaded successfully');
    }
});
</script>
<!-- End moved scripts -->

<script>
    // Previne que o usuário envie mais de uma requisição de formulário
    $(document).ready(function(){
        $("form").submit(function(){
            setTimeout(function() {
                $('input').attr('disabled', 'disabled');
                $('button').attr('disabled', 'disabled');
                $('a').attr('disabled', 'disabled');
            }, 50);
        });
    });

    $(function () {
        $( '#pre-loader' ).delay( 450 ).fadeOut( 'slow' );
    });
</script>

</body>
</html>