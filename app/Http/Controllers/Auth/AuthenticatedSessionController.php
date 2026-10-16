<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Login', [
            'canResetPassword' => Route::has('password.request'),
            'status' => session('status'),
        ]);
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $user = $request->authenticate();

        if (! $user->hasMfaEnabled()) {
            $user->update(['mfa_enabled' => true]);
        }

        $request->session()->regenerate();
        $request->session()->put('auth.mfa.user_id', $user->id);

        AuditLog::record($request, 'auth.mfa.challenged', $user);

        return redirect()->route('mfa.challenge');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        AuditLog::record($request, 'auth.logout');

        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * End an inactive session and record the timeout event.
     */
    public function timeout(Request $request): RedirectResponse
    {
        AuditLog::record($request, 'auth.session.expired', metadata: [
            'idle_minutes' => (int) config('session.idle_timeout', 15),
        ]);

        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', 'Your session expired after 15 minutes of inactivity.');
    }
}
