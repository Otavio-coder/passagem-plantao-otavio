<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Configura headers HTTP corretos para PWA, Service Worker e assets.
 *
 * Garante que:
 * - manifest.json sempre é revalidado (nunca cacheado)
 * - serviceworker.js é revalidado frecuentemente
 * - assets com hash (build/) têm cache longo
 */
class PwaHeadersMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var Response $response */
        $response = $next($request);

        $path = $request->getPathInfo();

        // manifest.json: sempre revalidar, nunca cachear
        if (str_ends_with($path, 'manifest.json')) {
            $response->headers->set('Content-Type', 'application/json; charset=utf-8', true);
            $response->headers->set('Cache-Control', 'no-cache, public, must-revalidate', true);
            $response->headers->set('Pragma', 'no-cache', true);
            $response->headers->set('Expires', '0', true);
        }

        // serviceworker.js: revalidar frecuentemente (1 hora máximo)
        if (str_ends_with($path, 'serviceworker.js')) {
            $response->headers->set('Content-Type', 'application/javascript; charset=utf-8', true);
            $response->headers->set('Cache-Control', 'no-cache, public, max-age=3600', true);
            $response->headers->set('Pragma', 'no-cache', true);
        }

        // Assets versionados do Vite (build/): cache longo com revalidação
        if (str_starts_with($path, '/build/') && ! str_contains($path, '.map')) {
            $response->headers->set('Cache-Control', 'public, immutable, max-age=2592000', true); // 30 dias
        }

        return $response;
    }
}
