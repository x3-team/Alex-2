<?php

namespace App\Http\Controllers\Doctors;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Concerns\ResolvesDoctorsRoutes;
use App\Services\DetectSite;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Inertia\Inertia;
use Inertia\Response;

class DoctorAuthController extends Controller
{
    use ResolvesDoctorsRoutes;

    public function createLogin(DetectSite $detectSite): Response|RedirectResponse
    {
        $this->ensureDoctorsContour($detectSite);

        if ($this->authenticatedDoctor()) {
            return redirect($detectSite->doctorsUrl('/cabinet'));
        }

        return Inertia::render('Doctors/Auth/Login', [
            'canResetPassword' => false,
            'status' => session('status'),
        ]);
    }

    public function storeLogin(Request $request, DetectSite $detectSite): RedirectResponse
    {
        $this->ensureDoctorsContour($detectSite);

        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required'],
        ]);

        if (! Auth::attempt(array_merge($credentials, ['is_doctor' => true]), $request->boolean('remember'))) {
            return back()->withErrors([
                'email' => 'Неверный email или пароль для врачебного контура.',
            ])->onlyInput('email');
        }

        $request->session()->regenerate();

        return redirect()->intended($detectSite->doctorsUrl('/cabinet'));
    }

    public function createRegister(DetectSite $detectSite): Response|RedirectResponse
    {
        $this->ensureDoctorsContour($detectSite);

        if ($this->authenticatedDoctor()) {
            return redirect($detectSite->doctorsUrl('/cabinet'));
        }

        return Inertia::render('Doctors/Auth/Register');
    }

    public function storeRegister(Request $request, DetectSite $detectSite): RedirectResponse
    {
        $this->ensureDoctorsContour($detectSite);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $userClass = config('auth.providers.users.model');
        $user = new $userClass;
        $user->forceFill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'password' => $this->passwordForCreate($user, $validated['password']),
            'is_doctor' => true,
        ]);
        $user->save();

        event(new Registered($user));

        Auth::login($user);
        $request->session()->regenerate();

        return redirect($detectSite->doctorsUrl('/cabinet'));
    }

    public function destroy(Request $request, DetectSite $detectSite): RedirectResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect($detectSite->doctorsUrl('/login'));
    }

    protected function authenticatedDoctor(): bool
    {
        $user = Auth::user();

        return $user && ($user->is_doctor ?? false);
    }

    /**
     * Laravel 11+ User casts password to `hashed`. Hash::make would double-hash
     * and make the next login fail.
     */
    public static function passwordForCreate(object $user, string $plain): string
    {
        if (method_exists($user, 'getCasts') && ($user->getCasts()['password'] ?? null) === 'hashed') {
            return $plain;
        }

        return Hash::make($plain);
    }

    protected function ensureDoctorsContour(DetectSite $detectSite): void
    {
        if (! $detectSite->isDoctorsSite()) {
            abort(404);
        }
    }
}
