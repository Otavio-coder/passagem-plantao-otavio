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
        _timer: null,
        openTip(btn) {
            window.dispatchEvent(new CustomEvent('close-alert-tooltip'));

            const r   = btn.getBoundingClientRect();
            const tipW = 200;
            let left  = r.left + r.width / 2;
            let top   = r.bottom + 6;

            if (left - tipW / 2 < 8)                     left = tipW / 2 + 8;
            if (left + tipW / 2 > window.innerWidth - 8) left = window.innerWidth - tipW / 2 - 8;

            const tipH = 140;
            if (top + tipH > window.innerHeight - 8)      top = r.top - tipH - 6;

            this.tipStyle = 'left:' + left + 'px;top:' + top + 'px;transform:translateX(-50%)';
            this.showTip  = true;

            clearTimeout(this._timer);
            this._timer = setTimeout(() => { this.showTip = false; }, 4000);
        },
        closeTip() {
            clearTimeout(this._timer);
            this.showTip = false;
        },
        openModal() { this.closeTip(); this.showModal = true; document.body.style.overflow = 'hidden'; },
        closeModal() { this.showModal = false; document.body.style.overflow = ''; },
        init() {
            window.addEventListener('close-alert-tooltip', () => this.closeTip());
            window.addEventListener('scroll',    () => this.closeTip(), { passive: true, capture: true });
            window.addEventListener('touchmove', () => this.closeTip(), { passive: true, capture: true });
        }
    }" @if($outerClass) class="{{ $outerClass }}" @endif>

    <button
        type="button"
        @mouseenter="openTip($el)"
        @mouseleave="closeTip()"
        @click="window.matchMedia('(pointer: coarse)').matches ? openTip($el) : openModal()"
        class="{{ $buttonClass }}"
        aria-label="{{ $ariaLabel }}"
    >
        {{ $buttonIcon }}
    </button>

    {{-- Tooltip --}}
    <div
        x-show="showTip"
        x-cloak
        x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="opacity-0 scale-95"
        x-transition:enter-end="opacity-100 scale-100"
        x-transition:leave="transition ease-in duration-100"
        x-transition:leave-start="opacity-100"
        x-transition:leave-end="opacity-0"
        :style="tipStyle"
        @click.outside="closeTip()"
        class="fixed z-[9999] rounded-lg shadow-xl px-3 py-2.5 text-xs {{ $tooltipClass }} pointer-events-none sm:pointer-events-auto"
        @click.stop
    >
        <div class="font-semibold text-xs mb-1.5 border-b {{ $tooltipTitleBorder }} pb-1">{{ $tooltipTitle }}</div>
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
