<div class="bg-white border-b border-gray-200 px-6 py-3 flex-shrink-0">
    <nav class="flex space-x-1 overflow-x-auto" style="scrollbar-width: thin;">
        <!-- S - Situação -->
        <button @click="activeTab = 'tab-s'"
                :class="activeTab === 'tab-s' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#007D44] text-white text-xs font-bold">S</span>
                <span>SITUAÇÃO</span>
            </div>
        </button>
        
        <!-- B - Background -->
        <button @click="activeTab = 'tab-b'"
                :class="activeTab === 'tab-b' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#007D44] text-white text-xs font-bold">B</span>
                <span>BACKGROUND</span>
            </div>
        </button>
        
        <!-- A - Avaliação -->
        <button @click="activeTab = 'tab-a'"
                :class="activeTab === 'tab-a' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#ff6b35] text-white text-xs font-bold">A</span>
                <span>AVALIAÇÃO</span>
            </div>
        </button>
        
        <!-- R - Recomendações -->
        <button @click="activeTab = 'tab-r'"
                :class="activeTab === 'tab-r' ? 'border-[#0071B9] text-[#0071B9] bg-blue-50' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300'"
                class="flex-shrink-0 px-4 py-2 text-sm font-medium border-b-2 whitespace-nowrap transition-colors">
            <div class="flex items-center space-x-2">
                <span class="inline-flex items-center justify-center h-6 w-6 rounded-full bg-[#28a745] text-white text-xs font-bold">R</span>
                <span>RECOMENDAÇÕES</span>
            </div>
        </button>
    </nav>
</div>