<div class="bg-white border-b border-gray-200 px-1 sm:px-3 md:px-6 py-2 sm:py-3 flex-shrink-0">
    <nav class="flex space-x-0 overflow-x-auto scrollbar-hide" style="scrollbar-width: none; -ms-overflow-style: none;">
        <!-- S - Situação -->
        <button 
            @click="activeTab = 'tab-s'; currentTabIndex = 0; $dispatch('tab-change', 'tab-s')"
            :class="activeTab === 'tab-s' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 sm:flex-shrink-0 px-1 sm:px-4 py-2 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors min-w-0 touch-manipulation"
            style="min-height: 44px;">
            <div class="flex items-center justify-center sm:justify-start space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-5 w-5 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#007D44] text-white text-xs font-bold flex-shrink-0">S</span>
                <span class="hidden lg:inline">SITUAÇÃO</span>
                <span class="lg:hidden hidden md:inline">SIT</span>
                <span class="md:hidden hidden sm:inline text-xs">SIT</span>
            </div>
        </button>
        
        <!-- B - Background -->
        <button 
            @click="activeTab = 'tab-b'; currentTabIndex = 1; $dispatch('tab-change', 'tab-b')"
            :class="activeTab === 'tab-b' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 sm:flex-shrink-0 px-1 sm:px-4 py-2 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors min-w-0 touch-manipulation"
            style="min-height: 44px;">
            <div class="flex items-center justify-center sm:justify-start space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-5 w-5 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#007D44] text-white text-xs font-bold flex-shrink-0">B</span>
                <span class="hidden xl:inline">BACKGROUND</span>
                <span class="xl:hidden hidden lg:inline">INFO</span>
                <span class="lg:hidden hidden md:inline">BG</span>
                <span class="md:hidden hidden sm:inline text-xs">BG</span>
            </div>
        </button>
        
        <!-- A - Avaliação -->
        <button 
            @click="activeTab = 'tab-a'; currentTabIndex = 2; $dispatch('tab-change', 'tab-a')"
            :class="activeTab === 'tab-a' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 sm:flex-shrink-0 px-1 sm:px-4 py-2 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors min-w-0 touch-manipulation"
            style="min-height: 44px;">
            <div class="flex items-center justify-center sm:justify-start space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-5 w-5 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#ff6b35] text-white text-xs font-bold flex-shrink-0">A</span>
                <span class="hidden lg:inline">AVALIAÇÃO</span>
                <span class="lg:hidden hidden md:inline">AVAL</span>
                <span class="md:hidden hidden sm:inline text-xs">AVAL</span>
            </div>
        </button>
        
        <!-- R - Recomendações -->
        <button 
            @click="activeTab = 'tab-r'; currentTabIndex = 3; $dispatch('tab-change', 'tab-r')"
            :class="activeTab === 'tab-r' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 sm:flex-shrink-0 px-1 sm:px-4 py-2 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors min-w-0 touch-manipulation"
            style="min-height: 44px;">
            <div class="flex items-center justify-center sm:justify-start space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-5 w-5 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#28a745] text-white text-xs font-bold flex-shrink-0">R</span>
                <span class="hidden lg:inline">RECOMENDAÇÕES</span>
                <span class="lg:hidden hidden md:inline">REC</span>
                <span class="md:hidden hidden sm:inline text-xs">REC</span>
            </div>
        </button>
    </nav>
</div>

<style>
.scrollbar-hide::-webkit-scrollbar {
    display: none;
}
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

/* Ensure proper touch targets on mobile */
@media (max-width: 640px) {
    button.touch-manipulation {
        min-height: 44px;
        min-width: 44px;
        touch-action: manipulation;
    }
}
</style>