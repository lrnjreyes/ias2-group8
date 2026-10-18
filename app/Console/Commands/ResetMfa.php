<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;

class ResetMfa extends Command
{
    protected $signature = 'auth:reset-mfa';

    protected $description = 'Reset authenticator setup for all accounts and clear login and MFA lockouts';

    public function handle(): int
    {
        if ($this->call('cache:clear') !== self::SUCCESS) {
            $this->error('The lockouts could not be cleared. Authenticator setup has not been reset.');

            return self::FAILURE;
        }

        $count = User::query()->update([
            'mfa_enabled' => true,
            'mfa_secret' => null,
            'mfa_confirmed_at' => null,
            'remember_token' => null,
        ]);

        $this->info("Authenticator setup reset for {$count} ".($count === 1 ? 'account.' : 'accounts.'));
        $this->info('Login and MFA lockouts cleared, including the 15-minute wait.');
        $this->info('Sign in again and scan the new QR code. Previous authenticator entries must be replaced.');

        return self::SUCCESS;
    }
}
