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

        $findUser = $this->users()->find([
            'username' => auth()->user()->username
        ]);

        if ( $findUser && $findUser->status == 'I' )
            abort(403);

        return $next($request);
    }

}
