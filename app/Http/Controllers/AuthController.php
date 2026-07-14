<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;

class AuthController extends Controller
{
    /**
     * Hidden login page — the only place the Keycloak button appears.
     *
     * @return \Illuminate\Contracts\View\View|RedirectResponse
     */
    public function login()
    {
        if (Auth::check()) {
            return redirect()->route('results.index');
        }

        return view('auth.login');
    }

    /**
     * Redirect to Keycloak for authentication.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('keycloak')->redirect();
    }

    /**
     * Handle the Keycloak callback: upsert the local user and log them in.
     */
    public function callback(Request $request): RedirectResponse
    {
        try {
            $kc = Socialite::driver('keycloak')->user();
        } catch (\Throwable $e) {
            Log::warning('Keycloak login failed', ['error' => $e->getMessage()]);

            return redirect()->route('login')->with('error', 'Prijava ni uspela. Poskusi znova.');
        }

        if (! $kc->getEmail()) {
            return redirect()->route('login')->with('error', 'Prijava ni uspela: manjka e-poštni naslov.');
        }

        $user = User::updateOrCreate(
            ['email' => $kc->getEmail()],
            [
                'name' => $kc->getName() ?? $kc->getNickname() ?? $kc->getEmail(),
                'keycloak_id' => $kc->getId(),
            ],
        );

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect()->intended(route('results.index'));
    }

    /**
     * Log out locally and return to the login page.
     */
    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}