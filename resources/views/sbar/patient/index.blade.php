{{-- resources/views/livewire/partials/patient-card.blade.php --}}

<div class="relative patient-card w-full">
    <div
        class="card-inner patient-card-fixed flex flex-col rounded-xl shadow-lg overflow-hidden h-[400px] lg:h-[420px] xl:h-[430px] max-h-[400px] lg:max-h-[420px] xl:max-h-[430px]
        {{ ($patient['has_patient'] ?? false) ? ($patient['border_class'] ?? '') . ' ' . ($patient['text_color_class'] ?? '') : '' }}"
        style="{{ ($patient['gradient_style'] ?? '') }}"
    >
        @if(!($patient['has_patient'] ?? false))

            {{-- Empty Bed Card --}}
            <div class="h-full w-full flex flex-col min-h-0">
                <div class="flex-1 flex items-center justify-center min-w-0">
                    <div class="w-full h-full flex flex-col bg-gradient-to-br from-gray-200 to-gray-300 p-4 rounded-xl overflow-hidden min-h-0">
                        <div class="flex justify-between items-center mb-3 flex-shrink-0 min-w-0">
                            <span class="bg-white/70 text-gray-700 text-sm font-bold px-3 py-1 rounded-full">
                                Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                            </span>
                        </div>
                        <div class="flex-grow flex items-center justify-center">
                            <div class="text-center">
                                <svg class="mx-auto h-12 w-12 text-gray-400 mb-2" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                                </svg>
                                <p class="text-gray-500 text-base font-medium">Leito Vago</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        @else
            {{-- Occupied Bed Card --}}
            <div class="flex flex-col h-full overflow-hidden">
                {{-- Header Section --}}
                <div class="flex-shrink-0 p-3 lg:p-2.5 flex flex-col gap-2 lg:gap-1.5">
                    {{-- Row 1: Bed + Alerts + MEWS --}}
                    <div class="flex justify-between items-center gap-2">
                        {{-- Bed badge com dot de passagem --}}
                        <div class="flex-shrink-0"
                             x-data="{
                                show: false,
                                tipStyle: '',
                                open(el) {
                                    if (!window.matchMedia('(pointer: fine)').matches) return;
                                    const r = el.getBoundingClientRect();
                                    this.tipStyle = 'left:' + (r.left + r.width/2) + 'px;top:' + (r.bottom + 4) + 'px;transform:translateX(-50%)';
                                    this.show = true;
                                }
                             }"
                             @mouseleave="show = false"
                        >
                            <span
                                @mouseenter="open($el)"
                                class="relative inline-flex items-center bg-white/80 text-gray-800 text-xs sm:text-sm font-bold px-2 py-1 rounded-full shadow-sm cursor-default"
                            >
                                Leito {{ $patient['cd_unidade_basica'] ?? 'N/A' }}
                                @if(!($patient['handover_done'] ?? false))
                                    <span class="absolute -top-0.5 -right-0.5 flex h-2.5 w-2.5">
                                        <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                                        <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-orange-500"></span>
                                    </span>
                                @else
                                    <span class="absolute -top-0.5 -right-0.5 inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                                @endif
                            </span>

                            <div
                                x-show="show"
                                x-cloak
                                x-transition:enter="transition ease-out duration-100"
                                x-transition:enter-start="opacity-0 scale-95"
                                x-transition:enter-end="opacity-100 scale-100"
                                :style="tipStyle"
                                class="fixed z-[9999] w-52 bg-gray-900 text-white text-xs rounded-lg shadow-xl px-3 py-2 pointer-events-none"
                                style="display:none"
                            >
                                @if(!($patient['handover_done'] ?? false))
                                    <p class="font-semibold text-orange-300 mb-1 flex items-center gap-1">
                                        <i class="fas fa-triangle-exclamation fa-xs"></i>
                                        Passagem pendente
                                    </p>
                                    <p class="text-gray-300 leading-snug">Nenhuma anotação no turno da {{ $patient['handover_shift_name'] ?? 'turno atual' }}.</p>
                                @else
                                    <p class="font-semibold text-green-300 mb-1 flex items-center gap-1">
                                        <i class="fas fa-check-circle fa-xs"></i>
                                        Passagem realizada
                                    </p>
                                    <p class="text-gray-300 leading-snug">
                                        {{ $patient['handover_msg_count'] ?? 0 }} {{ ($patient['handover_msg_count'] ?? 0) === 1 ? 'anotação' : 'anotações' }} no turno da {{ $patient['handover_shift_name'] ?? 'turno atual' }}. Última às {{ $patient['handover_last_time'] ?? '--:--' }}.
                                    </p>
                                @endif
                            </div>
                        </div>
                        {{-- Clinical Alerts (Alergia, Isolamento, Cirurgia, Alta) --}}
                        <div class="flex items-center justify-center gap-1.5 flex-1 min-w-0">

                            {{-- ALERGIA --}}
                            @if($patient['has_allergy'] ?? false)
                                <div x-data="{
                                        showTip: false,
                                        showModal: false,
                                        tipStyle: '',
                                        openTip(btn) {
                                            const r = btn.getBoundingClientRect();
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.tipStyle = 'left:' + (r.left + r.width / 2) + 'px;top:' + (r.bottom + 2) + 'px;transform:translateX(-50%)';
                                            } else {
                                                const top = Math.min(r.bottom + 8, window.innerHeight - 220);
                                                this.tipStyle = 'left:50%;top:' + top + 'px;transform:translateX(-50%);width:min(220px,calc(100vw - 32px))';
                                            }
                                            this.showTip = true;
                                        },
                                        closeTip() { this.showTip = false; },
                                        handleClick(btn) {
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.openModal();
                                            } else {
                                                this.showTip ? this.closeTip() : this.openTip(btn);
                                            }
                                        },
                                        openModal() { this.showModal = true; document.body.style.overflow = 'hidden'; },
                                        closeModal() { this.showModal = false; document.body.style.overflow = ''; }
                                    }">
                                    <button
                                        type="button"
                                        @mouseenter="openTip($el)"
                                        @mouseleave="closeTip()"
                                        @click="handleClick($el)"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-red-500 text-white shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                                        aria-label="Ver alergias"
                                    >
                                        <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                        </svg>
                                    </button>

                                    {{-- Desktop: Tooltip --}}
                                    <div
                                        x-show="showTip"
                                        x-cloak
                                        :style="tipStyle"
                                        @mouseenter="showTip = true"
                                        @mouseleave="closeTip()"
                                        @click.outside="closeTip()"
                                        class="fixed z-[9999] w-52 rounded-2xl shadow-xl p-3 bg-red-500 text-white text-xs"
                                        @click.stop
                                    >
                                        <div class="font-semibold text-xs mb-1 border-b border-white/20 pb-0.5">Alergias Registradas</div>
                                        @if(empty($patient['allergy_items'] ?? []))
                                            <div class="text-white/80">Nenhuma alergia registrada</div>
                                        @else
                                            @foreach(array_slice($patient['allergy_items'] ?? [], 0, 3) as $a)
                                                <div class="py-0.5">
                                                    @if(isset($a['med']))
                                                        <span class="font-medium">{{ $a['med'] }}</span>
                                                        <span class="text-white/70 ml-1">{{ $a['grav'] }}</span>
                                                    @else
                                                        <span>{{ $a['text'] }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                            @if(count($patient['allergy_items'] ?? []) > 3)
                                                <div class="text-white/70 text-[10px] mt-1">+{{ count($patient['allergy_items'] ?? []) - 3 }} mais · clique para ver</div>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- Allergy Modal --}}
                                    <div x-show="showModal"
                                         x-cloak
                                         @click.self="closeModal()"
                                         @keydown.escape.window="closeModal()"
                                         class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
                                         style="margin: 0 !important;"
                                    >
                                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal()"></div>
                                        <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] sm:w-[500px] flex flex-col"
                                             @click.stop
                                             x-show="showModal"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                                            {{-- Header --}}
                                            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-red-500 to-red-600 flex-shrink-0">
                                                <div class="flex items-center gap-2.5">
                                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5 text-white flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                                                    </svg>
                                                    <h3 class="text-base font-bold text-white">Alergias Registradas</h3>
                                                </div>
                                                <button @click="closeModal()" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            {{-- Content --}}
                                            <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                                                @if(empty($patient['allergy_items'] ?? []))
                                                    <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                                                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <p class="text-sm">Nenhuma alergia registrada</p>
                                                    </div>
                                                @else
                                                    <div class="space-y-2">
                                                        @foreach($patient['allergy_items'] ?? [] as $a)
                                                            <div class="p-3 rounded-lg bg-white border border-gray-200 shadow-sm">
                                                                @if(isset($a['med']))
                                                                    <div class="flex justify-between gap-2 items-start">
                                                                        <div class="font-medium text-gray-900 text-sm">{{ $a['med'] }}</div>
                                                                        <div class="{{ $a['severity_text_class'] ?? 'text-gray-500' }} text-xs px-2 py-0.5 rounded-full {{ $a['severity_bg_class'] ?? 'bg-gray-100' }} flex-shrink-0">{{ $a['grav'] }}</div>
                                                                    </div>
                                                                @else
                                                                    <div class="text-gray-800 text-sm">{{ $a['text'] }}</div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- ISOLAMENTO --}}
                            @if($patient['has_isolation'] ?? false)
                                <div x-data="{
                                        showTip: false,
                                        showModal: false,
                                        tipStyle: '',
                                        openTip(btn) {
                                            const r = btn.getBoundingClientRect();
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.tipStyle = 'left:' + (r.left + r.width / 2) + 'px;top:' + (r.bottom + 2) + 'px;transform:translateX(-50%)';
                                            } else {
                                                const top = Math.min(r.bottom + 8, window.innerHeight - 220);
                                                this.tipStyle = 'left:50%;top:' + top + 'px;transform:translateX(-50%);width:min(220px,calc(100vw - 32px))';
                                            }
                                            this.showTip = true;
                                        },
                                        closeTip() { this.showTip = false; },
                                        handleClick(btn) {
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.openModal();
                                            } else {
                                                this.showTip ? this.closeTip() : this.openTip(btn);
                                            }
                                        },
                                        openModal() { this.showModal = true; document.body.style.overflow = 'hidden'; },
                                        closeModal() { this.showModal = false; document.body.style.overflow = ''; }
                                    }">
                                    <button
                                        type="button"
                                        @mouseenter="openTip($el)"
                                        @mouseleave="closeTip()"
                                        @click="handleClick($el)"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-yellow-400 text-black shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                                        aria-label="Ver isolamento"
                                    >
                                        <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="w-5 h-5" alt="Isolamento" />
                                    </button>

                                    {{-- Desktop: Tooltip --}}
                                    <div
                                        x-show="showTip"
                                        x-cloak
                                        :style="tipStyle"
                                        @mouseenter="showTip = true"
                                        @mouseleave="closeTip()"
                                        @click.outside="closeTip()"
                                        class="fixed z-[9999] w-52 rounded-2xl shadow-xl p-3 bg-yellow-400 text-black text-xs"
                                        @click.stop
                                    >
                                        <div class="font-semibold text-xs mb-1 border-b border-black/10 pb-0.5">Precauções de Isolamento</div>
                                        @if(empty($patient['isolation_items'] ?? []))
                                            <div class="text-black/70">Motivo não especificado</div>
                                        @else
                                            @foreach(array_slice($patient['isolation_items'] ?? [], 0, 3) as $iso)
                                                <div class="py-0.5">
                                                    @if(isset($iso['label']))
                                                        <span class="font-medium">{{ $iso['label'] }}:</span>
                                                        <span class="text-black/80 ml-1">{{ $iso['value'] }}</span>
                                                    @else
                                                        <span>{{ $iso['text'] }}</span>
                                                    @endif
                                                </div>
                                            @endforeach
                                            @if(count($patient['isolation_items'] ?? []) > 3)
                                                <div class="text-black/60 text-[10px] mt-1">+{{ count($patient['isolation_items'] ?? []) - 3 }} mais · clique para ver</div>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- Isolation Modal --}}
                                    <div x-show="showModal"
                                         x-cloak
                                         @click.self="closeModal()"
                                         @keydown.escape.window="closeModal()"
                                         class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
                                         style="margin: 0 !important;"
                                    >
                                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal()"></div>
                                        <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] sm:w-[500px] flex flex-col"
                                             @click.stop
                                             x-show="showModal"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                                            {{-- Header --}}
                                            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-yellow-500 to-amber-500 flex-shrink-0">
                                                <div class="flex items-center gap-2.5">
                                                    <img src="{{ asset('images/icons/patient-card/patient-isolated.svg') }}" class="h-5 w-5 flex-shrink-0" alt="Isolamento" />
                                                    <h3 class="text-base font-bold text-white">Precauções de Isolamento</h3>
                                                </div>
                                                <button @click="closeModal()" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            {{-- Content --}}
                                            <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                                                @if(empty($patient['isolation_items'] ?? []))
                                                    <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                                                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <p class="text-sm">Motivo não especificado</p>
                                                    </div>
                                                @else
                                                    <div class="space-y-2">
                                                        @foreach($patient['isolation_items'] ?? [] as $iso)
                                                            <div class="p-3 rounded-lg bg-white border border-gray-200 shadow-sm">
                                                                @if(isset($iso['label']))
                                                                    <div class="text-sm">
                                                                        <span class="font-semibold text-gray-800">{{ $iso['label'] }}:</span>
                                                                        <span class="text-gray-600">{{ $iso['value'] }}</span>
                                                                    </div>
                                                                @else
                                                                    <div class="text-sm text-gray-800">{{ $iso['text'] }}</div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            {{-- CIRURGIA --}}
                            @if($patient['has_surgery'] ?? false)
                                <div x-data="{
                                        showTip: false,
                                        showModal: false,
                                        tipStyle: '',
                                        openTip(btn) {
                                            const r = btn.getBoundingClientRect();
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.tipStyle = 'left:' + (r.left + r.width / 2) + 'px;top:' + (r.bottom + 2) + 'px;transform:translateX(-50%)';
                                            } else {
                                                const top = Math.min(r.bottom + 8, window.innerHeight - 220);
                                                this.tipStyle = 'left:50%;top:' + top + 'px;transform:translateX(-50%);width:min(220px,calc(100vw - 32px))';
                                            }
                                            this.showTip = true;
                                        },
                                        closeTip() { this.showTip = false; },
                                        handleClick(btn) {
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.openModal();
                                            } else {
                                                this.showTip ? this.closeTip() : this.openTip(btn);
                                            }
                                        },
                                        openModal() { this.showModal = true; document.body.style.overflow = 'hidden'; },
                                        closeModal() { this.showModal = false; document.body.style.overflow = ''; }
                                    }">
                                    <button
                                        type="button"
                                        @mouseenter="openTip($el)"
                                        @mouseleave="closeTip()"
                                        @click="handleClick($el)"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full bg-[#7712C7] text-white shadow-md animate-pulse transition-transform duration-150 cursor-pointer hover:scale-110 hover:bg-[#7712C7]/70 focus:outline-none focus:ring-2 focus:ring-[#7712C7]/50 focus:ring-offset-2"
                                        aria-label="Ver cirurgia"
                                    >
                                        <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="w-6 h-6 filter brightness-0 invert" />
                                    </button>

                                    {{-- Desktop: Tooltip --}}
                                    <div
                                        x-show="showTip"
                                        x-cloak
                                        :style="tipStyle"
                                        @mouseenter="showTip = true"
                                        @mouseleave="closeTip()"
                                        @click.outside="closeTip()"
                                        class="fixed z-[9999] w-52 rounded-2xl shadow-xl p-3 bg-[#7712C7] text-white text-xs"
                                        @click.stop
                                    >
                                        <div class="font-semibold text-xs mb-1 border-b border-white/20 pb-0.5">Agenda de Cirurgia</div>
                                        @if(!empty($patient['procedimentos_cirurgicos'][0] ?? null))
                                            <div class="py-0.5">
                                                <span class="font-medium">{{ $patient['procedimentos_cirurgicos'][0]['data_agenda'] ?? 'N/A' }}</span>
                                                @if(!empty($patient['procedimentos_cirurgicos'][0]['hora_agenda']))
                                                    <span class="text-white/70 ml-1">às {{ $patient['procedimentos_cirurgicos'][0]['hora_agenda'] }}</span>
                                                @endif
                                            </div>
                                            <div class="py-0.5 text-white/90">{{ $patient['procedimentos_cirurgicos'][0]['display_description'] ?? 'Procedimento' }}</div>
                                            @if(!empty($patient['procedimentos_cirurgicos'][0]['carater_cirurgia']))
                                                <div class="text-white/80 text-[10px] mt-0.5">{{ $patient['procedimentos_cirurgicos'][0]['carater_cirurgia'] }}</div>
                                            @endif
                                            @if(!empty($patient['procedimentos_cirurgicos'][0]['status']))
                                                <div class="text-white/80 text-[10px] mt-0.5">{{ $patient['procedimentos_cirurgicos'][0]['status'] }}</div>
                                            @endif
                                            @if(!empty($patient['procedimentos_cirurgicos'][0]['setor_execucao']))
                                                <div class="text-white/80 text-[10px] mt-0.5">{{ $patient['procedimentos_cirurgicos'][0]['setor_execucao'] }}</div>
                                            @endif
                                            @if(!empty($patient['procedimentos_cirurgicos'][0]['observacoes']))
                                                <div class="text-white/80 text-[10px] mt-0.5">Obs: {{ $patient['procedimentos_cirurgicos'][0]['observacoes'] }}</div>
                                            @endif
                                            @if(count($patient['procedimentos_cirurgicos']) > 1)
                                                <div class="text-white/70 text-[10px] mt-1">+{{ count($patient['procedimentos_cirurgicos']) - 1 }} mais · clique para ver</div>
                                            @endif
                                        @else
                                            <div class="text-white/80">Ver detalhes</div>
                                        @endif
                                    </div>

                                    {{-- Mobile/Touch: Modal --}}
                                    <div x-show="showModal"
                                         x-cloak
                                         @click.self="closeModal()"
                                         @keydown.escape.window="closeModal()"
                                         class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
                                         style="margin: 0 !important;"
                                    >
                                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal()"></div>
                                        <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] sm:w-[500px] flex flex-col"
                                             @click.stop
                                             x-show="showModal"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                                            {{-- Header --}}
                                            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#7712C7] to-[#7712C7]/80 flex-shrink-0">
                                                <div class="flex items-center gap-2.5">
                                                    <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="h-5 w-5 flex-shrink-0 filter brightness-0 invert" />
                                                    <h3 class="text-base font-bold text-white">Agendas de Cirurgia Recente</h3>
                                                </div>
                                                <button @click="closeModal()" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            {{-- Content --}}
                                            <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                                                @if(!empty($patient['procedimentos_cirurgicos']) && is_array($patient['procedimentos_cirurgicos']))
                                                    <div class="space-y-2">
                                                        @foreach($patient['procedimentos_cirurgicos'] as $c)
                                                            <div class="p-3 rounded-lg bg-white border border-gray-200 shadow-sm">
                                                                <div class="text-sm font-semibold text-gray-900">
                                                                    {{ $c['data_agenda'] ?? 'N/A' }} @if(!empty($c['hora_agenda'])) às {{ $c['hora_agenda'] }}@endif
                                                                </div>
                                                                <div class="text-sm text-gray-700 mt-1">{{ $c['display_description'] ?? ($c['descricao_padronizada'] ?? $c['procedimento'] ?? $c['carater_cirurgia'] ?? 'Procedimento') }}</div>
                                                                @if(!empty($c['carater_cirurgia']))
                                                                    <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-[#7712C7] bg-[#7712C7]/10 border border-[#7712C7]/20 px-1.5 py-0.5 rounded mt-1">
                                                                        <img src="{{ asset('images/icons/patient-card/general-surgery.svg') }}" class="h-3 w-3 opacity-80" alt="" />
                                                                        <span>{{ $c['carater_cirurgia'] }}</span>
                                                                    </div>
                                                                @endif
                                                                @if(!empty($c['status']))
                                                                    <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-blue-700 bg-blue-50 border border-blue-200 px-1.5 py-0.5 rounded mt-1">
                                                                        <span>{{ $c['status'] }}</span>
                                                                    </div>
                                                                @endif
                                                                @if(!empty($c['setor_execucao']))
                                                                    <div class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded mt-1">
                                                                        <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                                                        <span>{{ $c['setor_execucao'] }}</span>
                                                                    </div>
                                                                @endif
                                                                @if(!empty($c['observacoes']))
                                                                    <div class="text-xs text-gray-500 italic mt-1">Obs: {{ $c['observacoes'] }}</div>
                                                                @endif
                                                            </div>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="flex flex-col items-center justify-center py-8 text-gray-500">
                                                        <svg class="w-12 h-12 text-gray-300 mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                        </svg>
                                                        <p class="text-sm">Verificar detalhes</p>
                                                    </div>
                                                @endif
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                            @if($patient['discharge_display']['show'] ?? false)
                                <div
                                    x-data="{
                                        showTip: false,
                                        showModal: false,
                                        tipStyle: '',
                                        openTip(btn) {
                                            const r = btn.getBoundingClientRect();
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.tipStyle = 'left:' + (r.left + r.width / 2) + 'px;top:' + (r.bottom + 2) + 'px;transform:translateX(-50%)';
                                            } else {
                                                const top = Math.min(r.bottom + 8, window.innerHeight - 220);
                                                this.tipStyle = 'left:50%;top:' + top + 'px;transform:translateX(-50%);width:min(220px,calc(100vw - 32px))';
                                            }
                                            this.showTip = true;
                                        },
                                        closeTip() { this.showTip = false; },
                                        handleClick(btn) {
                                            if (window.matchMedia('(pointer: fine)').matches) {
                                                this.openModal();
                                            } else {
                                                this.showTip ? this.closeTip() : this.openTip(btn);
                                            }
                                        },
                                        openModal() { this.showModal = true; document.body.style.overflow = 'hidden'; },
                                        closeModal() { this.showModal = false; document.body.style.overflow = ''; }
                                    }"
                                    class="relative"
                                >
                                    <button
                                        type="button"
                                        @mouseenter="openTip($el)"
                                        @mouseleave="closeTip()"
                                        @click="handleClick($el)"
                                        class="w-8 h-8 flex-shrink-0 flex items-center justify-center rounded-full {{ $patient['discharge_display']['bg'] ?? 'bg-gray-100' }} shadow-md transition-transform duration-150 cursor-pointer hover:scale-110 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2"
                                        aria-label="{{ $patient['discharge_display']['label'] ?? 'Alta' }}"
                                    >
                                        <img src="{{ asset('images/icons/patient-card/' . ($patient['discharge_display']['icon'] ?? 'alta.svg')) }}" class="w-5 h-5" alt="{{ $patient['discharge_display']['label'] ?? 'Alta' }}" />
                                    </button>

                                    {{-- Desktop: Tooltip (position:fixed para não ser cortado pelo overflow:hidden do card) --}}
                                    <div
                                        x-show="showTip"
                                        x-cloak
                                        :style="tipStyle"
                                        @mouseenter="showTip = true"
                                        @mouseleave="closeTip()"
                                        @click.outside="closeTip()"
                                        class="fixed z-[9999] w-48 rounded-2xl shadow-xl p-3 text-gray-800 text-xs bg-white border border-gray-200"
                                        @click.stop
                                    >
                                        <div class="font-semibold text-xs mb-1 border-b border-gray-200 pb-0.5">{{ $patient['discharge_display']['label'] ?? 'Alta' }}</div>
                                        @if(($patient['discharge_display']['type'] ?? '') === 'alta')
                                            <div><span class="text-gray-500">Data:</span> {{ $patient['discharge_info']['dt_alta_formatted'] ?? '-' }}</div>
                                            @if(!empty($patient['discharge_info']['ds_motivo_alta']))
                                                <div class="mt-1"><span class="text-gray-500">Motivo:</span> {{ $patient['discharge_info']['ds_motivo_alta'] }}</div>
                                            @endif
                                        @elseif(($patient['discharge_display']['type'] ?? '') === 'alta_medica')
                                            <div><span class="text-gray-500">Alta Médica:</span> {{ $patient['discharge_info']['dt_alta_medico_formatted'] ?? '-' }}</div>
                                            @if(!empty($patient['discharge_info']['dt_previsto_alta_formatted']))
                                                <div class="mt-1"><span class="text-gray-500">Prev. Alta:</span> {{ $patient['discharge_info']['dt_previsto_alta_formatted'] }}</div>
                                            @endif
                                        @endif
                                    </div>

                                    {{-- Mobile/Touch: Modal --}}
                                    <div
                                        x-show="showModal"
                                        x-cloak
                                        @click.self="closeModal()"
                                        @keydown.escape.window="closeModal()"
                                        class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
                                        style="margin: 0 !important;"
                                    >
                                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal()"></div>
                                        <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] sm:w-[400px] flex flex-col"
                                             @click.stop
                                             x-show="showModal"
                                             x-transition:enter="transition ease-out duration-300"
                                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave="transition ease-in duration-200"
                                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                                            {{-- Header --}}
                                            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-gray-600 to-gray-700 flex-shrink-0">
                                                <div class="flex items-center gap-2.5">
                                                    <span class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                                                        <img src="{{ asset('images/icons/patient-card/' . ($patient['discharge_display']['icon'] ?? 'alta.svg')) }}" class="w-4 h-4" alt="{{ $patient['discharge_display']['label'] ?? 'Alta' }}" />
                                                    </span>
                                                    <h3 class="text-base font-bold text-white">{{ $patient['discharge_display']['label'] ?? 'Alta' }}</h3>
                                                </div>
                                                <button @click="closeModal()" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                    </svg>
                                                </button>
                                            </div>
                                            {{-- Content --}}
                                            <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                                                <div class="space-y-2">
                                                    @if(($patient['discharge_display']['type'] ?? '') === 'alta')
                                                        <div class="flex justify-between items-center bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                                                            <span class="text-gray-500 text-sm">Data da Alta</span>
                                                            <span class="font-semibold text-gray-900">{{ $patient['discharge_info']['dt_alta_formatted'] ?? '-' }}</span>
                                                        </div>
                                                        @if(!empty($patient['discharge_info']['ds_motivo_alta']))
                                                            <div class="bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                                                                <span class="text-gray-500 text-sm block mb-1">Motivo</span>
                                                                <span class="text-gray-800">{{ $patient['discharge_info']['ds_motivo_alta'] }}</span>
                                                            </div>
                                                        @endif
                                                    @elseif(($patient['discharge_display']['type'] ?? '') === 'alta_medica')
                                                        <div class="flex justify-between items-center bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                                                            <span class="text-gray-500 text-sm">Alta Médica</span>
                                                            <span class="font-semibold text-gray-900">{{ $patient['discharge_info']['dt_alta_medico_formatted'] ?? '-' }}</span>
                                                        </div>
                                                        @if(!empty($patient['discharge_info']['dt_previsto_alta_formatted']))
                                                            <div class="flex justify-between items-center bg-white rounded-lg px-4 py-3 border border-gray-200 shadow-sm">
                                                                <span class="text-gray-500 text-sm">Previsão de Alta</span>
                                                                <span class="font-semibold text-gray-900">{{ $patient['discharge_info']['dt_previsto_alta_formatted'] }}</span>
                                                            </div>
                                                        @endif
                                                    @endif
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif

                        </div>
                        {{-- MEWS/PEWS Badge --}}
                        <div class="flex-shrink-0">
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs sm:text-sm font-bold shadow-sm whitespace-nowrap relative border
                                {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_styling.bg', 'bg-white/90') }}
                                {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_styling.text', 'text-gray-700') }}
                                {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_styling.border', 'border-gray-300') }}
                                {{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_needs_assessment', false) ? 'border-b-2 border-b-red-500' : '' }}">
                                <strong>{{ $patient['ews_display']['label'] ?? 'MEWS' }}:</strong>
                                <span class="ml-1">{{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_score', '-') }}</span>
                                @if(data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_shift'))
                                    <span class="ml-0.5 text-[10px] font-normal hidden">({{ data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_shift') }})</span>
                                @endif
                                @if(data_get($patient, ($patient['ews_display']['prefix'] ?? 'mews') . '_increased', false) && !($patient['is_new_patient'] ?? false))
                                    <span class="absolute -top-1 -right-1 w-2 h-2 bg-red-500 rounded-full animate-pulse"></span>
                                @endif
                            </span>
                        </div>
                    </div>
                    {{-- Row 2: Patient Name + Gender + Age --}}
                    <div class="bg-white/70 rounded-lg px-2 py-1.5 shadow-sm">
                        <div class="flex items-center gap-2">
                            @if(($patient['sexo'] ?? '') === 'F')
                                <x-iconoir-female class="text-pink-600 h-4 w-4 flex-shrink-0" />
                            @elseif(($patient['sexo'] ?? '') === 'M')
                                <x-iconoir-male class="text-blue-600 h-4 w-4 flex-shrink-0" />
                            @endif
                            <p class="text-gray-900 text-sm font-bold truncate flex-1 min-w-0">{{ $patient['nm_pessoa_fisica'] ?? 'N/A' }}</p>
                            <span class="text-gray-700 text-xs sm:text-sm font-semibold flex-shrink-0">{{ $patient['age'] ?? '?' }}a</span>
                            <span class="text-gray-700 text-xs font-semibold flex-shrink-0">({{ $patient['birth_date'] ?? '?' }})</span>
                        </div>
                    </div>
                    {{-- Row 3: Administrative Data grid --}}
                    @php
                        $prevAltaRaw = $patient['discharge_info']['dt_previsto_alta_formatted'] ?? null;
                        $hasPrevAlta = in_array(($patient['discharge_display']['type'] ?? ''), ['previsao_alta', 'alta_medica']) && !empty($prevAltaRaw);
                        // formato "09/04/2026 14:30" → "09/04 14:30"
                        $prevAlta = $prevAltaRaw ? (substr($prevAltaRaw, 0, 5) . ' ' . substr($prevAltaRaw, -5)) : null;
                        $avalEnf = $patient['avaliacao_enf'] ?? null;
                        $avalEnfOk = $avalEnf && $avalEnf !== 'Não realizada';
                        $avalEnfDisplay = $avalEnfOk ? Str::before($avalEnf, ' ') : null;
                        $peData = $patient['pe_data'] ?? null;
                        $peOk = $peData && $peData !== 'Não realizado';
                        $hemocDate = $patient['ultima_hemocultura'] ?? null;
                        $hemocPend = $patient['hemocultura_pendente'] ?? false;
                        // Dr(a): primeiro + último nome
                        $medicoRaw = $patient['medico_responsavel'] ?? null;
                        $medicoParts = $medicoRaw ? array_values(array_filter(explode(' ', $medicoRaw))) : [];
                        $medicoAbrev = count($medicoParts) >= 2
                            ? $medicoParts[0] . ' ' . $medicoParts[count($medicoParts) - 1]
                            : ($medicoRaw ?? null);
                    @endphp
                    <div class="bg-white/70 rounded-lg px-2 py-0.5 shadow-sm">
                        <div class="grid grid-cols-3 gap-x-2 gap-y-0 text-[9px] leading-snug">
                            {{-- Row 1: Nº | Alta | Int --}}
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="text-gray-500 shrink-0">Nº:</span>
                                <span class="text-gray-900 font-medium overflow-hidden">{{ $patient['nr_atendimento'] ?? '—' }}</span>
                            </div>
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                @if($hasPrevAlta)
                                    <span class="text-orange-600 shrink-0">Alta:</span>
                                    <span class="text-orange-700 font-semibold overflow-hidden">{{ $prevAlta }}</span>
                                @else
                                    <span class="text-gray-500 shrink-0">Alta:</span>
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="text-gray-500 shrink-0">Int:</span>
                                @if($patient['is_new_patient'] ?? false)
                                    <span class="text-green-700 font-semibold">Hoje</span>
                                @elseif(isset($patient['internment_days']) && $patient['internment_days'] !== null)
                                    <span class="text-gray-900 font-medium">{{ ceil($patient['internment_days']) }}d</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                            {{-- Row 2: Dr | Enf | Educ --}}
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="text-gray-500 shrink-0">Dr:</span>
                                @if($medicoAbrev)
                                    <span class="text-gray-900 font-medium overflow-hidden">{{ $medicoAbrev }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="text-gray-500 shrink-0">Enf:</span>
                                @if($avalEnfOk)
                                    <span class="text-gray-900 font-medium">{{ $avalEnfDisplay }}</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </div>
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="text-gray-500 shrink-0">Educ:</span>
                                @if($peOk)
                                    <span class="text-gray-900 font-medium">{{ $peData }}</span>
                                @else
                                    <span class="text-gray-400">N/A</span>
                                @endif
                            </div>
                            {{-- Row 3: Hemoc (2 cols) | Conv --}}
                            <div class="col-span-2 flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="{{ $hemocPend ? 'text-purple-600 font-semibold shrink-0' : 'text-gray-500 shrink-0' }}">Hemoc:</span>
                                @if($hemocPend)
                                    <span class="text-purple-700 font-semibold shrink-0">Pend.</span>
                                    @if($hemocDate)<span class="text-purple-500 font-normal overflow-hidden"> últ.{{ $hemocDate }}</span>@endif
                                @elseif($hemocDate)
                                    <span class="text-gray-900 font-medium overflow-hidden">{{ $hemocDate }}</span>
                                @else
                                    <span class="text-gray-400">—</span>
                                @endif
                            </div>
                            <div class="flex gap-0.5 overflow-hidden whitespace-nowrap">
                                <span class="text-gray-500 shrink-0">Conv:</span>
                                <span class="text-gray-900 font-medium overflow-hidden">{{ $patient['convenio_short'] ?? '—' }}</span>
                            </div>
                        </div>
                    </div>
                    {{-- Row 4: Risk Scales (clickable → opens lightweight scales modal) --}}
                    <div x-data="{ showScalesModal: false }">
                    <div
                        class="bg-white/70 rounded-lg px-2 py-1 shadow-sm cursor-pointer hover:bg-white/90 transition-colors"
                        title="Ver escalas de avaliação"
                        @click="showScalesModal = true; document.body.style.overflow = 'hidden'"
                    >
                        <div class="flex flex-wrap gap-1 justify-center items-center min-h-[18px]">
                            @if($patient['has_patient'] ?? false)
                                {{-- Braden --}}
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
                                    {{ $patient['braden_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['braden_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['braden_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['braden_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>Braden:</strong>
                                    <span class="ml-1">{{ $patient['braden_score'] ?? '-' }}</span>
                                    @if($patient['braden_shift'] ?? null)
                                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['braden_shift'] }})</span>
                                    @endif
                                    @if(($patient['braden_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                {{-- Morse --}}
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
                                    {{ $patient['morse_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['morse_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['morse_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['morse_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>Morse:</strong>
                                    <span class="ml-1">{{ $patient['morse_score'] ?? '-' }}</span>
                                    @if($patient['morse_shift'] ?? null)
                                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['morse_shift'] }})</span>
                                    @endif
                                    @if(($patient['morse_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                {{-- Pain --}}
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
                                    {{ $patient['pain_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['pain_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['pain_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['pain_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>Dor:</strong>
                                    <span class="ml-1">{{ $patient['pain_score'] ?? '-' }}</span>
                                    @if($patient['pain_shift'] ?? null)
                                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['pain_shift'] }})</span>
                                    @endif
                                    @if(($patient['pain_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                                {{-- VTE --}}
                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] border whitespace-nowrap relative
                                    {{ $patient['vte_styling']['bg'] ?? 'bg-gray-50' }}
                                    {{ $patient['vte_styling']['text'] ?? 'text-gray-800' }}
                                    {{ $patient['vte_styling']['border'] ?? 'border-gray-300' }}
                                    {{ ($patient['vte_needs_assessment'] ?? false) ? 'border-b-2 border-b-red-500' : '' }}">
                                    <strong>TEV:</strong>
                                    <span class="ml-1">{{ $patient['vte_score'] ?? '-' }}</span>
                                    @if($patient['vte_shift'] ?? null)
                                        <span class="ml-0.5 text-[10px] font-normal hidden">({{ $patient['vte_shift'] }})</span>
                                    @endif
                                    @if(($patient['vte_increased'] ?? false))
                                        <span class="absolute -top-1 -right-1 w-1.5 h-1.5 bg-red-500 rounded-full animate-pulse"></span>
                                    @endif
                                </span>
                            @else
                                <span class="text-xs text-gray-400 italic">Escalas pendentes de avaliação</span>
                            @endif
                        </div>
                    </div>

                    {{-- Modal de Escalas (Alpine inline, sem Livewire) --}}
                    <div x-show="showScalesModal"
                         x-cloak
                         class="fixed inset-0 z-[9999] flex items-center justify-center p-0 sm:p-4"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @keydown.escape.window="showScalesModal = false; document.body.style.overflow = ''"
                    >
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                             @click="showScalesModal = false; document.body.style.overflow = ''"></div>
                        <div class="relative w-full h-full sm:w-[680px] sm:h-auto sm:max-h-[90vh] bg-white rounded-none sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
                             @click.stop
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                            {{-- Header --}}
                            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-purple-700 to-purple-500 flex-shrink-0">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                                    </svg>
                                    <div class="min-w-0">
                                        <h3 class="text-base font-bold text-white leading-tight">Escalas de Avaliação</h3>
                                        <p class="text-white/70 text-xs leading-tight truncate">{{ $patient['nm_pessoa_fisica'] ?? '' }}</p>
                                    </div>
                                </div>
                                <button @click="showScalesModal = false; document.body.style.overflow = ''"
                                        title="Fechar"
                                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>
                            {{-- Conteúdo --}}
                            <div class="flex-1 overflow-y-auto p-4">
                                <x-ui.scales-display :data="$patient" />
                            </div>
                        </div>
                    </div>
                    </div>{{-- fecha x-data="{ showScalesModal: false }" --}}

                    {{-- Row 5: Equipe Multi (Clickable) --}}
                    @if(!empty($patient['multidisciplinary'] ?? []) || !empty($patient['multidisciplinary_other'] ?? null))
                        {{-- x-data wraps BOTH the trigger AND the modal --}}
                        <div x-data="{ showMdModal: false }">
                            <div
                                class="bg-white/70 rounded-lg px-2 py-1 shadow-sm {{ !empty($patient['multidisciplinary_requests'] ?? []) ? 'cursor-pointer hover:bg-blue-50 transition-colors' : '' }}"
                                @if(!empty($patient['multidisciplinary_requests'] ?? [])) @click="showMdModal = true; document.body.style.overflow = 'hidden'" @endif
                                title="{{ !empty($patient['multidisciplinary_requests'] ?? []) ? 'Clique para ver detalhes das solicitações' : '' }}"
                            >
                                <div class="flex flex-wrap justify-center text-[10px] text-gray-700 gap-x-2 gap-y-0.5 items-center">
                                    @if(($patient['multidisciplinary']['fisioterapia'] ?? false))
                                        <span class="flex items-center gap-0.5 text-green-700 font-bold">
                                            <img src="{{ asset('images/icons/patient-card/fisioterapia.svg') }}" class="w-3.5 h-3.5" alt="Fisio" />
                                            Fisio
                                        </span>
                                    @else
                                        <span class="text-gray-400">Fisio(–)</span>
                                    @endif
                                    @if(($patient['multidisciplinary']['psicologia'] ?? false))
                                        <span class="flex items-center gap-0.5 text-green-700 font-bold">
                                            <img src="{{ asset('images/icons/patient-card/psicologia.svg') }}" class="w-3.5 h-3.5" alt="Psico" />
                                            Psico
                                        </span>
                                    @else
                                        <span class="text-gray-400">Psico(–)</span>
                                    @endif
                                    @if(($patient['multidisciplinary']['nutricao'] ?? false))
                                        <span class="flex items-center gap-0.5 text-green-700 font-bold">
                                            <img src="{{ asset('images/icons/patient-card/nutricao.svg') }}" class="w-3.5 h-3.5" alt="Nutri" />
                                            Nutri
                                        </span>
                                    @else
                                        <span class="text-gray-400">Nutri(–)</span>
                                    @endif
                                    @if(($patient['multidisciplinary']['fonoaudiologia'] ?? false))
                                        <span class="flex items-center gap-0.5 text-green-700 font-bold">
                                            <img src="{{ asset('images/icons/patient-card/fonoaudiologia.svg') }}" class="w-3.5 h-3.5" alt="Fono" />
                                            Fono
                                        </span>
                                    @else
                                        <span class="text-gray-400">Fono(–)</span>
                                    @endif
                                    @if(($patient['multidisciplinary']['servico_social'] ?? false))
                                        <span class="flex items-center gap-0.5 text-green-700 font-bold">
                                            <img src="{{ asset('images/icons/patient-card/servico-social.svg') }}" class="w-3.5 h-3.5" alt="SS" />
                                            SS
                                        </span>
                                    @else
                                        <span class="text-gray-400">SS(–)</span>
                                    @endif
                                    @if(($patient['multidisciplinary']['acessos_vasculares'] ?? false))
                                        <span class="flex items-center gap-0.5 text-green-700 font-bold">
                                            <img src="{{ asset('images/icons/patient-card/catheter-svgrepo-com.svg') }}" class="w-3.5 h-3.5" alt="Time" />
                                            Time
                                        </span>
                                    @else
                                        <span class="text-gray-400">Time(–)</span>
                                    @endif
                                </div>
                                @if(!empty($patient['multidisciplinary_other'] ?? null))
                                    <div class="text-[10px] text-gray-600 text-center mt-0.5">
                                        Outro: <span class="text-gray-800 font-medium">{{ $patient['multidisciplinary_other'] }}</span>
                                    </div>
                                @endif
                                @if(!empty($patient['multidisciplinary_requests'] ?? []))
                                    <div class="text-[9px] text-center text-blue-600 mt-0.5">
                                        {{ count($patient['multidisciplinary_requests']) }} solicitação(ões) · Clique para ver
                                    </div>
                                @endif
                            </div>

                            {{-- Modal de Equipes Multi (DENTRO do mesmo x-data) --}}
                            @if(!empty($patient['multidisciplinary_requests'] ?? []))
                                <div x-show="showMdModal"
                                     x-cloak
                                     @click.self="showMdModal = false; document.body.style.overflow = ''"
                                     @keydown.escape.window="showMdModal = false; document.body.style.overflow = ''"
                                     class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
                                     style="margin: 0 !important;"
                                >
                                    <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="showMdModal = false; document.body.style.overflow = ''"></div>
                                    <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] sm:w-[650px] flex flex-col"
                                         @click.stop
                                         x-show="showMdModal"
                                         x-transition:enter="transition ease-out duration-300"
                                         x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                                         x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave="transition ease-in duration-200"
                                         x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                                         x-transition:leave-end="opacity-0 scale-95 translate-y-4">
                                        {{-- Header --}}
                                        <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                                            <div class="flex items-center gap-2.5 min-w-0">
                                                <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"/>
                                                </svg>
                                                <h3 class="text-base font-bold text-white">Solicitações de Parecer / Consultorias</h3>
                                            </div>
                                            <button @click="showMdModal = false; document.body.style.overflow = ''" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                                </svg>
                                            </button>
                                        </div>

                                        {{-- Content --}}
                                        <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                                            <div class="space-y-3">
                                                @foreach(($patient['multidisciplinary_requests'] ?? []) as $request)
                                                    <div class="border rounded-lg p-4 {{ $request['card_class'] ?? 'bg-amber-50 border-amber-200' }}">
                                                        {{-- Header com ícone --}}
                                                        <div class="flex justify-between items-start mb-3">
                                                            <div class="flex items-center gap-2 flex-1 min-w-0">
                                                                @if(!empty($request['team_icon']))
                                                                    <img src="{{ asset('images/icons/patient-card/' . $request['team_icon']) }}" class="w-5 h-5 flex-shrink-0" alt="" />
                                                                @endif
                                                                <span class="text-sm font-semibold text-gray-800">{{ $request['ds_equipe_destino'] ?? 'Equipe não identificada' }}</span>
                                                            </div>
                                                            <span class="text-xs px-2.5 py-1 rounded-full flex-shrink-0 ml-2 {{ $request['status_badge_class'] ?? 'bg-amber-500 text-white' }}">
                                                                {{ $request['ds_status'] ?? $request['ie_status'] ?? '-' }}
                                                            </span>
                                                        </div>

                                                        {{-- Requisitante e datas --}}
                                                        <div class="text-xs text-gray-600 mb-3 space-y-1">
                                                            <div><strong>Profissional requisitante:</strong> {{ $request['nm_requisitante'] ?? 'Não informado' }}</div>
                                                            <div><strong>Data do registro:</strong> {{ $request['dt_registro_formatted'] ?? 'N/A' }}</div>
                                                            @if(!empty($request['dt_liberacao_formatted']))
                                                                <div><strong>Data liberação:</strong> {{ $request['dt_liberacao_formatted'] }}</div>
                                                            @endif
                                                        </div>

                                                        {{-- Pedido / Motivo da Consulta --}}
                                                        @if(!empty($request['ds_motivo_consulta']))
                                                            <div class="bg-white rounded-lg p-3 mb-3 border border-gray-200 shadow-sm">
                                                                <div class="text-[10px] font-semibold text-gray-500 uppercase mb-1">Pedido / Motivo da Consulta:</div>
                                                                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $request['ds_motivo_consulta'] }}</div>
                                                            </div>
                                                        @endif

                                                        {{-- Resposta / Parecer --}}
                                                        @if(!empty($request['ds_parecer']))
                                                            <div class="bg-green-50 rounded-lg p-3 border border-green-200">
                                                                <div class="text-[10px] font-semibold text-green-700 uppercase mb-1">Resposta / Parecer:</div>
                                                                <div class="text-sm text-gray-800 whitespace-pre-line">{{ $request['ds_parecer'] }}</div>
                                                                @if(!empty($request['nm_responsavel_resposta']))
                                                                    <div class="text-xs text-gray-600 mt-2 pt-2 border-t border-green-200">
                                                                        <strong>Respondido por:</strong> {{ $request['nm_responsavel_resposta'] }}
                                                                        @if(!empty($request['dt_resposta_formatted']))
                                                                            em {{ $request['dt_resposta_formatted'] }}
                                                                        @endif
                                                                    </div>
                                                                @endif
                                                            </div>
                                                        @endif
                                                    </div>
                                                @endforeach
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endif
                        </div>
                    @endif
                </div>

                {{-- Pending Events Section --}}
                <div class="flex-1 min-h-0 px-2 sm:px-2.5 lg:px-3 overflow-hidden flex flex-col"
                     x-data="{ showPendingModal: false, pendingShowAll: false, cardSlide: 0 }"
                     @pending-filter.window="pendingShowAll = $event.detail.v">

                    @if(!empty($patient['first_pending_event']) || !empty($patient['latest_evaluation']['content'] ?? null))
                        {{-- ── CARD: somente o evento mais próximo/urgente ── --}}
                        <div class="rounded-lg p-2 border {{ !empty($patient['first_pending_event']) ? '' : 'bg-blue-50/60 border-blue-200' }}"
                             @if(!empty($patient['first_pending_event']) && !empty($patient['first_pending_style']['card_style'])) style="{{ $patient['first_pending_style']['card_style'] }}" @endif>
                            {{-- Cabeçalho da seção --}}
                            <div class="flex items-center justify-between mb-1.5">
                                <span class="text-[12px] font-bold tracking-wide text-[#004D9D]">
                                    @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                                        Pendências / Avaliação
                                    @elseif(!empty($patient['first_pending_event']))
                                        Pendências
                                    @else
                                        Avaliação
                                    @endif
                                </span>
                                <div class="flex items-center gap-1.5">
                                    @if(!empty($patient['pending_events'] ?? []))
                                        <button
                                            @click="showPendingModal = true; document.body.style.overflow = 'hidden'; $dispatch('pending-filter', { v: false })"
                                            class="inline-flex items-center rounded-md border border-[#004D9D]/25 bg-[#004D9D]/10 px-2 py-0.5
                                                   text-[10px] font-medium text-[#004D9D] hover:bg-[#004D9D]/20 transition-colors cursor-pointer"
                                            title="Ver todas as pendências"
                                        >
                                            Ver todas
                                        </button>
                                    @endif

                                    @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                                        <button class="w-5 h-5 flex items-center justify-center rounded-full bg-[#004D9D]/10 text-[#004D9D] hover:bg-[#004D9D]/20 transition-colors"
                                                title="Anterior"
                                                @click="cardSlide = cardSlide === 0 ? 1 : 0">
                                            <x-iconoir-nav-arrow-left class="h-3.5 w-3.5" />
                                        </button>
                                        <button class="w-5 h-5 flex items-center justify-center rounded-full bg-[#004D9D]/10 text-[#004D9D] hover:bg-[#004D9D]/20 transition-colors"
                                                title="Próximo"
                                                @click="cardSlide = cardSlide === 0 ? 1 : 0">
                                            <x-iconoir-nav-arrow-right class="h-3.5 w-3.5" />
                                        </button>
                                    @endif
                                </div>
                            </div>

                            @if(!empty($patient['first_pending_event']))
                            <div x-show="{{ !empty($patient['latest_evaluation']['content'] ?? null) ? 'cardSlide === 0' : 'true' }}" class="flex items-start gap-2" x-transition>
                                <img src="{{ asset('images/icons/patient-card/' . ($patient['first_pending_style']['icon'] ?? 'alert-circle.svg')) }}"
                                     class="w-4 h-4 flex-shrink-0 mt-0.5 opacity-90" alt="">
                                <div class="flex-1 min-w-0">
                                    <div class="text-[11px] {{ $patient['first_pending_style']['description_class'] ?? 'text-[#062047] font-semibold' }} leading-tight line-clamp-2">
                                        {{ $patient['first_pending_event']['descricao'] ?? 'Sem descrição' }}
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                        @if(!empty($patient['first_pending_event']['dt_evento_formatted']))
                                            <span class="text-[9px] {{ $patient['first_pending_style']['time_class'] ?? 'text-[#004D9D] font-medium' }}">
                                                {{ $patient['first_pending_event']['dt_evento_formatted'] }}
                                            </span>
                                        @endif
                                        @if(!empty($patient['first_pending_event']['tempo_pendente']))
                                            <span class="text-[9px] text-gray-500">
                                                · {{ $patient['first_pending_event']['tempo_pendente'] }}
                                            </span>
                                        @endif
                                    </div>
                                    @if(!empty($patient['first_pending_event']['motivo_pendente']))
                                        <div class="flex items-center gap-1 mt-0.5 text-[9px] text-gray-400 leading-tight">
                                            <svg class="w-2.5 h-2.5 flex-shrink-0 opacity-60" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                            </svg>
                                            <span>{{ $patient['first_pending_event']['motivo_pendente'] }}</span>
                                        </div>
                                    @endif
                                </div>
                                @if($patient['first_pending_style']['show_pulse'] ?? false)
                                    <span class="w-2 h-2 rounded-full {{ $patient['first_pending_style']['pulse_color'] ?? 'bg-gray-400' }} animate-pulse flex-shrink-0 mt-1"></span>
                                @endif
                            </div>
                            @endif

                            @if(!empty($patient['latest_evaluation']['content'] ?? null))
                            <div x-show="{{ !empty($patient['first_pending_event']) ? 'cardSlide === 1' : 'true' }}" class="flex items-start gap-2" x-transition>
                                <x-ui.user-avatar 
                                    :photo="$patient['latest_evaluation']['photo'] ?? null" 
                                    :name="$patient['latest_evaluation']['user_name'] ?? 'U'" 
                                    class="w-5 h-5 flex-shrink-0 mt-0.5"
                                />
                                <div class="flex-1 min-w-0">
                                    <div class="text-[11px] text-blue-800 font-medium leading-tight line-clamp-2">
                                        {{ $patient['latest_evaluation']['content'] ?? '-' }}
                                    </div>
                                    <div class="flex items-center gap-1.5 mt-0.5 flex-wrap">
                                        @if(!empty($patient['latest_evaluation']['created_at_formatted']))
                                            <span class="text-[9px] text-blue-700 font-medium">
                                                {{ $patient['latest_evaluation']['created_at_formatted'] }}
                                            </span>
                                        @endif
                                        @if(!empty($patient['latest_evaluation']['user_name']))
                                            <span class="text-[9px] text-blue-700">
                                                · {{ $patient['latest_evaluation']['user_name'] }}
                                            </span>
                                        @endif
                                    </div>
                                </div>
                            </div>
                            @endif

                            @if(!empty($patient['first_pending_event']) && !empty($patient['latest_evaluation']['content'] ?? null))
                                <div class="mt-1.5 flex justify-center gap-1">
                                    <span class="h-1.5 w-1.5 rounded-full" :class="cardSlide === 0 ? 'bg-[#004D9D]' : 'bg-gray-300'"></span>
                                    <span class="h-1.5 w-1.5 rounded-full" :class="cardSlide === 1 ? 'bg-[#004D9D]' : 'bg-gray-300'"></span>
                                </div>
                            @endif
                        </div>

                    @else
                        <div class="flex items-center justify-center h-full w-full">
                            <div class="text-center py-2">
                                <x-iconoir-walking class="text-gray-400 h-5 w-5 mx-auto" />
                                <p class="text-xs text-gray-500 font-medium">Sem pendências</p>
                                <p class="text-[9px] text-gray-400 mt-0.5">Nenhum evento pendente registrado para este paciente.</p>
                            </div>
                        </div>
                    @endif

                    {{-- ── MODAL: todas as pendências ── --}}
                    <div x-show="showPendingModal"
                         x-cloak
                         class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
                         x-transition:enter="transition ease-out duration-200"
                         x-transition:enter-start="opacity-0"
                         x-transition:enter-end="opacity-100"
                         x-transition:leave="transition ease-in duration-150"
                         x-transition:leave-start="opacity-100"
                         x-transition:leave-end="opacity-0"
                         @keydown.escape.window="showPendingModal = false; document.body.style.overflow = ''"
                    >
                        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm"
                             @click="showPendingModal = false; document.body.style.overflow = ''"></div>

                            <div data-pending-modal-panel
                                class="relative w-full h-full sm:w-[760px] sm:h-auto sm:max-w-[95vw] sm:max-h-[90vh] bg-white rounded-none sm:rounded-2xl shadow-2xl flex flex-col overflow-hidden"
                             @click.stop
                             x-show="showPendingModal"
                             x-transition:enter="transition ease-out duration-300"
                             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
                             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-200"
                             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                             x-transition:leave-end="opacity-0 scale-95 translate-y-4">

                            {{-- Header --}}
                            <div class="flex items-center justify-between px-4 py-3 bg-gradient-to-r from-[#004D9D] to-[#0071B9] flex-shrink-0">
                                <div class="flex items-center gap-2.5 min-w-0">
                                    <svg class="w-5 h-5 text-white flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                    </svg>
                                    <div class="min-w-0">
                                        <h3 class="text-base font-bold text-white leading-tight">Pendências do Paciente</h3>
                                        <p class="text-white/70 text-xs leading-tight truncate">{{ $patient['nm_pessoa_fisica'] ?? '' }}</p>
                                    </div>
                                </div>
                                <button @click="showPendingModal = false; document.body.style.overflow = ''"
                                        title="Fechar"
                                        class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors flex-shrink-0">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                    </svg>
                                </button>
                            </div>

                            {{-- Barra de filtro --}}
                            <div class="flex items-center gap-2 px-3 py-2 border-b border-gray-100 bg-gray-50/80 flex-shrink-0">
                                <span class="text-[10px] text-gray-500 font-semibold uppercase tracking-wide mr-1">Período:</span>
                                <button @click="$dispatch('pending-filter', { v: false })"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap"
                                        :class="!pendingShowAll ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'">
                                    Ontem – Hoje – Amanhã
                                </button>
                                <button @click="$dispatch('pending-filter', { v: true })"
                                        class="px-2.5 py-1 rounded-lg text-[11px] font-semibold border transition-all whitespace-nowrap"
                                        :class="pendingShowAll ? 'bg-[#004D9D] text-white border-[#004D9D]' : 'bg-white text-gray-600 border-gray-200 hover:border-[#004D9D]'">
                                    Todas as Pendências
                                </button>
                            </div>

                            {{-- Grupos --}}
                            <div class="flex-1 overflow-y-auto min-h-0 p-3 space-y-3">
                                <div x-show="pendingShowAll ? {{ (($patient['pending_modal_meta']['all_count'] ?? 0) === 0) ? 'true' : 'false' }} : {{ (($patient['pending_modal_meta']['near_count'] ?? 0) === 0) ? 'true' : 'false' }}"
                                     class="rounded-xl border border-gray-200 bg-gray-50/60 p-6 text-center">
                                    <x-iconoir-walking class="text-gray-400 h-5 w-5 mx-auto" />
                                    <p class="text-xs text-gray-500 font-medium mt-2">Nenhuma pendência para este filtro.</p>
                                    <p class="text-[10px] text-gray-400 mt-1">Troque o período para visualizar outros itens.</p>
                                </div>

                                @foreach(($patient['pending_groups'] ?? []) as $group)

                                    {{-- Grupo: shell Blade (cores) + items Alpine (filtro + paginação) --}}
                                    <div x-data="{
                                            allItems: @js(array_values($group['events'] ?? [])),
                                            showAll: false,
                                            page: 1,
                                            perPage: 8,
                                            calcPerPage() {
                                                const modal = document.querySelector('[data-pending-modal-panel]');
                                                const modalHeight = modal ? modal.clientHeight : window.innerHeight;
                                                const reservedSpace = 430;
                                                const itemHeight = 96;
                                                const computed = Math.floor((modalHeight - reservedSpace) / itemHeight);
                                                this.perPage = Math.max(3, Math.min(10, computed || 8));
                                                if (this.page > this.pages) this.page = this.pages;
                                            },
                                            get items() {
                                                return this.showAll
                                                    ? this.allItems
                                                    : this.allItems.filter(i => i.is_near);
                                            },
                                            get paged() {
                                                return this.items.slice((this.page-1)*this.perPage, this.page*this.perPage);
                                            },
                                            get pages() {
                                                return Math.max(1, Math.ceil(this.items.length / this.perPage));
                                            }
                                         }"
                                         x-init="calcPerPage()"
                                         @resize.window="calcPerPage()"
                                         @pending-filter.window="showAll = $event.detail.v; page = 1"
                                         x-show="items.length > 0"
                                         class="rounded-xl border {{ $group['style']['border_header'] ?? 'border-gray-200' }} overflow-hidden">

                                        {{-- Cabeçalho do grupo --}}
                                        <div class="flex items-center justify-between px-3 py-2 {{ $group['style']['bg_header'] ?? 'bg-white/30' }} border-b {{ $group['style']['border_header'] ?? 'border-gray-200' }}">
                                            <span class="text-xs font-bold {{ $group['style']['text_header'] ?? 'text-[#062047]' }} uppercase tracking-wide">
                                                {{ $group['label'] ?? 'Pendências' }}
                                            </span>
                                        </div>

                                        {{-- Itens (Alpine x-for) --}}
                                        <div class="divide-y divide-gray-100/80">
                                            <template x-for="(ev, idx) in paged" :key="idx">
                                                   <div class="px-3 py-2.5 hover:brightness-95 transition-all {{ $group['style']['bg_card'] ?? 'bg-gray-50/50' }}"
                                                       :class="{ 'bg-[#7712C7]/10': ev.urgente }">
                                                    {{-- Ícone + descrição + badge --}}
                                                    <div class="flex items-start gap-2">
                                                        <img :src="'/images/icons/patient-card/' + (ev.icone || 'alert-circle.svg')"
                                                             class="w-4 h-4 flex-shrink-0 mt-0.5 opacity-80" alt="">
                                                        <div class="flex-1 min-w-0">
                                                            <div class="text-xs font-semibold leading-snug"
                                                                :class="ev.urgente ? 'text-[#7712C7]' : 'text-[#062047]'"
                                                                 x-text="ev.descricao || 'Sem descrição'"></div>
                                                            <div x-show="ev.ds_subtipo || ev.nm_prescritor"
                                                                 class="text-[10px] text-gray-500 mt-0.5 flex flex-wrap gap-x-2">
                                                                <span x-show="ev.ds_subtipo" x-text="ev.ds_subtipo"></span>
                                                                <span x-show="ev.nm_prescritor" x-text="'· ' + ev.nm_prescritor" class="text-gray-400"></span>
                                                            </div>
                                                        </div>
                                                        <span x-show="ev.status_laudo"
                                                              x-text="ev.status_laudo"
                                                              class="text-[9px] px-1.5 py-0.5 rounded-full flex-shrink-0 whitespace-nowrap"
                                                              :class="ev.urgente ? 'bg-[#7712C7] text-white' : 'bg-[#004D9D]/10 text-[#004D9D]'"></span>
                                                    </div>
                                                    {{-- Datas e tempo --}}
                                                    <div class="flex flex-wrap gap-x-3 gap-y-0.5 mt-1.5 text-[10px] text-gray-500">
                                                        <template x-if="ev.dt_evento_formatted">
                                                            <span>
                                                                <span class="font-medium text-gray-600">Previsto: </span>
                                                                <span x-text="ev.dt_evento_formatted"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="ev.dt_solicitacao">
                                                            <span>
                                                                <span class="font-medium text-gray-600">Solicitado: </span>
                                                                <span x-text="ev.dt_solicitacao"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="ev.dt_autorizacao">
                                                            <span>
                                                                <span class="font-medium text-gray-600">Liberado: </span>
                                                                <span x-text="ev.dt_autorizacao"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="ev.nr_prescricao">
                                                            <span>
                                                                <span class="font-medium text-gray-600">Prescrição: </span>
                                                                <span x-text="ev.nr_prescricao"></span>
                                                            </span>
                                                        </template>
                                                        <template x-if="ev.dt_coleta">
                                                            <span>
                                                                <span class="font-medium text-gray-600">Coletado: </span>
                                                                <span x-text="ev.dt_coleta"></span>
                                                            </span>
                                                        </template>
                                                        <span x-show="ev.tempo_pendente"
                                                              x-text="ev.tempo_pendente"
                                                              class="font-semibold"
                                                              :class="ev.urgente ? 'text-[#7712C7]' : 'text-[#0071B9]'"></span>
                                                                                                                <span x-show="['cirurgia','hemoterapia','quimioterapia'].includes(ev.tipo) && (ev.setor_execucao || true)"
                                                                                                                            class="inline-flex items-center gap-1 text-[10px] font-semibold text-indigo-700 bg-indigo-50 border border-indigo-200 px-1.5 py-0.5 rounded">
                                                                                                                        <i class="fa-solid fa-hospital text-indigo-500" style="font-size:9px;"></i>
                                                                                                                    <span x-text="ev.setor_execucao || '{{ $patient['sector_exec_fallback'] ?? 'Setor não informado' }}'"></span>
                                                                                                                </span>
                                                          <span x-show="ev.ds_complemento && ev.tipo !== 'antibiotico'"
                                                              x-text="ev.ds_complemento"
                                                              class="text-gray-500 italic"></span>
                                                    </div>
                                                    {{-- Motivo da pendência --}}
                                                    <template x-if="ev.motivo_pendente">
                                                        <div class="mt-1 flex items-center gap-1 text-[10px]"
                                                             :class="{
                                                                'text-orange-700': ev.foi_executado_sem_baixa || ev.exame_coletado_em_prescricao_mais_nova,
                                                                'text-gray-500': !(ev.foi_executado_sem_baixa || ev.exame_coletado_em_prescricao_mais_nova)
                                                             }">
                                                            <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                                                      d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                                            </svg>
                                                            <span x-text="ev.motivo_pendente"></span>
                                                        </div>
                                                    </template>
                                                </div>
                                            </template>

                                            {{-- Estado vazio para filtro near --}}
                                            <div x-show="items.length === 0"
                                                 class="px-3 py-4 text-center">
                                                <p class="text-[11px] text-gray-400">Nenhum item nos próximos 3 dias.</p>
                                                <button @click="$dispatch('pending-filter', { v: true })"
                                                        class="text-[11px] text-[#004D9D] font-semibold underline mt-1">
                                                    Ver todas
                                                </button>
                                            </div>
                                        </div>

                                        {{-- Paginação --}}
                                        <div x-show="pages > 1"
                                            class="flex items-center justify-between px-3 py-2 border-t {{ $group['style']['border_header'] ?? 'border-gray-200' }} {{ $group['style']['bg_header'] ?? 'bg-white/30' }}">
                                            <button @click="if(page > 1) page--"
                                                    :disabled="page === 1"
                                                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-lg
                                                        bg-white/70 border {{ $group['style']['border_header'] ?? 'border-gray-200' }} {{ $group['style']['text_header'] ?? 'text-[#062047]' }}
                                                           disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white transition-colors">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                                                </svg>
                                                Anterior
                                            </button>
                                            <span class="text-[10px] {{ $group['style']['text_header'] ?? 'text-[#062047]' }} font-medium">
                                                pág. <span x-text="page"></span> / <span x-text="pages"></span>
                                            </span>
                                            <button @click="if(page < pages) page++"
                                                    :disabled="page >= pages"
                                                    class="inline-flex items-center gap-1 text-[10px] font-semibold px-2 py-1 rounded-lg
                                                           bg-white/70 border {{ $group['style']['border_header'] ?? 'border-gray-200' }} {{ $group['style']['text_header'] ?? 'text-[#062047]' }}
                                                           disabled:opacity-40 disabled:cursor-not-allowed hover:bg-white transition-colors">
                                                Próxima
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                                </svg>
                                            </button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Details Button --}}
                <div
                    class="flex-shrink-0 p-1.5 border-t border-white/10 z-10"
                    x-data='{ sbarPatient: @json($patient), hospitalName: @json($currentHospitalName ?? "") }'
                >
                    <button
                        type="button"
                        wire:loading.attr="disabled"
                        wire:loading.class="opacity-60 cursor-not-allowed hover:bg-white/20 hover:shadow-sm"
                        wire:target="changeHospital,changeSector,refreshData"
                        class="w-full bg-white/20 text-gray-700 px-3 py-2 rounded-md flex items-center justify-center gap-2 shadow-sm transition-all duration-150 text-xs sm:text-sm font-medium backdrop-blur-[4px] cursor-pointer hover:bg-white/30 hover:shadow-md active:bg-white/40 focus:outline-none focus:ring-2 focus:ring-blue-500/50 focus:ring-offset-2 disabled:opacity-60 disabled:cursor-not-allowed disabled:hover:bg-white/20 disabled:hover:shadow-sm"
                        @click.prevent="$dispatch('openModal', { attendanceNumber: sbarPatient.nr_atendimento ?? 0, hospital: hospitalName, sbarPatient: sbarPatient })"
                    >
                        <span>Detalhes</span>
                    </button>
                </div>
            </div>
        @endif
    </div>
</div>

{{-- CSS --}}
<style>
    /* Animação do Modal */
    @keyframes modal-slide-in {
        from {
            opacity: 0;
            transform: translateY(-20px) scale(0.95);
        }
        to {
            opacity: 1;
            transform: translateY(0) scale(1);
        }
    }

    /* Force definitive card size and prevent children from expanding */
    .patient-card .card-inner.patient-card-fixed {
        height: 400px !important;
        max-height: 400px !important;
        display: flex;
        flex-direction: column;
    }

    /* Ensure direct flex children can shrink properly */
    .patient-card .card-inner > * {
        min-height: 0;
    }

    /* Prevent inner flex-grow items from increasing card height */
    .patient-card .card-inner .flex-1,
    .patient-card .card-inner .flex-grow,
    .patient-card .card-inner .h-full {
        min-height: 0;
    }

    /* Keep scrollable areas constrained */
    .patient-card .card-inner .custom-scrollbar-pending {
        overflow: auto;
    }

    /* Scrollbar Customizado */
    .custom-scrollbar-pending::-webkit-scrollbar,
    .custom-scrollbar::-webkit-scrollbar {
        width: 6px;
    }

    .custom-scrollbar-pending::-webkit-scrollbar-track,
    .custom-scrollbar::-webkit-scrollbar-track {
        background: rgba(0, 0, 0, 0.05);
        border-radius: 3px;
    }

    .custom-scrollbar-pending::-webkit-scrollbar-thumb,
    .custom-scrollbar::-webkit-scrollbar-thumb {
        background: rgba(0, 0, 0, 0.25);
        border-radius: 3px;
    }

    .custom-scrollbar-pending::-webkit-scrollbar-thumb:hover,
    .custom-scrollbar::-webkit-scrollbar-thumb:hover {
        background: rgba(0, 0, 0, 0.45);
    }

    .custom-scrollbar-pending,
    .custom-scrollbar {
        scrollbar-width: thin;
        scrollbar-color: rgba(0, 0, 0, 0.25) rgba(0, 0, 0, 0.05);
    }

    /* Alpine.js Cloak */
    [x-cloak] {
        display: none !important;
    }
</style>
