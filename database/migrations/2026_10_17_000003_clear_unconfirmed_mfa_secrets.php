<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')
            ->whereNull('mfa_confirmed_at')
            ->update(['mfa_secret' => null]);
    }

    public function down(): void
    {
        // Cleared MFA setup secrets cannot be safely restored.
    }
};
