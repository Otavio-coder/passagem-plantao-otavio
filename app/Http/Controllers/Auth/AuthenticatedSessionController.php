<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Jobs\SyncUserPhoto;
use App\Models\System\User as AppUser;
use App\Repositories\MySQL\UserRepository;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;
use Spatie\Permission\Models\Role;

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

        $now = now();
        $localUser = auth()->user();
        $username = $request->get('username');
        $userRecord = AppUser::where('username', $username)->first();

        // Verifica se precisa provisionar o usuário localmente
        if ($localUser) {
            if (! $userRecord) {
                try {
                    // Busca usuário no LDAP para obter informações completas
                    $ldapUser = LdapUser::query()->findBy('samaccountname', $username);

                    if ($ldapUser) {
                        $repo = new UserRepository;
                        $userData = [
                            'name' => $ldapUser->getFirstAttribute('displayname') ?? $username,
                            'username' => $username,
                            'email' => $ldapUser->getFirstAttribute('mail') ?? ($ldapUser->getFirstAttribute('userprincipalname') ?? "{$username}@santacasa.org.br"),
                            'created_by' => 0,
                            'guid' => $ldapUser->getConvertedGuid(),
                            'domain' => 'default',
                            'status' => 'A',
                            'last_access_at' => $now,
                        ];

                        $newUser = $repo->store($userData, true);

                        // Atribui perfil padrão "Usuário"
                        $role = Role::where('name', 'Usuário')->first();
                        if ($newUser && $role) {
                            $newUser->syncRoles([$role->name]);
                        }

                        if ($newUser) {
                            $userRecord = $newUser;
                        }
                    }
                } catch (\Exception $e) {
                    Log::error('Erro ao provisionar usuário no login', [
                        'exception' => $e,
                        'username' => $username,
                    ]);
                    // Continua o login mesmo se o provisionamento falhar
                }
            }
        }

        if ($userRecord) {
            $userRecord->forceFill(['last_access_at' => $now])->save();
            SyncUserPhoto::dispatch($userRecord->id);
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
