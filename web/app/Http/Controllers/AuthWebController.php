<?php

namespace App\Http\Controllers;

use App\Models\Usuario;
use App\Services\GoogleAuthService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

/** Login web exclusivamente con Google OAuth 2.0 + lista blanca (usuarios.activo / email_google). */
class AuthWebController extends Controller
{
    public function login()
    {
        if (Auth::check()) {
            return redirect()->route('panel');
        }

        return view('auth.login', ['devLogin' => config('peaje.dev_login'),
            'googleConfigurado' => filled(config('services.google.client_id'))]);
    }

    public function redirigir()
    {
        if (! filled(config('services.google.client_id'))) {
            return redirect()->route('login')->with('error', 'Google OAuth no está configurado (GOOGLE_CLIENT_ID / GOOGLE_CLIENT_SECRET).');
        }

        return Socialite::driver('google')->scopes(['openid', 'email', 'profile'])->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $g = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            return redirect()->route('login')->with('error', 'No se pudo completar el inicio de sesión con Google. Intenta de nuevo.');
        }
        $verificado = (bool) ($g->user['email_verified'] ?? $g->user['verified_email'] ?? true);
        $usuario = $verificado ? GoogleAuthService::autorizar(['email' => mb_strtolower((string) $g->getEmail()),
            'nombre' => $g->getName(), 'google_id' => $g->getId(), 'avatar' => $g->getAvatar()]) : null;
        if (! $usuario) {
            return response()->view('auth.denegado', ['email' => $g->getEmail()], 403);
        }
        Auth::login($usuario, true);
        $request->session()->regenerate();

        return redirect()->intended(route('panel'));
    }

    /** Solo APP_ENV=local + AUTH_DEV_LOGIN=true. Permite probar el panel sin Google con un correo de la lista blanca. */
    public function devLogin(Request $request)
    {
        abort_unless(config('peaje.dev_login'), 404);
        $u = Usuario::autorizado($request->input('email'));
        if (! $u) {
            return response()->view('auth.denegado', ['email' => $request->input('email')], 403);
        }
        Auth::login($u);
        $request->session()->regenerate();

        return redirect()->route('panel');
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
