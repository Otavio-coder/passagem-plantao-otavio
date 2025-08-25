<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use App\Repositories\MySQL\UserRepository;
use Spatie\Permission\Models\Role;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();
        $request->session()->regenerate();

        // Se o usuário não existe localmente, provisiona automaticamente
        $localUser = auth()->user();
        if ($localUser && !$localUser->exists) {
            // Busca usuário no LDAP
            $username = $request->get('username');
            $ldapUser = \LdapRecord\Models\ActiveDirectory\User::query()->findBy('samaccountname', $username);

            if ($ldapUser) {
                $repo = new \App\Repositories\MySQL\UserRepository();
                $userData = [
                    'name'     => $ldapUser->getFirstAttribute('displayname') ?? $username,
                    'username' => $username,
                    'email'    => $ldapUser->getFirstAttribute('mail') ?? ($ldapUser->getFirstAttribute('userprincipalname') ?? null),
                    'created_by' => 0,
                    'guid'     => $ldapUser->getConvertedGuid(),
                    'domain'   => 'default',
                    'status'   => 'A',
                ];
                $user = $repo->store($userData, true);

                // Atribui perfil "Visualizador"
                $role = \Spatie\Permission\Models\Role::where('name', 'Visualizador')->first();
                if ($user && $role) {
                    $user->syncRoles([$role->name]);
                }

                // Autentica o usuário recém-criado
                auth()->login($user);
            }
        }

        return redirect()->intended(route('home', absolute: false));
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}

