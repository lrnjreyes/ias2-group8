<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Totp;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'role', 'mfa_enabled', 'mfa_secret', 'mfa_confirmed_at', 'password'])]
#[Hidden(['password', 'remember_token', 'mfa_secret'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public function employeeRecord()
    {
        return $this->hasOne(EmployeeRecord::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'mfa_confirmed_at' => 'datetime',
            'password' => 'hashed',
            'mfa_enabled' => 'boolean',
        ];
    }

    /**
     * Determine if MFA is enabled for this user.
     */
    public function hasMfaEnabled(): bool
    {
        return (bool) $this->mfa_enabled;
    }

    /**
     * Determine if MFA setup has already been completed and confirmed.
     */
    public function isMfaConfirmed(): bool
    {
        return $this->hasMfaEnabled() && ! empty($this->mfa_secret) && ! is_null($this->mfa_confirmed_at);
    }

    /**
     * Verify an MFA code using TOTP.
     */
    public function verifyMfaCode(string $code): bool
    {
        if (empty($this->mfa_secret)) {
            return false;
        }

        return Totp::verify($this->mfa_secret, $code);
    }
}
