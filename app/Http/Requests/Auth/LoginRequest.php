<?php

namespace App\Http\Requests\Auth;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\AuthenticationThrottle;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class LoginRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ];
    }

    /**
     * Attempt to authenticate the request's credentials and return the validated user.
     *
     * @throws ValidationException
     */
    public function authenticate(): User
    {
        $this->session()->put('auth.login.email', (string) $this->string('email'));
        $this->ensureIsNotRateLimited();

        if (! Auth::validate($this->only('email', 'password'))) {
            AuthenticationThrottle::recordFailure($this->throttleKey());

            AuditLog::record($this, 'auth.login.failed');

            $this->ensureIsNotRateLimited();

            throw ValidationException::withMessages([
                'email' => trans('auth.failed'),
            ]);
        }

        AuthenticationThrottle::clear($this->throttleKey());
        $this->session()->forget('auth.login.email');

        return User::where('email', $this->string('email'))->firstOrFail();
    }

    /**
     * Ensure the login request is not rate limited.
     *
     * @throws ValidationException
     */
    public function ensureIsNotRateLimited(): void
    {
        if (! $status = AuthenticationThrottle::status($this->throttleKey())) {
            return;
        }

        event(new Lockout($this));

        AuditLog::record($this, 'auth.login.locked');

        throw ValidationException::withMessages([
            'email' => AuthenticationThrottle::message($status, 'Too many login attempts.'),
        ]);
    }

    /**
     * Get the rate limiting throttle key for the request.
     */
    public function throttleKey(): string
    {
        return self::keyFor((string) $this->string('email'), $this->ip());
    }

    public static function keyFor(string $email, string $ip): string
    {
        return Str::transliterate(Str::lower($email).'|'.$ip);
    }
}
