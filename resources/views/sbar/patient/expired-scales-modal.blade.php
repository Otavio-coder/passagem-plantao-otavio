<div wire:key="expired-scales-modal-{{ $sectorKey }}"
    x-data="{
        open: false,
        patients: @js($expiredScalesPatients),
        init() {
            window.addEventListener('openSbarExpiredScalesModal', () => {
                this.open = true;
                document.body.style.overflow = 'hidden';
            });
            window.addEventListener('expired-scales-data-loaded', (e) => {
                this.patients = e.detail.patients ?? [];
            });
        },
        close() {
            this.open = false;
            document.body.style.overflow = '';
        }
    }"
    x-show="open"
    x-cloak
    @keydown.escape.window="close()"
    style="display:none">

    <div class="fixed inset-0 z-[9998]"
        x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
        x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0">

        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="close()"></div>

        <div class="absolute inset-0 flex items-center justify-center p-0 sm:p-4">
            <div class="relative bg-white flex flex-col overflow-hidden w-full h-full sm:w-[95vw] sm:h-[92vh] sm:rounded-2xl lg:w-[620px] lg:h-[90vh] shadow-2xl"
                @click.stop
                x-transition:enter="transition ease-out duration-300" x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                x-transition:leave="transition ease-in duration-200" x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                {{-- Header --}}
                <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-red-600 to-orange-500 flex-shrink-0">
                    <div class="flex items-center gap-2.5 min-w-0">
                        <x-heroicon-o-exclamation-triangle class="w-5 h-5 text-white flex-shrink-0" />
                        <div class="min-w-0">
                            <h2 class="text-base font-bold text-white leading-tight">Escalas Pendentes</h2>
                            <p class="text-white/70 text-xs leading-tight">{{ now()->format('H:i') }}</p>
                        </div>
                        <template x-if="patients.length > 0">
                            <span class="px-2.5 py-0.5 bg-white text-red-600 text-xs font-bold rounded-full flex-shrink-0"
                                x-text="patients.length + (patients.length === 1 ? ' paciente' : ' pacientes')"></span>
                        </template>
                    </div>
                    <button @click="close()" title="Fechar"
                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                        <x-heroicon-o-x-mark class="w-4 h-4" />
                    </button>
                </div>

                {{-- Content --}}
                <div class="flex-1 overflow-y-auto min-h-0 bg-gray-50" style="scrollbar-width: thin; scrollbar-color: #cbd5e1 #f1f5f9;">

                    {{-- Vazio --}}
                    <template x-if="patients.length === 0">
                        <div class="flex flex-col items-center justify-center h-full text-center px-6 gap-3">
                            <div class="w-14 h-14 bg-green-100 rounded-full flex items-center justify-center">
                                <x-heroicon-o-check-circle class="w-7 h-7 text-green-500" />
                            </div>
                            <div>
                                <p class="text-base font-semibold text-gray-700">Todas em dia!</p>
                                <p class="text-sm text-gray-400 mt-0.5">Nenhuma escala pendente no turno atual.</p>
                            </div>
                        </div>
                    </template>

                    {{-- Lista --}}
                    <template x-if="patients.length > 0">
                        <div class="divide-y divide-gray-100">
                            <template x-for="patient in patients" :key="patient.nr_atendimento">
                                <div class="px-4 py-3 border-l-4 bg-white hover:bg-gray-50/80 transition-colors"
                                    :class="{
                                        'border-l-red-500': patient.priority === 'critical',
                                        'border-l-orange-400': patient.priority === 'high',
                                        'border-l-amber-400': patient.priority === 'medium'
                                    }">

                                    {{-- Linha 1: leito + nome + badge --}}
                                    <div class="flex items-start gap-2.5">
                                        <span class="mt-0.5 w-9 h-9 bg-gradient-to-br from-[#004D9D] to-[#0071B9] text-white rounded-lg flex items-center justify-center font-bold text-xs flex-shrink-0 shadow-sm"
                                            x-text="patient.bed"></span>

                                        <div class="flex-1 min-w-0">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="font-semibold text-gray-900 text-sm leading-tight truncate" x-text="patient.name"></span>
                                                <span class="text-xs font-bold px-2 py-0.5 rounded-full flex-shrink-0"
                                                    :class="{
                                                        'bg-red-100 text-red-700': patient.priority === 'critical',
                                                        'bg-orange-100 text-orange-700': patient.priority === 'high',
                                                        'bg-amber-100 text-amber-700': patient.priority === 'medium'
                                                    }"
                                                    x-text="patient.total_expired + 'p'"></span>
                                            </div>

                                            {{-- Linha 2: atendimento + entrada --}}
                                            <div class="flex items-center gap-2 flex-wrap mt-0.5 text-xs text-gray-400">
                                                <template x-if="patient.nr_atendimento">
                                                    <span>Atend. <span class="text-gray-600 font-medium" x-text="patient.nr_atendimento"></span></span>
                                                </template>
                                                <template x-if="patient.dt_entrada_formatted">
                                                    <span class="text-gray-300">·</span>
                                                </template>
                                                <template x-if="patient.dt_entrada_formatted">
                                                    <span>
                                                        Entrada <span class="text-gray-600 font-medium" x-text="patient.dt_entrada_formatted"></span>
                                                        <template x-if="patient.internment_days !== null">
                                                            <span x-text="' (' + patient.internment_days + 'd)'"></span>
                                                        </template>
                                                    </span>
                                                </template>
                                            </div>

                                            {{-- Linha 3: badges de escalas --}}
                                            <div class="flex flex-wrap gap-1.5 mt-2">
                                                <template x-for="scale in patient.expired_scales" :key="scale.name">
                                                    <div class="flex items-center gap-1 bg-red-50 border border-red-100 rounded-md px-2 py-1 text-xs">
                                                        <span class="w-1.5 h-1.5 rounded-full bg-red-400 flex-shrink-0"></span>
                                                        <span class="font-semibold text-red-700" x-text="scale.name"></span>
                                                        <template x-if="scale.score !== null && scale.score !== ''">
                                                            <span>
                                                                <span class="text-gray-400 mx-0.5">·</span>
                                                                <span class="text-gray-600" x-text="scale.score"></span>
                                                            </span>
                                                        </template>
                                                        <span class="text-gray-400 mx-0.5">·</span>
                                                        <template x-if="scale.timestamp_label">
                                                            <span class="text-gray-400" x-text="scale.timestamp_label"></span>
                                                        </template>
                                                        <template x-if="!scale.timestamp_label">
                                                            <span class="text-red-500 font-medium italic">sem registro</span>
                                                        </template>
                                                    </div>
                                                </template>
                                            </div>
                                        </div>
                                    </div>

                                </div>
                            </template>
                        </div>
                    </template>
                </div>

                {{-- Footer --}}
                <div class="px-4 py-2.5 border-t border-gray-200 bg-gray-50 flex-shrink-0 flex items-center justify-between">
                    <span class="text-xs text-gray-400">Manhã 07–13h · Tarde 13–19h · Noite 19–07h</span>
                    <template x-if="patients.length > 0">
                        <span class="text-xs text-gray-500 font-medium"
                            x-text="patients.length + (patients.length === 1 ? ' paciente' : ' pacientes')"></span>
                    </template>
                </div>

            </div>
        </div>
    </div>
</div>
