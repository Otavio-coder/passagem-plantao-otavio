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

        $localUser = auth()->user();
        $username = $request->get('username');
        
        // Verifica se precisa provisionar o usuário localmente
        if ($localUser) {
            $existingUser = \App\Models\System\User::where('username', $username)->first();
            
            if (!$existingUser) {
                try {
                    // Busca usuário no LDAP para obter informações completas
                    $ldapUser = \LdapRecord\Models\ActiveDirectory\User::query()->findBy('samaccountname', $username);

                    if ($ldapUser) {
                        $repo = new \App\Repositories\MySQL\UserRepository();
                        $userData = [
                            'name'     => $ldapUser->getFirstAttribute('displayname') ?? $username,
                            'username' => $username,
                            'email'    => $ldapUser->getFirstAttribute('mail') ?? ($ldapUser->getFirstAttribute('userprincipalname') ?? "{$username}@santacasa.org.br"),
                            'created_by' => 0,
                            'guid'     => $ldapUser->getConvertedGuid(),
                            'domain'   => 'default',
                            'status'   => 'A',
                        ];
                        
                        $newUser = $repo->store($userData, true);

                        // Atribui perfil padrão "Visualizador"
                        $role = \Spatie\Permission\Models\Role::where('name', 'Visualizador')->first();
                        if ($newUser && $role) {
                            $newUser->syncRoles([$role->name]);
                        }
                    }
                } catch (\Exception $e) {
                    \Illuminate\Support\Facades\Log::error("Erro ao provisionar usuário {$username}: " . $e->getMessage());
                    // Continua o login mesmo se o provisionamento falhar
                }
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

