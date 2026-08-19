<div class="bg-white">
    <nav class="flex overflow-x-auto scrollbar-hide">
        {{-- S - Situação --}}
        <button 
            @click.prevent="switchTab('tab-s')"
            :class="activeTab === 'tab-s' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 min-w-[80px] sm:flex-shrink-0 px-3 sm:px-6 py-2 sm:py-2.5 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-all duration-200"
            type="button"
        >
            <div class="flex items-center justify-center gap-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#007D44] text-white text-xs font-bold flex-shrink-0">S</span>
                <span class="hidden sm:inline">SITUAÇÃO</span>
            </div>
        </button>
        
        {{-- B - Background --}}
        <button 
            @click.prevent="switchTab('tab-b')"
            :class="activeTab === 'tab-b' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 min-w-[80px] sm:flex-shrink-0 px-3 sm:px-6 py-2 sm:py-2.5 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-all duration-200"
            type="button"
        >
            <div class="flex items-center justify-center gap-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#007D44] text-white text-xs font-bold flex-shrink-0">B</span>
                <span class="hidden sm:inline">BACKGROUND</span>
            </div>
        </button>
        
        {{-- A - Avaliação --}}
        <button 
            @click.prevent="switchTab('tab-a')"
            :class="activeTab === 'tab-a' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 min-w-[80px] sm:flex-shrink-0 px-3 sm:px-6 py-2 sm:py-2.5 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-all duration-200"
            type="button"
        >
            <div class="flex items-center justify-center gap-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#ff6b35] text-white text-xs font-bold flex-shrink-0">A</span>
                <span class="hidden sm:inline">AVALIAÇÃO</span>
            </div>
        </button>
        
        {{-- R - Recomendações --}}
        <button 
            @click.prevent="switchTab('tab-r')"
            :class="activeTab === 'tab-r' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
            class="flex-1 min-w-[80px] sm:flex-shrink-0 px-3 sm:px-6 py-2 sm:py-2.5 text-xs sm:text-sm font-medium border-b-2 whitespace-nowrap transition-all duration-200"
            type="button"
        >
            <div class="flex items-center justify-center gap-2">
                <span class="flex items-center justify-center w-6 h-6 rounded-full bg-[#28a745] text-white text-xs font-bold flex-shrink-0">R</span>
                <span class="hidden sm:inline">RECOMENDAÇÕES</span>
            </div>
        </button>
    </nav>
</div>

<style>
.scrollbar-hide {
    -ms-overflow-style: none;
    scrollbar-width: none;
}

.scrollbar-hide::-webkit-scrollbar {
    display: none;
}

/* Touch target mobile */
@media (max-width: 640px) {
    button[type="button"] {
        min-height: 44px;
        touch-action: manipulation;
    }
}
</style>