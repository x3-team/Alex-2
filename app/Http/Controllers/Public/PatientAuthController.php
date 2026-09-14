<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Password;
use Inertia\Inertia;

class PatientAuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Public/Login', [
            'meta' => [
                'title' => 'Вход в личный кабинет — ALEX LAB',
            ],
        ]);
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();

            // ПРЯМОЙ редирект на dashboard вместо intended()
            return redirect('/dashboard');
        }

        return back()->withErrors([
            'email' => 'Неверный email или пароль.',
        ])->onlyInput('email');
    }

    public function showRecover()
    {
        return Inertia::render('Public/Recover', [
            'meta' => [
                'title' => 'Восстановление доступа — ALEX LAB',
            ],
        ]);
    }

    public function recover(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Здесь можно подключить реальную отправку письма через Laravel Password::sendResetLink
        // Для демонстрации просто перенаправляем с флагом успеха

        return redirect()->route('patient.login')->with('status', 'Инструкции по восстановлению отправлены на вашу почту.');
    }
}