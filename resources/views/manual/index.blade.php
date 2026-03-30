@extends('layouts.app')

@push('head')
<style>
    /* Override main padding so viewer fills the remaining height */
    main > div {
        padding-top: 0 !important;
        padding-bottom: 0 !important;
    }
    main > div > div {
        padding-left: 0 !important;
        padding-right: 0 !important;
        max-width: 100% !important;
    }
    main > div > div > div {
        display: block !important;
    }

    #pdf-canvas {
        display: block;
        margin: 0 auto;
        box-shadow: 0 4px 24px rgba(0,0,0,0.25);
    }

    #canvas-container {
        position: relative;
    }

    .pdf-toolbar-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 0.25rem;
        padding: 0.375rem 0.75rem;
        border-radius: 0.5rem;
        font-size: 0.8rem;
        font-weight: 600;
        transition: background 0.15s;
        white-space: nowrap;
        border: none;
        cursor: pointer;
    }
    .pdf-toolbar-btn:disabled {
        opacity: 0.35;
        cursor: default;
    }

    /* Swipe hint animation */
    @keyframes swipe-hint {
        0%   { transform: translateX(0); opacity: 0.6; }
        50%  { transform: translateX(-8px); opacity: 1; }
        100% { transform: translateX(0); opacity: 0.6; }
    }

    .mobile-zoom-wrapper {
        position: fixed;
        right: 12px;
        bottom: 92px;
        z-index: 50;
        pointer-events: none;
    }
    .mobile-zoom-card {
        backdrop-filter: blur(8px);
        background: rgba(17, 24, 39, 0.92);
        box-shadow: 0 10px 30px rgba(0,0,0,0.35);
        pointer-events: auto;
    }
    .mobile-hint {
        position: fixed;
        left: 50%;
        transform: translateX(-50%);
        bottom: 96px;
        z-index: 40;
        backdrop-filter: blur(10px);
    }
</style>
@endpush

