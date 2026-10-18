<?php

namespace Tests\Feature\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use App\Support\AuthenticationThrottle;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AuthenticationLockoutTest extends TestCase
{
    use RefreshDatabase;

    public static function challenges(): array
    {
        return [['password'], ['mfa']];
    }

    #[DataProvider('challenges')]
    public function test_fifth_failure_starts_a_full_minute_and_refresh_keeps_the_remaining_time(string $challenge): void
    {
        $this->freezeTime();
        $user = $this->prepareUser($challenge);

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->attempt($challenge, $user)->assertSessionHasErrors($this->field($challenge));
            $this->assertNull(AuthenticationThrottle::status($this->key($challenge, $user)));
            $this->travel(8)->seconds();
        }

        $this->attempt($challenge, $user)->assertSessionHasErrors([
            $this->field($challenge) => $this->prefix($challenge).' Please try again in 60 seconds.',
        ]);
        $this->travel(7)->seconds();
        $this->get($this->path($challenge))->assertInertia(fn (Assert $page) => $page
            ->where('lockout.showCountdown', true)
            ->where('lockout.retryAfter', 53));

        $this->attempt($challenge, $user, valid: true)->assertSessionHasErrors($this->field($challenge));
        $this->assertGuest();
        $this->assertSame(53, AuthenticationThrottle::status($this->key($challenge, $user))['retryAfter']);

        $this->travel(23)->seconds();
        $this->attempt($challenge, $user)->assertSessionHasErrors($this->field($challenge));
        $this->attempt($challenge, $user, valid: true)->assertSessionHasErrors($this->field($challenge));
        $this->assertGuest();
        $this->assertSame(30, AuthenticationThrottle::status($this->key($challenge, $user))['retryAfter']);

        $this->travel(31)->seconds();
        $this->attempt($challenge, $user, valid: true)->assertRedirect(
            $challenge === 'password' ? '/mfa-challenge' : '/dashboard'
        );
    }

    #[DataProvider('challenges')]
    public function test_next_failure_after_first_lockout_gets_a_temporary_wait_without_a_timer(string $challenge): void
    {
        $this->freezeTime();
        $user = $this->prepareUser($challenge);
        $this->lock($challenge, $user);
        $this->travel(61)->seconds();

        $this->attempt($challenge, $user)->assertSessionHasErrors([
            $this->field($challenge) => 'Too many attempts. Please try again later.',
        ]);
        $this->get($this->path($challenge))->assertInertia(fn (Assert $page) => $page
            ->where('lockout.showCountdown', false)
            ->where('lockout.retryAfter', 900));

        $this->travel(30)->seconds();
        $this->attempt($challenge, $user)->assertSessionHasErrors($this->field($challenge));
        $this->attempt($challenge, $user, valid: true)->assertSessionHasErrors($this->field($challenge));
        $this->assertGuest();
        $this->assertSame(870, AuthenticationThrottle::status($this->key($challenge, $user))['retryAfter']);

        $this->travel(871)->seconds();
        $this->attempt($challenge, $user, valid: true)->assertRedirect(
            $challenge === 'password' ? '/mfa-challenge' : '/dashboard'
        );
        $this->assertNull(AuthenticationThrottle::status($this->key($challenge, $user)));
        $this->assertNull(AuthenticationThrottle::recordFailure($this->key($challenge, $user)));
        $this->assertSame(1, RateLimiter::attempts($this->key($challenge, $user)));
    }

    #[DataProvider('challenges')]
    public function test_correct_retry_after_initial_wait_clears_previous_lockout_history(string $challenge): void
    {
        $this->freezeTime();
        $user = $this->prepareUser($challenge);
        $this->lock($challenge, $user);
        $this->travel(61)->seconds();

        $this->attempt($challenge, $user, valid: true)->assertSessionHasNoErrors();
        $this->assertNull(AuthenticationThrottle::recordFailure($this->key($challenge, $user)));
        $this->assertSame(1, RateLimiter::attempts($this->key($challenge, $user)));
    }

    #[DataProvider('challenges')]
    public function test_previous_lockout_history_expires_after_an_hour(string $challenge): void
    {
        $this->freezeTime();
        $user = $this->prepareUser($challenge);
        $this->lock($challenge, $user);
        $this->travel(3601)->seconds();

        $this->attempt($challenge, $user)->assertSessionHasErrors($this->field($challenge));
        $this->assertNull(AuthenticationThrottle::status($this->key($challenge, $user)));
        $this->assertSame(1, RateLimiter::attempts($this->key($challenge, $user)));
    }

    public function test_lockout_is_scoped_to_the_account_and_ip(): void
    {
        $user = $this->prepareUser('password');
        $this->lock('password', $user);
        $other = User::factory()->create();
        $this->attempt('password', $other, valid: true)->assertRedirect('/mfa-challenge');

        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.10']);
        $this->attempt('password', $user, valid: true)->assertRedirect('/mfa-challenge');
        $this->assertNotNull(AuthenticationThrottle::status($this->key('password', $user)));
    }

    public function test_cancelling_and_restarting_mfa_does_not_clear_its_lockout(): void
    {
        $user = $this->prepareUser('mfa');
        $this->lock('mfa', $user);
        $this->post('/mfa-cancel')->assertRedirect('/login');
        $this->attempt('password', $user, valid: true)->assertRedirect('/mfa-challenge');
        $this->attempt('mfa', $user, valid: true)->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    private function prepareUser(string $challenge): User
    {
        $user = User::factory()->create([
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
            'mfa_confirmed_at' => now(),
        ]);
        if ($challenge === 'mfa') {
            $this->withSession(['auth.mfa.user_id' => $user->id]);
        }

        return $user;
    }

    private function attempt(string $challenge, User $user, bool $valid = false): TestResponse
    {
        return $this->from($this->path($challenge))->post($this->path($challenge), $challenge === 'password'
            ? ['email' => $user->email, 'password' => $valid ? 'password' : 'wrong-password']
            : ['code' => $valid ? Totp::getCode($user->mfa_secret) : 'not-a-code']);
    }

    private function lock(string $challenge, User $user): void
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->attempt($challenge, $user);
        }
    }

    private function path(string $challenge): string
    {
        return $challenge === 'password' ? '/login' : '/mfa-challenge';
    }

    private function field(string $challenge): string
    {
        return $challenge === 'password' ? 'email' : 'code';
    }

    private function prefix(string $challenge): string
    {
        return $challenge === 'password' ? 'Too many login attempts.' : 'Too many invalid verification attempts.';
    }

    private function key(string $challenge, User $user): string
    {
        return $challenge === 'password'
            ? LoginRequest::keyFor($user->email, '127.0.0.1')
            : 'mfa|'.$user->id.'|127.0.0.1';
    }
}
