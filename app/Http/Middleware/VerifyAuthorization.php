<?php

namespace App\Http\Middleware;

use App\Services\UsesRepositories;
use Closure;
use Illuminate\Http\Request;

class VerifyAuthorization
{
    use UsesRepositories;

    /**
     * @param Request $request
     * @param Closure $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $user = auth()->user();
        
        if (!$user) {
            abort(403);
        }

        // Verifica se é um usuário local existente
        $findUser = $this->users()->find([
            'username' => $user->username
        ]);

        // Se encontrou o usuário localmente, verifica se está ativo
        if ($findUser && $findUser->status == 'I') {
            abort(403);
        }
        
        return $next($request);
    }
}