@section('content')
<div class="w-full flex flex-col" style="height: calc(100svh - 64px);">

    {{-- ── Toolbar ──────────────────────────────────────── --}}
    <div class="flex-shrink-0 bg-gray-800 text-white flex items-center gap-2 px-3 py-2 flex-wrap">

        {{-- Back --}}
        <a href="{{ route('home') }}"
           class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white mr-1"
           title="Voltar ao início">
            <i class="fas fa-arrow-left text-xs"></i>
        </a>

        {{-- Title --}}
        <span class="font-semibold text-sm truncate max-w-[140px] sm:max-w-none hidden sm:block">
            Manual do Sistema
        </span>

        <div class="flex-1"></div>

        {{-- Prev / Page info / Next --}}
        <button id="btn-prev" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white" title="Página anterior" disabled>
            <i class="fas fa-angle-left"></i>
        </button>

        <span class="text-xs font-mono bg-white/10 rounded px-2 py-1.5 min-w-[80px] text-center select-none" id="page-info">
            — / —
        </span>

        <button id="btn-next" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white" title="Próxima página" disabled>
            <i class="fas fa-angle-right"></i>
        </button>

        <div class="w-px h-5 bg-white/20 mx-1 hidden sm:block"></div>

        {{-- Zoom --}}
        <button id="btn-zoom-out" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white hidden sm:inline-flex" title="Diminuir zoom">
            <i class="fas fa-magnifying-glass-minus text-xs"></i>
        </button>
        <span id="zoom-label" class="text-xs font-mono bg-white/10 rounded px-2 py-1.5 min-w-[52px] text-center select-none hidden sm:block">100%</span>
        <button id="btn-zoom-in" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white hidden sm:inline-flex" title="Aumentar zoom">
            <i class="fas fa-magnifying-glass-plus text-xs"></i>
        </button>
        <button id="btn-fit" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white text-xs hidden sm:inline-flex" title="Ajustar à largura">
            <i class="fas fa-arrows-left-right text-xs"></i>
        </button>

        <div class="w-px h-5 bg-white/20 mx-1"></div>

        {{-- Download --}}
        <a href="{{ route('documentation') }}" download
           class="pdf-toolbar-btn bg-[#004D9D] hover:bg-[#003d7a] text-white" title="Baixar PDF">
            <i class="fas fa-download text-xs"></i>
            <span class="hidden sm:inline">Baixar</span>
        </a>
    </div>

    {{-- ── Canvas area ──────────────────────────────────── --}}
    <div id="canvas-container"
         class="flex-1 overflow-auto bg-gray-600 flex flex-col items-center py-4 px-2 gap-4"
         style="-webkit-overflow-scrolling: touch; overscroll-behavior: contain;">

        {{-- Loading spinner --}}
        <div id="pdf-loading" class="flex flex-col items-center justify-center gap-3 text-white mt-16">
            <div class="w-10 h-10 border-4 border-white border-t-transparent rounded-full animate-spin"></div>
            <span class="text-sm">Carregando manual...</span>
        </div>

        {{-- Error state --}}
        <div id="pdf-error" class="hidden flex-col items-center justify-center gap-3 text-white mt-16 text-center px-4">
            <i class="fas fa-file-circle-exclamation text-4xl text-red-300"></i>
            <p class="font-semibold">Não foi possível carregar o manual.</p>
             <a href="{{ route('documentation') }}" target="_blank"
               class="pdf-toolbar-btn bg-[#004D9D] hover:bg-[#003d7a] text-white text-sm mt-2">
                <i class="fas fa-external-link-alt text-xs"></i>
                Abrir em nova aba
            </a>
        </div>

        <canvas id="pdf-canvas" class="rounded-sm hidden"></canvas>
    </div>

    {{-- ── Mobile zoom controls & hint ────────────────────── --}}
    <div class="mobile-zoom-wrapper sm:hidden">
        <div class="mobile-zoom-card rounded-full flex items-center gap-1 px-2 py-1 text-white border border-white/10">
            <button id="btn-zoom-out-m" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white px-3 py-1 text-sm">
                <i class="fas fa-magnifying-glass-minus text-xs"></i>
            </button>
            <button id="btn-fit-m" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white px-3 py-1 text-xs">
                Ajustar
            </button>
            <button id="btn-zoom-in-m" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white px-3 py-1 text-sm">
                <i class="fas fa-magnifying-glass-plus text-xs"></i>
            </button>
        </div>
    </div>
    <div id="mobile-hint" class="mobile-hint sm:hidden text-white/90 bg-black/70 rounded-lg px-3 py-2 text-[11px] shadow-lg opacity-100 transition-opacity duration-500 pointer-events-none">
        Toque duplo para alternar zoom. Deslize para mudar de página.
    </div>

    {{-- ── Mobile bottom bar ────────────────────────────── --}}
    <div class="flex-shrink-0 bg-gray-800 text-white flex items-center justify-between px-4 py-2 sm:hidden border-t border-white/10">
        <button id="btn-prev-m" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white flex-1 mr-2" disabled>
            <i class="fas fa-angle-left mr-1"></i> Anterior
        </button>
        <span class="text-xs font-mono text-gray-300 flex-shrink-0 mx-2" id="page-info-m">— / —</span>
        <button id="btn-next-m" class="pdf-toolbar-btn bg-white/10 hover:bg-white/20 text-white flex-1 ml-2" disabled>
            Próxima <i class="fas fa-angle-right ml-1"></i>
        </button>
    </div>

