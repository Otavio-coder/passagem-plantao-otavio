<div class="fixed w-3/4 block z-50 hidden" id="mobile-menu">
    <div style="z-index: -1;" class="fixed inset-0 transition-opacity">
        <div class="absolute inset-0 bg-gray-500 opacity-75"></div>
    </div>
    <div class="bg-white h-screen p-3">

        <!-- Menu Button -->
        <div class="flex justify-end">
            <button onclick="event.preventDefault(); toggleNavbar('mobile-menu')" class="grid rounded-md focus:outline-none text-sky-700">
                <svg class="h-6 w-6" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                </svg>
            </button>
        </div>

        <div class="flex flex-col divide-y">

            <a href="<?php echo e(route('home')); ?>" class="flex justify-between items-center text-sm text-sky-600 mt-4">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Inicio</span>
                    <span class="text-xs text-gray-400">Página inicial do sistema</span>
                </div>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                </svg>
            </a>
            <a href="<?php echo e(route('feedback')); ?>" class="flex justify-between items-center text-sm text-sky-600 mt-4">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Feedback</span>
                    <span class="text-xs text-gray-400">Envie sua opinião sobre o sistema</span>
                </div>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                </svg>
            </a>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check( 'ver usuarios' )): ?>
                <a href="<?php echo e(route('users.index')); ?>" class="flex justify-between items-center text-sm text-sky-600">
                    <div class="px-2 pt-2 pb-3 flex flex-col">
                        <span class="font-semibold">Usuários</span>
                        <span class="text-xs text-gray-400">Gerencie o cadastro de usuários</span>
                    </div>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check( 'ver perfis' )): ?>
                <a href="<?php echo e(route('profiles.index')); ?>" class="flex justify-between items-center text-sm text-sky-600">
                    <div class="px-2 pt-2 pb-3 flex flex-col">
                        <span class="font-semibold">Perfis</span>
                        <span class="text-xs text-gray-400">Gerencie o cadastro de perfis</span>
                    </div>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('configurar sistema')): ?>
                <a href="<?php echo e(route('system-configuration.index')); ?>" class="flex justify-between items-center text-sm text-sky-600">
                    <div class="px-2 pt-2 pb-3 flex flex-col">
                        <span class="font-semibold">Configurações</span>
                        <span class="text-xs text-gray-400">Configure hospitais e setores</span>
                    </div>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check('configurar sistema')): ?>
                <a href="<?php echo e(route('chat-auditoria')); ?>" class="flex justify-between items-center text-sm text-sky-600">
                    <div class="px-2 pt-2 pb-3 flex flex-col">
                        <span class="font-semibold">Chat</span>
                        <span class="text-xs text-gray-400">Auditoria do chat</span>
                    </div>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                    </svg>
                </a>
            <?php endif; ?>
            <?php if (app(\Illuminate\Contracts\Auth\Access\Gate::class)->check( 'ver logs' )): ?>
                <a href="<?php echo e(route('logs')); ?>" class="flex justify-between items-center text-sm text-sky-600">
                    <div class="px-2 pt-2 pb-3 flex flex-col">
                        <span class="font-semibold">Logs</span>
                        <span class="text-xs text-gray-400">Verifique os logs do sistema</span>
                    </div>
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                    </svg>
                </a>
            <?php endif; ?>
        </div>

    </div>
</div>

<div class="fixed w-3/4 block z-50 hidden right-0" id="profile-menu">
    <div style="z-index: -1;" class="fixed right-0 transition-opacity">
        <div class="absolute right-0 bg-gray-500 opacity-75"></div>
    </div>
    <div class="bg-white h-screen p-3">

        <!-- Menu Button -->
        <div class="flex justify-start">
            <button href="#" onclick="event.preventDefault(); toggleNavbar('profile-menu')" class="bg-sky-700 rounded-full flex items-center">
                <span class="flex items-center justify-center text-white rounded-full h-8 w-8">
                    <i class="fa fa-user"></i>
                </span>
            </button>
        </div>

        <div class="flex flex-col divide-y">

            <a href onclick="event.preventDefault(); document.getElementById('logout-form').submit();" class="flex justify-between items-center text-sm text-sky-600 mt-4">
                <div class="px-2 pt-2 pb-3 flex flex-col">
                    <span class="font-semibold">Sair</span>
                    <span class="text-xs text-gray-400">Encerrar a sessão no sistema</span>
                </div>
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 4.5l7.5 7.5-7.5 7.5"></path>
                </svg>
                <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" class="hidden">
                    <?php echo csrf_field(); ?>
                </form>
            </a>

        </div>

    </div>
</div>
                <form id="logout-form" action="<?php echo e(route('logout')); ?>" method="POST" class="hidden">
                    <?php echo csrf_field(); ?>
                </form>
            </a>

        </div>

    </div>
</div>
<?php /**PATH /var/www/passagem-plantao/resources/views/partials/menu-mobile.blade.php ENDPATH**/ ?>