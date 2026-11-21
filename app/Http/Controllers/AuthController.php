<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** تسجيل دخول مديري النظام (حسابات جدول users) */
class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->intended('/admin');
        }

        return view('auth.login', ['systemName' => (string) \ET_Settings::get()['system_name']]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $key = 'admin-login:' . Str::lower($credentials['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'email' => 'محاولات كثيرة جدًا، حاول بعد ' . ceil(RateLimiter::availableIn($key) / 60) . ' دقيقة.',
            ]);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($key, 900);
            throw ValidationException::withMessages(['email' => 'البريد الإلكتروني أو كلمة المرور غير صحيحة.']);
        }

        RateLimiter::clear($key);
        $request->session()->regenerate();

        return redirect()->intended('/admin');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
