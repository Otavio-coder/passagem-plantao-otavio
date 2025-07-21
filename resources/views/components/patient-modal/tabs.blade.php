<div  class="bg-white border-b border-gray-200 px-2 sm:px-3 md:px-6 py-2 sm:py-3 flex-shrink-0">
    <nav class="flex space-x-1 overflow-x-auto" style="scrollbar-width: thin;">
        <!-- S - Situação -->
        <button 
            @click="activeTab = 'tab-s'; $dispatch('tab-change', 'tab-s')"
            :class="activeTab === 'tab-s' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-shrink-0 px-1.5 sm:px-2 md:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-4 w-4 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#007D44] text-white text-xs font-bold">S</span>
                <span class="hidden md:inline">SITUAÇÃO</span>
                <span class="md:hidden hidden sm:inline">SIT</span>
                <span class="sm:hidden">S</span>
            </div>
        </button>
        
        <!-- B - Background -->
        <button 
            @click="activeTab = 'tab-b'; $dispatch('tab-change', 'tab-b')"
            :class="activeTab === 'tab-b' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-shrink-0 px-1.5 sm:px-2 md:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-4 w-4 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#007D44] text-white text-xs font-bold">B</span>
                <span class="hidden xl:inline">BACKGROUND (INFORMAÇÃO)</span>
                <span class="xl:hidden hidden lg:inline">BACKGROUND</span>
                <span class="lg:hidden hidden md:inline">INFO</span>
                <span class="md:hidden hidden sm:inline">BG</span>
                <span class="sm:hidden">B</span>
            </div>
        </button>
        
        <!-- A - Avaliação -->
        <button 
            wire:click="$call('openChatSession')"
            @click="activeTab = 'tab-a'; $dispatch('tab-change', 'tab-a')"
            :class="activeTab === 'tab-a' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-shrink-0 px-1.5 sm:px-2 md:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-4 w-4 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#ff6b35] text-white text-xs font-bold">A</span>
                <span class="hidden md:inline">AVALIAÇÃO</span>
                <span class="md:hidden hidden sm:inline">AVAL</span>
                <span class="sm:hidden">A</span>
            </div>
        </button>
        
        <!-- R - Recomendações -->
        <button 
            @click="activeTab = 'tab-r'; $dispatch('tab-change', 'tab-r')"
            :class="activeTab === 'tab-r' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-shrink-0 px-1.5 sm:px-2 md:px-4 py-1.5 sm:py-2 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <span class="inline-flex items-center justify-center h-4 w-4 sm:h-5 sm:w-5 md:h-6 md:w-6 rounded-full bg-[#28a745] text-white text-xs font-bold">R</span>
                <span class="hidden md:inline">RECOMENDAÇÕES</span>
                <span class="md:hidden hidden sm:inline">REC</span>
                <span class="sm:hidden">R</span>
            </div>
        </button>
    </nav>
</div>