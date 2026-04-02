<?php

namespace App\Http\Middleware;

use App\Services\UsesRepositories;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class VerifyAuthorization
{
    use UsesRepositories;

    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();

        if (! $user) {
            Log::warning('VerifyAuthorization: Usuário não autenticado', [
                'url' => $request->url(),
                'ajax' => $request->ajax(),
            ]);
            abort(403, 'Usuário não autenticado');
        }

        // Verifica se é um usuário local existente
        $findUser = $this->users()->find([
            'username' => $user->username,
        ]);

        Log::debug('VerifyAuthorization: Verificando usuário', [
            'username' => $user->username,
            'found_local' => (bool) $findUser,
            'status' => $findUser?->status,
            'url' => $request->url(),
        ]);

        // Se encontrou o usuário localmente, verifica se está ativo
        if ($findUser && $findUser->status == 'I') {
            Log::warning('VerifyAuthorization: Usuário inativo', ['username' => $user->username]);
            abort(403, 'Usuário inativo');
        }

        return $next($request);
    }
}
