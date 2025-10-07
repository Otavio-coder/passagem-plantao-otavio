<!DOCTYPE html>
<html class="scroll-smooth" lang="pt-BR">
<head>
    <meta charset="UTF-8"/>
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <link rel="stylesheet" href="<?php echo e(secure_asset( '/vendor/noty/noty.css' )); ?>"/>
    <link rel="stylesheet" href="<?php echo e(secure_asset( '/vendor/noty/themes/nest.css' )); ?>"/>
    <link rel="shortcut icon" type="image/x-icon" href="<?php echo e(secure_asset( 'images/favicon.ico')); ?>">
    <link rel="stylesheet" href="<?php echo e(secure_asset( '/vendor/fontawesome-free-6.3.0-web/css/all.min.css' )); ?>"/>
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
    <?php echo app('Illuminate\Foundation\Vite')(['resources/css/app.css', 'resources/js/app.js', 'resources/js/patient-modal.js', 'resources/js/chat-component-global.js']); ?>

    <title><?php echo e(env( 'APP_NAME' )); ?></title>

    <?php $config = (new \LaravelPWA\Services\ManifestService)->generate(); echo $__env->make( 'laravelpwa::meta' , ['config' => $config])->render(); ?>
</head>

<body class="flex flex-col h-screen text-gray-800 bg-gray-300 antialiased">

<!-- NAVBAR -->
<header class="sticky top-0 z-40 w-full bg-white shadow-md">
    <?php echo $__env->make('partials.navbar', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
</header>

<!-- MENU MOBILE -->
<?php echo $__env->make('partials.menu-mobile', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

    <!-- Global Modal Loading Overlay - IMPROVED -->
    <div id="modal-global-loading" class="fixed inset-0 z-[99999] hidden">
        <!-- Backdrop com blur mais intenso -->
        <div class="absolute inset-0 bg-[#004D9D]/40 backdrop-blur-sm"></div>
        
        <!-- Loading content centralizado -->
        <div class="relative flex items-center justify-center min-h-screen">
            <div class="bg-white rounded-xl shadow-2xl p-6 flex flex-col items-center space-y-4 mx-4">
                <!-- Spinner animado -->
                <div class="relative">
                    <div class="w-16 h-16 border-4 border-gray-200 rounded-full"></div>
                    <div class="w-16 h-16 border-4 border-t-[#004D9D] border-transparent rounded-full animate-spin absolute top-0 left-0"></div>
                </div>
                
                <!-- Texto de loading -->
                <div class="text-center">
                    <h3 class="text-lg font-semibold text-[#004D9D] mb-1">Carregando Paciente</h3>
                    <p class="text-sm text-gray-600">Aguarde enquanto carregamos os dados...</p>
                </div>
                
                <!-- Indicador de progresso (opcional) -->
                <div class="w-48 h-1 bg-gray-200 rounded-full overflow-hidden">
                    <div class="h-full bg-[#004D9D] rounded-full animate-pulse"></div>
                </div>
            </div>
        </div>
    </div>

<!-- PRINCIPAL -->
<main class="flex-grow bg-gray-50 pt-2">
    <div class="relative py-2 flex justify-center">
        <div class="w-full max-w-full relative px-1 lg:px-2">
            <div class="items-center flex flex-wrap">
                <?php echo $__env->yieldContent('content'); ?>
            </div>
        </div>
    </div>
</main>

<?php echo $__env->make('partials.footer', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

<!-- Scripts -->
<script type="text/javascript" src="<?php echo e(asset('js/jquery.js')); ?>"></script>
<script src="<?php echo e(asset('/js/common.js' . preventCache())); ?>"></script>
<script src="<?php echo e(asset('/vendor/noty/noty.min.js')); ?>"></script>
<script src="https://cdn.datatables.net/2.2.2/js/dataTables.min.js"></script>


<?php echo $__env->yieldPushContent('scripts'); ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Welcome modal logic
    const welcomeShown = localStorage.getItem('sbar_welcome_shown');
    if (welcomeShown) {
        const welcomeElement = document.querySelector('[x-data*="showWelcome"]');
        if (welcomeElement && welcomeElement.__x) {
            welcomeElement.__x.$data.showWelcome = false;
        }
    }
    
    // Debugging: Log when overlay element is found
    const overlay = document.getElementById('modal-global-loading');
    if (overlay) {
        //console.log('Global modal loading overlay found in DOM');
    } else {
        //console.warn('Global modal loading overlay NOT found in DOM');
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
</html><?php /**PATH /var/www/passagem-plantao/resources/views/layouts/app.blade.php ENDPATH**/ ?>