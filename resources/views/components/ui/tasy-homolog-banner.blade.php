{{--
    Banner de segurança: exibido em TODAS as páginas quando TASY_EVALUATION_CONNECTION
    não aponta para produção ('tasy'). Impossível de ignorar — fixo no topo, cor laranja
    vibrante, piscando. Só desaparece mudando o .env de volta.
--}}
@isset($__tasyHomologBanner)
<div id="tasy-homolog-banner"
     class="fixed top-0 inset-x-0 z-[999999] bg-gradient-to-r from-amber-500 via-orange-500 to-red-500 text-white shadow-lg"
     style="min-height: 44px;">
    <div class="flex items-center justify-center gap-3 px-4 py-2.5 text-center">
        <span class="text-xl animate-pulse">⚠️</span>
        <p class="text-sm font-bold tracking-wide uppercase">
            ATENÇÃO — Tasy avaliações apontando para
            <span class="underline decoration-2 decoration-white/80">{{ $__tasyHomologBanner }}</span>
            (NÃO É PRODUÇÃO)
        </p>
        <span class="text-xl animate-pulse">⚠️</span>
    </div>
    <div class="text-center pb-1.5">
        <p class="text-xs font-medium opacity-90">
            Altere <code class="bg-white/20 px-1.5 py-0.5 rounded text-[11px]">TASY_EVALUATION_CONNECTION=tasy</code>
            no <code class="bg-white/20 px-1.5 py-0.5 rounded text-[11px]">.env</code> para voltar à produção.
        </p>
    </div>
</div>
{{-- Empurra o conteúdo da página para baixo, evitando sobreposição --}}
<style>
    body { padding-top: 72px !important; }
    header.sticky { top: 72px !important; }
    #tasy-homolog-banner {
        animation: homolog-flash 2s ease-in-out infinite;
    }
    @keyframes homolog-flash {
        0%, 100% { opacity: 1; }
        50% { opacity: 0.85; }
    }
</style>
@endisset
