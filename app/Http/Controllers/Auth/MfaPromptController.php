<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuthenticationThrottle;
use App\Support\Totp;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class MfaPromptController extends Controller
{
    /**
     * Display the MFA challenge view (setup first time, code-only on subsequent logins).
     */
    public function create(Request $request): Response|RedirectResponse
    {
        $userId = $request->session()->get('auth.mfa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::find($userId);
        if (! $user || ! $user->hasMfaEnabled()) {
            $request->session()->forget(['auth.mfa.user_id', 'auth.mfa.remember']);

            return redirect()->route('login');
        }

        if (empty($user->mfa_secret)) {
            $user->update(['mfa_secret' => Totp::generateSecret()]);
        }

        $isSetup = is_null($user->mfa_confirmed_at) || $request->boolean('setup');

        $otpAuthUri = Totp::getOtpAuthUri(config('app.name', 'Employee Portal'), $user->email, $user->mfa_secret);

        return Inertia::render('Auth/MfaChallenge', [
            'email' => $user->email,
            'secret' => $user->mfa_secret,
            'isSetup' => (bool) $isSetup,
            'otpAuthUri' => $otpAuthUri,
            'status' => session('status'),
            'lockout' => AuthenticationThrottle::status('mfa|'.$user->id.'|'.$request->ip()),
        ]);
    }

    /**
     * Verify the incoming MFA verification code.
     */
    public function store(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.mfa.user_id');

        if (! $userId) {
            return redirect()->route('login');
        }

        $user = User::findOrFail($userId);

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $throttleKey = 'mfa|'.$user->id.'|'.$request->ip();

        if ($lockout = AuthenticationThrottle::status($throttleKey)) {
            AuditLog::record($request, 'auth.mfa.locked', $user);

            throw ValidationException::withMessages([
                'code' => AuthenticationThrottle::message($lockout, 'Too many invalid verification attempts.'),
            ]);
        }

        $submittedCode = preg_replace('/\s+/', '', (string) $request->input('code'));

        if (! $user->verifyMfaCode($submittedCode)) {
            $lockout = AuthenticationThrottle::recordFailure($throttleKey);

            AuditLog::record($request, 'auth.mfa.failed', $user, [
                'attempted_length' => strlen($submittedCode),
            ]);

            if ($lockout) {
                AuditLog::record($request, 'auth.mfa.locked', $user);
            }

            throw ValidationException::withMessages([
                'code' => $lockout
                    ? AuthenticationThrottle::message($lockout, 'Too many invalid verification attempts.')
                    : 'The provided verification code is invalid or has expired.',
            ]);
        }

        AuthenticationThrottle::clear($throttleKey);

        if (is_null($user->mfa_confirmed_at)) {
            $user->update(['mfa_confirmed_at' => now()]);
            AuditLog::record($request, 'auth.mfa.enrolled', $user);
        }

        $request->session()->forget(['auth.mfa.user_id', 'auth.mfa.remember']);

        Auth::login($user);

        $request->session()->regenerate();
        $request->session()->put('last_activity_at', now()->timestamp);

        AuditLog::record($request, 'auth.mfa.succeeded', $user);
        AuditLog::record($request, 'auth.login.succeeded', $user);

        return redirect()->intended(route('dashboard', absolute: false));
    }

    /**
     * Cancel the MFA challenge and return to login.
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->session()->forget(['auth.mfa.user_id', 'auth.mfa.remember']);

        return redirect()->route('login');
    }

    /**
     * Reset MFA with a new secret so an old or exposed QR code cannot be reused.
     */
    public function reset(Request $request): RedirectResponse
    {
        $user = $request->user();

        if ($user) {
            $user->update([
                'mfa_secret' => Totp::generateSecret(),
                'mfa_confirmed_at' => null,
            ]);

            AuditLog::record($request, 'auth.mfa.reset', $user);
        }

        return back()->with('status', 'Two-Factor Authentication setup has been reset. The QR code setup will appear on your next login.');
    }
}
