<?php

namespace Tests\Evidence;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class MfaLockoutEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_five_failures_block_the_next_request_even_with_the_correct_password(): void
    {
        $user = User::factory()->create(['email' => 'employee@example.test', 'password' => 'DemoPassword!2026']);
        $key = $user->email.'|127.0.0.1';
        RateLimiter::clear($key);

        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect-evidence-password'])
                ->assertSessionHasErrors(['email' => trans('auth.failed')]);
            $this->assertGuest();
            $this->assertSame($attempt, RateLimiter::attempts($key));
        }

        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->assertSame(5, AuditLog::where('event', 'auth.login.failed')->count());
        $this->post('/login', ['email' => $user->email, 'password' => 'DemoPassword!2026'])
            ->assertSessionHas('errors', fn ($errors) => str_contains($errors->first('email'), 'Too many login attempts.'));
        $this->assertGuest();
        $this->assertSame(1, AuditLog::where('event', 'auth.login.locked')->count());
        $this->assertSame(0, AuditLog::where('event', 'auth.login.succeeded')->count());
    }

    public function test_lockout_expires_and_success_resets_the_failed_attempt_counter(): void
    {
        $user = User::factory()->create(['email' => 'employee@example.test', 'password' => 'DemoPassword!2026']);
        $key = $user->email.'|127.0.0.1';
        RateLimiter::clear($key);
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'incorrect-evidence-password']);
        }
        $this->assertTrue(RateLimiter::tooManyAttempts($key, 5));
        $this->travel(61)->seconds();

        $this->post('/login', ['email' => $user->email, 'password' => 'DemoPassword!2026'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->assertSame(0, RateLimiter::attempts($key));
        $this->assertSame(1, AuditLog::where('event', 'auth.login.succeeded')->count());
        $this->travelBack();
    }

    public function test_password_alone_currently_authenticates_without_an_mfa_challenge(): void
    {
        $user = User::factory()->create(['email' => 'mfa-evidence@example.test', 'password' => 'DemoPassword!2026']);
        RateLimiter::clear($user->email.'|127.0.0.1');

        $this->post('/login', ['email' => $user->email, 'password' => 'DemoPassword!2026'])
            ->assertRedirect(route('dashboard', absolute: false));
        $this->assertAuthenticatedAs($user);
        $this->get('/dashboard')->assertOk();
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $user->id, 'event' => 'auth.login.succeeded']);
    }
}
