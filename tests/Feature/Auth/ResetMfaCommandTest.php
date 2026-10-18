<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthenticationThrottle;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ResetMfaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_reset_clears_all_enrollments_and_lockout_history_without_changing_passwords(): void
    {
        $users = collect([
            User::factory()->create([
                'mfa_secret' => 'JBSWY3DPEHPK3PXP',
                'mfa_confirmed_at' => now(),
                'remember_token' => 'old-token',
            ]),
            User::factory()->create([
                'mfa_enabled' => false,
                'mfa_secret' => 'GEZDGNBVGY3TQOJQ',
                'mfa_confirmed_at' => null,
            ]),
        ]);
        $users->each->refresh();
        $keys = $users->flatMap(fn (User $user) => [
            LoginRequest::keyFor($user->email, '127.0.0.1'),
            'mfa|'.$user->id.'|127.0.0.1',
        ]);

        foreach ($keys as $key) {
            for ($i = 0; $i < 5; $i++) {
                AuthenticationThrottle::recordFailure($key);
            }
            RateLimiter::clear($key);
            AuthenticationThrottle::recordFailure($key);
            $this->assertFalse(AuthenticationThrottle::status($key)['showCountdown']);
        }

        $this->artisan('auth:reset-mfa')
            ->expectsOutputToContain('Authenticator setup reset for 2 accounts.')
            ->assertSuccessful();

        foreach ($users as $user) {
            $fresh = $user->fresh();
            $this->assertTrue($fresh->mfa_enabled);
            $this->assertNull($fresh->mfa_secret);
            $this->assertNull($fresh->mfa_confirmed_at);
            $this->assertNull($fresh->remember_token);
            foreach (['name', 'email', 'password', 'role'] as $attribute) {
                $this->assertSame($user->$attribute, $fresh->$attribute);
            }
        }
        $this->assertDatabaseCount('users', 2);

        foreach ($keys as $key) {
            $this->assertNull(AuthenticationThrottle::status($key));
            $this->assertNull(AuthenticationThrottle::recordFailure($key));
            $this->assertSame(1, RateLimiter::attempts($key));
        }
    }

    public function test_next_login_requires_a_new_qr_setup_and_can_complete_mfa(): void
    {
        $oldSecret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'mfa_secret' => $oldSecret,
            'mfa_confirmed_at' => now(),
        ]);

        $this->artisan('auth:reset-mfa')->assertSuccessful();
        $this->assertFalse($user->fresh()->verifyMfaCode(Totp::getCode($oldSecret)));

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/mfa-challenge');
        $this->assertGuest();

        $this->get('/mfa-challenge')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/MfaChallenge')
            ->where('isSetup', true)
            ->where('secret', fn (string $secret) => $secret !== '' && $secret !== $oldSecret));

        $newSecret = $user->fresh()->mfa_secret;
        $this->assertNotSame($oldSecret, $newSecret);
        $this->assertNull($user->fresh()->mfa_confirmed_at);
        $this->post('/mfa-challenge', ['code' => Totp::getCode($newSecret)])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->fresh()->mfa_confirmed_at);
    }
}
