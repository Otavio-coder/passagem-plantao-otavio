
<div class="font-montserrat relative">
    <!-- Sistema de Aviso Placeholder -->
    <div class="mb-4 sm:mb-6 bg-blue-50 border-l-4 border-blue-400 p-3 sm:p-4 shadow-sm animate-pulse">
        <div class="flex flex-col sm:flex-row sm:items-start">
            <div class="flex items-start">
                <div class="flex-shrink-0">
                    <div class="h-5 w-5 bg-blue-200 rounded mt-0.5"></div>
                </div>
                <div class="ml-3 flex-1 sm:mr-8">
                    <div class="space-y-2">
                        <div class="h-4 bg-blue-200 rounded w-3/4"></div>
                        <div class="h-3 bg-blue-200 rounded w-full"></div>
                        <div class="h-3 bg-blue-200 rounded w-5/6"></div>
                        <div class="h-3 bg-blue-200 rounded w-4/5"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main container placeholder -->
    <div class="relative bg-gradient-to-br from-gray-100 to-gray-200 rounded-xl shadow-xl overflow-hidden">
        <!-- Header Skeleton -->
        <div class="bg-[#004D9D]/90 px-3 sm:px-4 lg:px-8 py-2 sm:py-3 lg:py-4">
            <div class="animate-pulse">
                <!-- Title placeholder -->
                <div class="h-6 bg-white/20 rounded w-2/3 mx-auto mb-4"></div>
                
                <!-- Controls grid placeholder -->
                <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6 gap-2 xl:gap-4">
                    <?php for($i = 0; $i < 6; $i++): ?>
                        <div class="space-y-1">
                            <div class="h-3 bg-white/10 rounded w-16"></div>
                            <div class="h-8 bg-white/10 rounded"></div>
                        </div>
                    <?php endfor; ?>
                </div>
            </div>
        </div>
        
        <!-- Content area skeleton -->
        <div class="p-3 sm:p-4 lg:p-6 xl:p-8 bg-white">
            <!-- Loading message -->
            <div class="flex flex-col items-center justify-center py-12 mb-8">
                <div class="w-16 h-16 border-t-4 border-r-4 border-[#0071B9] border-solid rounded-full animate-spin mb-4"></div>
                <p class="text-gray-500 text-sm mt-2">Inicializando dados de pacientes e estrutura hospitalar...</p>
            </div>
            
            <!-- Cards grid skeleton -->
            <div class="grid grid-cols-1 sm:grid-cols-1 md:grid-cols-2 lg:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-4 gap-4 sm:gap-5 lg:gap-6">
                <?php for($i = 0; $i < 8; $i++): ?>
                    <div class="animate-pulse">
                        <div class="bg-gradient-to-br from-gray-200 to-gray-300 h-64 sm:h-72 md:h-80 lg:h-80 rounded-xl shadow-lg">
                            <div class="p-4 h-full flex flex-col">
                                <!-- Header skeleton -->
                                <div class="flex justify-between mb-3">
                                    <div class="h-6 bg-gray-400/30 rounded-full w-20"></div>
                                    <div class="h-6 bg-gray-400/30 rounded-full w-16"></div>
                                </div>
                                
                                <!-- Patient info skeleton -->
                                <div class="flex items-center mb-3">
                                    <div class="h-10 w-10 bg-gray-400/30 rounded-full"></div>
                                    <div class="ml-3 flex-1 space-y-1">
                                        <div class="h-4 bg-gray-400/30 rounded w-3/4"></div>
                                        <div class="h-3 bg-gray-400/30 rounded w-1/2"></div>
                                        <div class="h-3 bg-gray-400/30 rounded w-2/3"></div>
                                    </div>
                                </div>
                                
                                <!-- Content skeleton -->
                                <div class="space-y-2 flex-grow">
                                    <div class="h-3 bg-gray-400/30 rounded w-full"></div>
                                    <div class="h-3 bg-gray-400/30 rounded w-5/6"></div>
                                    <div class="h-3 bg-gray-400/30 rounded w-4/6"></div>
                                    <div class="h-3 bg-gray-400/30 rounded w-3/4"></div>
                                    <div class="h-3 bg-gray-400/30 rounded w-2/3"></div>
                                </div>
                                
                                <!-- Footer skeleton -->
                                <div class="flex justify-between items-center mt-auto">
                                    <div class="h-4 bg-gray-400/30 rounded w-16"></div>
                                    <div class="h-3 bg-gray-400/30 rounded w-24"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endfor; ?>
            </div>
        </div>
    </div>
    
    <!-- Legend skeleton -->
    <div class="mt-6 mb-16 lg:mb-6 p-3 sm:p-4 md:p-6 bg-white rounded-xl shadow-lg border border-gray-100 animate-pulse">
        <div class="h-6 bg-gray-200 rounded w-24 mb-4"></div>
        
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 sm:gap-6">
            <!-- Legend sections skeleton -->
            <?php for($section = 0; $section < 2; $section++): ?>
                <div>
                    <div class="h-5 bg-gray-200 rounded w-32 mb-3"></div>
                    <div class="space-y-2">
                        <?php for($item = 0; $item < 4; $item++): ?>
                            <div class="flex items-center">
                                <div class="w-6 h-6 bg-gray-200 rounded mr-3"></div>
                                <div class="h-4 bg-gray-200 rounded w-24"></div>
                            </div>
                        <?php endfor; ?>
                    </div>
                </div>
            <?php endfor; ?>
        </div>
    </div>
</div><?php /**PATH /var/www/passagem-plantao/resources/views/livewire/sbar-report-placeholder.blade.php ENDPATH**/ ?>