</div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/pdfjs/pdf.min.js') }}"></script>
<script>
(function () {
    const WORKER_URL = @json(asset('vendor/pdfjs/pdf.worker.min.js'));
    const PDF_URL    = @json(asset('doc-passagem.pdf'));

    pdfjsLib.GlobalWorkerOptions.workerSrc = WORKER_URL;

    // ── State ─────────────────────────────────────────────
    let pdfDoc      = null;
    let currentPage = 1;
    let scale       = 1.0;
    let baseFitScale= 1.0;
    let rendering   = false;
    let pendingPage = null;
    let lastTapTime = 0;

    const canvas    = document.getElementById('pdf-canvas');
    const ctx       = canvas.getContext('2d');
    const container = document.getElementById('canvas-container');

    // ── Elements ─────────────────────────────────────────
    const elLoading  = document.getElementById('pdf-loading');
    const elError    = document.getElementById('pdf-error');
    const elPageInfo = document.getElementById('page-info');
    const elPageInfoM= document.getElementById('page-info-m');
    const elZoom     = document.getElementById('zoom-label');
    const elHint     = document.getElementById('mobile-hint');

    const MIN_SCALE = 0.45;
    const MAX_SCALE = 3.2;

    function clampScale(val) {
        return Math.min(MAX_SCALE, Math.max(MIN_SCALE, val));
    }

    function setPageInfo(cur, total) {
        const txt = `${cur} / ${total}`;
        elPageInfo.textContent  = txt;
        elPageInfoM.textContent = txt;
    }

    function setNavState() {
        const total = pdfDoc ? pdfDoc.numPages : 0;
        ['btn-prev','btn-prev-m'].forEach(id => {
            const b = document.getElementById(id);
            if (b) b.disabled = currentPage <= 1;
        });
        ['btn-next','btn-next-m'].forEach(id => {
            const b = document.getElementById(id);
            if (b) b.disabled = currentPage >= total;
        });
    }

    // ── Fit scale to container width ──────────────────────
    function fitScale(page) {
        const viewport = page.getViewport({ scale: 1 });
        const available = Math.max(320, container.clientWidth - 16); // 2×8px padding
        const fitted = clampScale(available / viewport.width);
        baseFitScale = fitted;
        return fitted;
    }

    // ── Render ────────────────────────────────────────────
    function renderPage(num) {
        if (rendering) {
            pendingPage = num;
            return;
        }
        rendering = true;

        pdfDoc.getPage(num).then(page => {
            const viewport = page.getViewport({ scale });
            const outputScale = window.devicePixelRatio || 1;

            canvas.style.width  = `${viewport.width}px`;
            canvas.style.height = `${viewport.height}px`;
            canvas.width  = Math.floor(viewport.width  * outputScale);
            canvas.height = Math.floor(viewport.height * outputScale);
            ctx.setTransform(1, 0, 0, 1, 0, 0);

            const renderContext = {
                canvasContext: ctx,
                viewport,
                transform: outputScale !== 1 ? [outputScale, 0, 0, outputScale, 0, 0] : null,
            };

            page.render(renderContext).promise.then(() => {
                rendering = false;
                currentPage = num;
                setPageInfo(currentPage, pdfDoc.numPages);
                setNavState();
                if (elZoom) elZoom.textContent = Math.round(scale * 100) + '%';

                if (pendingPage !== null) {
                    const next = pendingPage;
                    pendingPage = null;
                    renderPage(next);
                }
            });
        });
    }

    // ── Load PDF ──────────────────────────────────────────
    pdfjsLib.getDocument(PDF_URL).promise.then(pdf => {
        pdfDoc = pdf;
        elLoading.style.display = 'none';
        canvas.classList.remove('hidden');

        // Auto-fit to width on first load
        pdf.getPage(1).then(page => {
            const fitted = fitScale(page);
            scale = fitted;
            renderPage(1);
        });

    }).catch(err => {
        console.error('PDF load error:', err);
        elLoading.style.display = 'none';
        elError.classList.remove('hidden');
        elError.style.display = 'flex';
    });

    // ── Navigation ────────────────────────────────────────
    function prevPage() { if (currentPage > 1) renderPage(currentPage - 1); }
    function nextPage() { if (pdfDoc && currentPage < pdfDoc.numPages) renderPage(currentPage + 1); }

    ['btn-prev','btn-prev-m'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', prevPage);
    });
    ['btn-next','btn-next-m'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', nextPage);
    });

    // ── Zoom ──────────────────────────────────────────────
    function applyScale(nextScale) {
        scale = clampScale(nextScale);
        if (!pdfDoc) return;
        if (elZoom) elZoom.textContent = Math.round(scale * 100) + '%';
        renderPage(currentPage);
    }

    function fitCurrent() {
        if (!pdfDoc) return;
        pdfDoc.getPage(currentPage).then(page => {
            const fitted = fitScale(page);
            scale = fitted;
            renderPage(currentPage);
        });
    }

    document.getElementById('btn-zoom-in')?.addEventListener('click', () => {
        applyScale(scale + 0.2);
    });
    document.getElementById('btn-zoom-out')?.addEventListener('click', () => {
        applyScale(scale - 0.2);
    });
    document.getElementById('btn-fit')?.addEventListener('click', () => {
        fitCurrent();
    });
    document.getElementById('btn-zoom-in-m')?.addEventListener('click', () => {
        applyScale(scale + 0.2);
    });
    document.getElementById('btn-zoom-out-m')?.addEventListener('click', () => {
        applyScale(scale - 0.2);
    });
    document.getElementById('btn-fit-m')?.addEventListener('click', () => {
        fitCurrent();
    });

    // ── Keyboard ──────────────────────────────────────────
    document.addEventListener('keydown', e => {
        if (e.key === 'ArrowLeft'  || e.key === 'ArrowUp')   prevPage();
        if (e.key === 'ArrowRight' || e.key === 'ArrowDown')  nextPage();
        if (e.key === '+' || e.key === '=') {
            applyScale(scale + 0.2);
        }
        if (e.key === '-') {
            applyScale(scale - 0.2);
        }
    });

    // ── Touch swipe (horizontal = next/prev page) ─────────
    let touchStartX = null, touchStartY = null;
    container.addEventListener('touchstart', e => {
        if (e.touches.length === 1) {
            touchStartX = e.touches[0].clientX;
            touchStartY = e.touches[0].clientY;
        } else {
            touchStartX = null;
            touchStartY = null;
        }
    }, { passive: true });

    function handleDoubleTap() {
        const now = Date.now();
        if (now - lastTapTime < 350) {
            const target = Math.abs(scale - baseFitScale) < 0.05
                ? clampScale(baseFitScale * 1.55)
                : baseFitScale;
            applyScale(target);
            if (elHint) {
                elHint.classList.add('opacity-0');
            }
            lastTapTime = 0;
            return true;
        }
        lastTapTime = now;
        return false;
    }

    container.addEventListener('touchend', e => {
        if (pinchStartDist && e.touches.length < 2) {
            renderPage(currentPage);
            pinchStartDist = null;
            return;
        }

        if (e.changedTouches.length === 1 && e.touches.length === 0) {
            if (handleDoubleTap()) {
                touchStartX = null;
                return;
            }
        }

        if (touchStartX === null) return;
        const dx = e.changedTouches[0].clientX - touchStartX;
        const dy = e.changedTouches[0].clientY - touchStartY;
        // Only treat as horizontal swipe if clearly horizontal and large enough
        if (Math.abs(dx) > Math.abs(dy) + 20 && Math.abs(dx) > 60) {
            dx < 0 ? nextPage() : prevPage();
        }
        touchStartX = null;
    }, { passive: true });

    // ── Pinch-to-zoom ─────────────────────────────────────
    let pinchStartDist = null;
    let pinchStartScale = 1;
    container.addEventListener('touchstart', e => {
        if (e.touches.length === 2) {
            const dx = e.touches[0].clientX - e.touches[1].clientX;
            const dy = e.touches[0].clientY - e.touches[1].clientY;
            pinchStartDist  = Math.hypot(dx, dy);
            pinchStartScale = scale;
        }
    }, { passive: true });
    container.addEventListener('touchmove', e => {
        if (e.touches.length === 2 && pinchStartDist) {
            const dx   = e.touches[0].clientX - e.touches[1].clientX;
            const dy   = e.touches[0].clientY - e.touches[1].clientY;
            const dist = Math.hypot(dx, dy);
            scale = clampScale(pinchStartScale * (dist / pinchStartDist));
            if (elZoom) elZoom.textContent = Math.round(scale * 100) + '%';
        }
    }, { passive: true });
    container.addEventListener('touchend', e => {
        if (pinchStartDist && e.touches.length < 2) {
            renderPage(currentPage);
            pinchStartDist = null;
        }
    }, { passive: true });

    // ── Re-fit on resize ──────────────────────────────────
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            if (!pdfDoc) return;
            pdfDoc.getPage(currentPage).then(page => {
                const fitted = fitScale(page);
                scale = fitted;
                renderPage(currentPage);
            });
        }, 250);
    });

    // ── Hint hide ─────────────────────────────────────────
    if (elHint) {
        setTimeout(() => elHint.classList.add('opacity-0'), 4500);
        elHint.addEventListener('transitionend', () => elHint.remove(), { once: true });
    }
})();
</script>
@endpush
