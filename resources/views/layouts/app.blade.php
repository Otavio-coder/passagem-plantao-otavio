<!DOCTYPE html>
<html class="scroll-smooth" lang="pt-BR">
<head>
    <!-- ...existing head content... -->
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

<!-- Global Loading Overlay - Lower z-index and more specific targeting -->
<div class="w-full h-full fixed block bg-white/90 backdrop-blur-sm z-[9998]" 
     id="pre-loader" 
     x-data="{ show: true }"
     x-show="show"
     x-init="
         // Hide after DOM ready
         document.addEventListener('DOMContentLoaded', () => {
             setTimeout(() => show = false, 450);
         });
         
         // Don't show during modal operations
         window.addEventListener('modal-opening', () => show = false);
     ">
    <div class="flex flex-col items-center justify-center min-h-screen">
        <div class="w-12 h-12 border-4 border-t-[#004D9D] border-gray-200 rounded-full animate-spin mb-4"></div>
        <span class="text-[#004D9D] font-medium">Carregando sistema...</span>
    </div>
</div>

<!-- NAVBAR -->
<header class="sticky top-0 z-40 w-full bg-white shadow-md">
    @include('partials.navbar')
</header>

<!-- MENU MOBILE -->
@include('partials.menu-mobile')

<!-- PRINCIPAL -->
<main class="flex-grow bg-gray-50 pt-2">
    <div class="relative pt-4 md:pt-6 pb-4 md:pb-10 flex justify-center">
        <div class="container relative p-2 rounded pb-6">
            <div class="items-center flex flex-wrap">
                @yield('content')
            </div>
        </div>
    </div>
</main>

@include('partials.footer')

<!-- Scripts -->
<script type="text/javascript" src="{{ asset('js/jquery.js') }}"></script>
<script src="{{ asset('/js/common.js' . preventCache()) }}"></script>
<script src="{{ asset('/vendor/noty/noty.min.js') }}"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>

@stack('scripts')

<script src="{{ asset('js/sbar-autoscroll.js') }}"></script>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const preLoader = document.getElementById('pre-loader');
    
    // Hide initial loader after DOM is ready
    setTimeout(() => {
        if (preLoader) {
            preLoader.style.display = 'none';
        }
    }, 450);

    // Livewire loading control - BUT NOT FOR MODALS
    if (typeof Livewire !== 'undefined') {
        let isModalOperation = false;
        
        // Listen for modal events
        window.addEventListener('modal-opening', () => {
            isModalOperation = true;
            console.log('Modal operation detected - disabling global loader');
        });
        
        document.addEventListener('livewire:init', () => {
            // Hook into Livewire navigation events
            Livewire.hook('request', ({ uri, options, payload, respond, succeed, fail }) => {
                // Don't show global loader for modal operations
                if (!isModalOperation && preLoader) {
                    preLoader.style.display = 'block';
                }
            });

            // Hide loading when request completes
            Livewire.hook('commit', ({ component, commit, respond, succeed, fail }) => {
                setTimeout(() => {
                    if (preLoader) {
                        preLoader.style.display = 'none';
                    }
                    isModalOperation = false; // Reset flag
                }, 100);
            });

            // Also hide on morph completion
            Livewire.hook('morph.updated', ({ el, component }) => {
                setTimeout(() => {
                    if (preLoader) {
                        preLoader.style.display = 'none';
                    }
                }, 50);
            });
        });
    }

    // Welcome modal logic
    const welcomeShown = localStorage.getItem('sbar_welcome_shown');
    if (welcomeShown) {
        const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
        if (welcomeElement && welcomeElement.__x) {
            welcomeElement.__x.$data.showWelcome = false;
        }
    }
});

// Form submission prevention
if (typeof $ !== 'undefined') {
    $(document).ready(function(){
        $("form").submit(function(){
            setTimeout(function() {
                $('input').attr('disabled', 'disabled');
                $('button').attr('disabled', 'disabled');
                $('a').attr('disabled', 'disabled');
            }, 50);
        });
    });
}
</script>

</body>
</html>