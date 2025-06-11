
<div class="border-b border-gray-200 flex-shrink-0">
    <nav class="flex px-4 sm:px-6 -mb-px overflow-x-auto">
        <button 
            @click="activeTab = 'tab-1'"
            :class="activeTab === 'tab-1' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-800'"
            class="py-3 sm:py-4 px-2 sm:px-4 mr-4 sm:mr-8 border-b-2 font-medium text-xs sm:text-sm whitespace-nowrap flex-shrink-0"
        >
            <span class="hidden sm:inline">SBAR Geral</span>
            <span class="sm:hidden">SBAR</span>
        </button>
        <button 
            @click="activeTab = 'tab-2'"
            :class="activeTab === 'tab-2' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-800'"
            class="py-3 sm:py-4 px-2 sm:px-4 mr-4 sm:mr-8 border-b-2 font-medium text-xs sm:text-sm whitespace-nowrap flex-shrink-0"
        >
            <span class="hidden sm:inline">CPOE</span>
            <span class="sm:hidden">CPOE</span>
        </button>
        <button 
            @click="activeTab = 'tab-3'"
            :class="activeTab === 'tab-3' ? 'border-blue-500 text-blue-600' : 'border-transparent text-gray-600 hover:border-gray-300 hover:text-gray-800'"
            class="py-3 sm:py-4 px-2 sm:px-4 border-b-2 font-medium text-xs sm:text-sm whitespace-nowrap flex-shrink-0"
        >
            <span class="hidden sm:inline">SBAR por Turno</span>
            <span class="sm:hidden">Turnos</span>
        </button>
    </nav>
</div>