@props([
    'loadingPatient' => false,
    'currentPatient' => null,
    'patientDetails' => null
])

<div x-show="activeTab === 'tab-a'" class="p-3 sm:p-6">
    @if($loadingPatient)
        <!-- ...existing loading state... -->
    @elseif($currentPatient && !$currentPatient['has_patient'])
        <!-- ...existing empty bed state... -->
    @elseif($patientDetails)
        <!-- Avaliação - Sistema de Turnos -->
        <div class="bg-white rounded-xl p-4 sm:p-6 shadow-sm border border-gray-100">
            <h4 class="text-lg sm:text-xl font-bold text-gray-800 border-b border-gray-200 pb-3 mb-6 flex items-center">
                <span class="inline-flex items-center justify-center h-7 w-7 sm:h-8 sm:w-8 rounded-full bg-[#ff6b35] text-white mr-3 text-sm sm:text-base font-bold">A</span>
                <div>
                    <span class="text-base sm:text-lg">AVALIAÇÃO</span>
                    <p class="text-xs text-gray-500 font-normal mt-1">Qual é a sua análise ou interpretação do quadro?</p>
                </div>
            </h4>
            
            <!-- Sistema de Turnos -->
            <div x-data="{
                currentShift: getCurrentShift(),
                selectedDate: new Date().toISOString().split('T')[0],
                viewShift: getCurrentShift()
            }">
                
                <!-- Controles de Data e Turno -->
                <div class="bg-gray-50 p-4 rounded-lg border mb-6">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between space-y-4 sm:space-y-0">
                        <!-- Seletor de Data -->
                        <div class="flex items-center space-x-3">
                            <label class="text-sm font-medium text-gray-700">Data:</label>
                            <input 
                                type="date" 
                                x-model="selectedDate"
                                class="px-3 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                            >
                        </div>
                        
                        <!-- Status do Turno Atual -->
                        <div class="text-center">
                            <p class="text-xs text-gray-600">Turno Atual:</p>
                            <span x-text="getShiftName(currentShift)" class="px-3 py-1 rounded-full text-sm font-medium bg-blue-100 text-blue-800"></span>
                            <p class="text-xs text-gray-500 mt-1" x-text="getShiftTime(currentShift)"></p>
                        </div>
                    </div>
                </div>
                
                <!-- Navegação de Turnos -->
                <div class="flex space-x-1 mb-6 bg-gray-100 p-1 rounded-lg">
                    <button 
                        @click="viewShift = 'manha'"
                        :class="viewShift === 'manha' ? 'bg-white shadow-sm text-blue-700' : 'text-gray-600 hover:text-gray-800'"
                        class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors"
                    >
                        <div class="flex items-center justify-center space-x-2">
                            <span class="w-3 h-3 bg-blue-400 rounded-full"></span>
                            <span>Manhã (06h-14h)</span>
                        </div>
                    </button>
                    <button 
                        @click="viewShift = 'tarde'"
                        :class="viewShift === 'tarde' ? 'bg-white shadow-sm text-orange-700' : 'text-gray-600 hover:text-gray-800'"
                        class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors"
                    >
                        <div class="flex items-center justify-center space-x-2">
                            <span class="w-3 h-3 bg-orange-400 rounded-full"></span>
                            <span>Tarde (14h-22h)</span>
                        </div>
                    </button>
                    <button 
                        @click="viewShift = 'noite'"
                        :class="viewShift === 'noite' ? 'bg-white shadow-sm text-purple-700' : 'text-gray-600 hover:text-gray-800'"
                        class="flex-1 px-4 py-2 text-sm font-medium rounded-md transition-colors"
                    >
                        <div class="flex items-center justify-center space-x-2">
                            <span class="w-3 h-3 bg-purple-400 rounded-full"></span>
                            <span>Noite (22h-06h)</span>
                        </div>
                    </button>
                </div>
                
                <!-- Conteúdo do Turno -->
                <div class="min-h-96">
                    <!-- Turno Manhã -->
                    <div x-show="viewShift === 'manha'" class="space-y-6">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-6">
                            <h5 class="text-lg font-semibold text-blue-800 mb-4 flex items-center">
                                <span class="w-4 h-4 bg-blue-500 rounded-full mr-2"></span>
                                Turno Manhã - {{ date('d/m/Y') }}
                            </h5>
                            
                            <!-- Chat/Anotações -->
                            <div class="bg-white rounded-lg p-4 mb-4 border">
                                <h6 class="text-sm font-medium text-gray-700 mb-3">Anotações de Enfermagem:</h6>
                                <div class="space-y-3 max-h-64 overflow-y-auto">
                                    <!-- Exemplo de anotações -->
                                    <div class="flex space-x-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-medium text-blue-700">EN</span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="bg-gray-50 rounded-lg p-3">
                                                <p class="text-sm text-gray-800">Paciente acordado, orientado. Sinais vitais estáveis. Aceitou dieta. Sem queixas de dor.</p>
                                                <p class="text-xs text-gray-500 mt-1">08:30 - Enfª Maria Silva</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="flex space-x-3">
                                        <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center flex-shrink-0">
                                            <span class="text-xs font-medium text-blue-700">TE</span>
                                        </div>
                                        <div class="flex-1">
                                            <div class="bg-gray-50 rounded-lg p-3">
                                                <p class="text-sm text-gray-800">Higiene íntima realizada. Curativo de CVC trocado conforme protocolo.</p>
                                                <p class="text-xs text-gray-500 mt-1">10:15 - Téc. João Santos</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <!-- Input para nova anotação (se for turno atual) -->
                                <template x-if="currentShift === 'manha' && selectedDate === new Date().toISOString().split('T')[0]">
                                    <div class="mt-4 pt-4 border-t">
                                        <textarea 
                                            placeholder="Digite sua anotação..."
                                            class="w-full p-3 border border-gray-300 rounded-lg text-sm focus:outline-none focus:ring-2 focus:ring-blue-500"
                                            rows="3"
                                        ></textarea>
                                        <div class="flex justify-between items-center mt-2">
                                            <div class="text-xs text-gray-500">
                                                <span x-text="new Date().toLocaleTimeString('pt-BR', {hour: '2-digit', minute: '2-digit'})"></span> - Usuário Atual
                                            </div>
                                            <button class="px-4 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700 transition-colors">
                                                Adicionar
                                            </button>
                                        </div>
                                    </div>
                                </template>
                            </div>
                            
                            <!-- Checklist/Marcações -->
                            <div class="bg-white rounded-lg p-4 border">
                                <h6 class="text-sm font-medium text-gray-700 mb-3">Checklist do Turno:</h6>
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" checked class="rounded text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700">Sinais vitais verificados</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" checked class="rounded text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700">Medicação administrada</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" class="rounded text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700">Higiene realizada</span>
                                    </label>
                                    <label class="flex items-center space-x-2">
                                        <input type="checkbox" checked class="rounded text-blue-600 focus:ring-blue-500">
                                        <span class="text-sm text-gray-700">Dieta aceita</span>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Turno Tarde -->
                    <div x-show="viewShift === 'tarde'" class="space-y-6">
                        <div class="bg-orange-50 border border-orange-200 rounded-lg p-6">
                            <h5 class="text-lg font-semibold text-orange-800 mb-4 flex items-center">
                                <span class="w-4 h-4 bg-orange-500 rounded-full mr-2"></span>
                                Turno Tarde - {{ date('d/m/Y') }}
                            </h5>
                            
                            <div class="bg-white rounded-lg p-4 border">
                                <p class="text-center text-gray-500 py-8">
                                    🚧 <strong>Em desenvolvimento</strong><br>
                                    Anotações e avaliações do turno da tarde
                                </p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Turno Noite -->
                    <div x-show="viewShift === 'noite'" class="space-y-6">
                        <div class="bg-purple-50 border border-purple-200 rounded-lg p-6">
                            <h5 class="text-lg font-semibold text-purple-800 mb-4 flex items-center">
                                <span class="w-4 h-4 bg-purple-500 rounded-full mr-2"></span>
                                Turno Noite - {{ date('d/m/Y') }}
                            </h5>
                            
                            <div class="bg-white rounded-lg p-4 border">
                                <p class="text-center text-gray-500 py-8">
                                    🚧 <strong>Em desenvolvimento</strong><br>
                                    Anotações e avaliações do turno da noite
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <script>
            function getCurrentShift() {
                const hour = new Date().getHours();
                if (hour >= 6 && hour < 14) return 'manha';
                if (hour >= 14 && hour < 22) return 'tarde';
                return 'noite';
            }
            
            function getShiftName(shift) {
                const names = {
                    'manha': 'Manhã',
                    'tarde': 'Tarde', 
                    'noite': 'Noite'
                };
                return names[shift] || 'Indefinido';
            }
            
            function getShiftTime(shift) {
                const times = {
                    'manha': '06:00 - 14:00',
                    'tarde': '14:00 - 22:00',
                    'noite': '22:00 - 06:00'
                };
                return times[shift] || '';
            }
        </script>
    @else
        <!-- ...existing error state... -->
    @endif
</div>
