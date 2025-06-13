<div class="border-b border-gray-200 bg-white px-3 sm:px-6">
    <nav class="flex space-x-1 overflow-x-auto pb-2" style="scrollbar-width: thin;">
        <!-- Tab 1: SBAR Geral -->
        <button @click="activeTab = 'tab-1'"
                :class="activeTab === 'tab-1' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-t-lg border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                </svg>
                <span>SBAR Geral</span>
            </div>
        </button>
        
        <!-- Tab 2: Avaliação (renamed from SBAR por Turno) -->
        <button @click="activeTab = 'tab-2'"
                :class="activeTab === 'tab-2' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-t-lg border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>Avaliação</span>
            </div>
        </button>
        
        <!-- Tab 3: CPOE (moved to third position) -->
        <button @click="activeTab = 'tab-3'"
                :class="activeTab === 'tab-3' ? 'border-blue-500 bg-blue-50 text-blue-700' : 'border-transparent bg-gray-50 text-gray-600 hover:text-gray-800 hover:bg-gray-100'"
                class="flex-shrink-0 px-3 sm:px-4 py-2 text-xs sm:text-sm font-medium rounded-t-lg border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-1 sm:space-x-2">
                <svg xmlns="http://www.w3.org/2000/svg" class="h-3 w-3 sm:h-4 sm:w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v10a2 2 0 002 2h8a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4" />
                </svg>
                <span>CPOE</span>
            </div>
        </button>
    </nav>
</div>