@props([
    'buttonClass',
    'ariaLabel',
    'tooltipClass',
    'tooltipTitle',
    'tooltipTitleBorder' => 'border-white/20',
    'modalGradient',
    'modalTitle',
    'modalWidth' => 'sm:w-[500px]',
    'outerClass' => '',
])
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
    }" @if($outerClass) class="{{ $outerClass }}" @endif>

    <button
        type="button"
        @mouseenter="openTip($el)"
        @mouseleave="closeTip()"
        @click="handleClick($el)"
        class="{{ $buttonClass }}"
        aria-label="{{ $ariaLabel }}"
    >
        {{ $buttonIcon }}
    </button>

    {{-- Tooltip --}}
    <div
        x-show="showTip"
        x-cloak
        :style="tipStyle"
        @mouseenter="showTip = true"
        @mouseleave="closeTip()"
        @click.outside="closeTip()"
        class="fixed z-[9999] rounded-2xl shadow-xl p-3 text-xs {{ $tooltipClass }}"
        @click.stop
    >
        <div class="font-semibold text-xs mb-1 border-b {{ $tooltipTitleBorder }} pb-0.5">{{ $tooltipTitle }}</div>
        {{ $tooltipContent }}
    </div>

    {{-- Modal --}}
    <div
        x-show="showModal"
        x-cloak
        @click.self="closeModal()"
        @keydown.escape.window="closeModal()"
        class="fixed inset-0 z-[9998] flex items-center justify-center p-0 sm:p-4"
        style="margin: 0 !important;"
    >
        <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeModal()"></div>
        <div class="relative bg-white rounded-xl sm:rounded-2xl overflow-hidden shadow-2xl w-full h-full sm:h-auto sm:max-h-[90vh] {{ $modalWidth }} flex flex-col"
             @click.stop
             x-show="showModal"
             x-transition:enter="transition ease-out duration-300"
             x-transition:enter-start="opacity-0 scale-95 translate-y-4"
             x-transition:enter-end="opacity-100 scale-100 translate-y-0"
             x-transition:leave="transition ease-in duration-200"
             x-transition:leave-start="opacity-100 scale-100 translate-y-0"
             x-transition:leave-end="opacity-0 scale-95 translate-y-4">
            <div class="flex items-center justify-between px-4 py-3 {{ $modalGradient }} flex-shrink-0">
                <div class="flex items-center gap-2.5">
                    {{ $modalHeaderIcon }}
                    <h3 class="text-base font-bold text-white">{{ $modalTitle }}</h3>
                </div>
                <button @click="closeModal()" class="p-2 text-white/70 hover:text-white hover:bg-white/15 rounded-lg transition-colors">
                    <x-heroicon-o-x-mark class="w-4 h-4" />
                </button>
            </div>
            <div class="flex-1 overflow-y-auto min-h-0 p-4 bg-gray-50">
                {{ $slot }}
            </div>
        </div>
    </div>
</div>
