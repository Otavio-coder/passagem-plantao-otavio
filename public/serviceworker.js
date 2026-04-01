/**
 * Passagem de Plantão – Service Worker
 * Estratégia híbrida: Cache-first para assets, Network-first para páginas
 *
 * Versão: atualizar APP_CACHE_VERSION a cada deploy para invalidar caches
 */

const APP_CACHE_VERSION = '2.2.0';
const CACHE_STATIC  = `plantao-static-${APP_CACHE_VERSION}`;
const CACHE_PAGES   = `plantao-pages-${APP_CACHE_VERSION}`;
const CACHE_FONTS   = `plantao-fonts-${APP_CACHE_VERSION}`;
const ALL_CACHES    = [CACHE_STATIC, CACHE_PAGES, CACHE_FONTS];

/** Assets que devem ser pré-cacheados no install */
const PRECACHE_ASSETS = [
    '/offline',
    '/images/favicon.ico',
    '/images/santacasa-horizontal-branco.svg',
    '/images/icons/icon-192x192.png',
    '/images/icons/icon-512x512.png',
];

/** Padrões de URL que usam estratégia cache-first */
const STATIC_PATTERNS = [
    /\/build\/assets\//,
    /\/images\//,
    /\/vendor\/fontawesome/,
    /\/css\//,
    /\/js\//,
];

/** Padrões de URL que NUNCA devem ser cacheados */
const NEVER_CACHE_PATTERNS = [
    /\.mp4$/i,
    /\.webm$/i,
    /\.mov$/i,
    /\/livewire\//,
    /\/api\//,
    /\?livewire/,
    /broadcasting\/auth/,
    /sanctum\/csrf/,
    /manifest\.json/,
];

// ─── INSTALL ──────────────────────────────────────────────────────────────────
self.addEventListener('install', event => {
    event.waitUntil(
        caches.open(CACHE_STATIC)
            .then(cache => cache.addAll(PRECACHE_ASSETS))
            .then(() => self.skipWaiting())
            .catch(err => console.warn('[SW] Pre-cache falhou (assets opcionais):', err))
    );
});

// ─── ACTIVATE ─────────────────────────────────────────────────────────────────
self.addEventListener('activate', event => {
    event.waitUntil(
        caches.keys()
            .then(keys =>
                Promise.all(
                    keys
                        .filter(key => key.startsWith('plantao-') && !ALL_CACHES.includes(key))
                        .map(key => {
                            console.log('[SW] Removendo cache antigo:', key);
                            return caches.delete(key);
                        })
                )
            )
            .then(() => self.clients.claim())
    );
});

// ─── FETCH ────────────────────────────────────────────────────────────────────
self.addEventListener('fetch', event => {
    const req = event.request;
    const url = new URL(req.url);

    // Só intercepta GET
    if (req.method !== 'GET') return;

    // Safari/iOS: não interceptar range requests (vídeo, áudio)
    if (req.headers.get('Range')) return;

    // Nunca cacheia chamadas Livewire/API/auth/vídeos
    if (NEVER_CACHE_PATTERNS.some(p => p.test(url.pathname) || p.test(url.search))) return;

    // Fontes do Google: cache-first com cache dedicado
    if (url.hostname === 'fonts.googleapis.com' || url.hostname === 'fonts.gstatic.com') {
        event.respondWith(cacheFirst(req, CACHE_FONTS));
        return;
    }

    // Só trata requests da mesma origem
    if (url.origin !== self.location.origin) return;

    // Assets estáticos: cache-first com revalidação em background
    if (STATIC_PATTERNS.some(p => p.test(url.pathname))) {
        event.respondWith(staleWhileRevalidate(req, CACHE_STATIC));
        return;
    }

    // Páginas HTML: network-first com fallback para offline
    if (req.headers.get('Accept')?.includes('text/html')) {
        event.respondWith(networkFirstWithOfflineFallback(req));
        return;
    }
});

// ─── ESTRATÉGIAS ──────────────────────────────────────────────────────────────

/** Verifica se é seguro cachear a resposta */
function isCacheable(response) {
    return (
        response &&
        response.ok &&
        response.status !== 206 &&   // sem respostas parciais (range requests - Safari/iOS)
        response.type !== 'opaque'   // sem respostas cross-origin sem CORS
    );
}

/** Cache-first: serve do cache; atualiza em background se veio do cache */
async function cacheFirst(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);
    if (cached) {
        // Revalida silenciosamente em background
        fetch(request).then(res => {
            if (isCacheable(res)) cache.put(request, res.clone());
        }).catch(() => {});
        return cached;
    }
    try {
        const response = await fetch(request);
        if (isCacheable(response)) cache.put(request, response.clone());
        return response;
    } catch {
        return cached || Response.error();
    }
}

/** Stale-while-revalidate: sempre serve do cache enquanto atualiza em background */
async function staleWhileRevalidate(request, cacheName) {
    const cache = await caches.open(cacheName);
    const cached = await cache.match(request);

    const networkFetch = fetch(request).then(response => {
        if (isCacheable(response)) cache.put(request, response.clone());
        return response;
    }).catch(() => null);

    return cached || networkFetch;
}

/** Network-first com fallback para cache e página offline */
async function networkFirstWithOfflineFallback(request) {
    try {
        const response = await fetch(request);
        if (isCacheable(response)) {
            const cache = await caches.open(CACHE_PAGES);
            cache.put(request, response.clone());
        }
        return response;
    } catch {
        const cached = await caches.match(request);
        if (cached) return cached;

        // Fallback para a página offline
        const offline = await caches.match('/offline');
        return offline || new Response(
            '<html><body><h1>Sem conexão</h1><p>Verifique sua internet e tente novamente.</p></body></html>',
            { headers: { 'Content-Type': 'text/html; charset=utf-8' }, status: 503 }
        );
    }
}

// ─── MENSAGENS DO CLIENTE ──────────────────────────────────────────────────────
self.addEventListener('message', event => {
    if (event.data?.type === 'SKIP_WAITING') {
        self.skipWaiting();
    }
    if (event.data?.type === 'CLEAR_ALL_CACHES') {
        caches.keys().then(keys =>
            Promise.all(keys.filter(k => k.startsWith('plantao-')).map(k => caches.delete(k)))
        ).then(() => {
            event.ports[0]?.postMessage({ success: true });
        });
    }
});
