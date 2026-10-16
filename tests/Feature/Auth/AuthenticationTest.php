<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response->assertStatus(200);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $response = $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ]);

        $this->assertGuest();
        $response->assertRedirect(route('mfa.challenge', absolute: false));
        $this->get('/dashboard')->assertRedirect('/login');
        $this->get('/mfa-challenge')->assertOk();

        $response = $this->post('/mfa-challenge', [
            'code' => Totp::getCode($user->fresh()->mfa_secret),
        ]);

        $this->assertAuthenticatedAs($user);
        $response->assertRedirect(route('dashboard', absolute: false));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'wrong-password',
        ]);

        $this->assertGuest();
    }

    public function test_existing_account_with_mfa_disabled_must_set_up_authenticator(): void
    {
        $user = User::factory()->create(['mfa_enabled' => false]);

        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/mfa-challenge');

        $this->assertGuest();
        $this->assertTrue($user->fresh()->hasMfaEnabled());
        $this->get('/mfa-challenge')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/MfaChallenge')
            ->where('isSetup', true));
        $this->assertNotEmpty($user->fresh()->mfa_secret);
    }

    public function test_logout_and_sign_in_again_requires_a_fresh_mfa_challenge(): void
    {
        $secret = 'JBSWY3DPEHPK3PXP';
        $user = User::factory()->create([
            'mfa_enabled' => true,
            'mfa_secret' => $secret,
            'mfa_confirmed_at' => now(),
        ]);

        for ($signIn = 0; $signIn < 2; $signIn++) {
            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
                'remember' => true,
            ])->assertRedirect('/mfa-challenge');

            $this->assertGuest();
            $this->get('/dashboard')->assertRedirect('/login');
            $this->get('/mfa-challenge')->assertInertia(fn (Assert $page) => $page
                ->component('Auth/MfaChallenge')
                ->where('isSetup', false));

            $this->withSession(['auth.mfa.remember' => true])
                ->post('/mfa-challenge', ['code' => Totp::getCode($secret)])
                ->assertRedirect('/dashboard')
                ->assertCookieMissing(Auth::guard('web')->getRecallerName());

            $this->assertAuthenticatedAs($user);
            $this->assertSame($secret, $user->fresh()->mfa_secret);
            $this->post('/logout')->assertRedirect('/');
            $this->assertGuest();
            $this->assertNull(session('auth.mfa.user_id'));
        }
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->post('/logout');

        $this->assertGuest();
        $response->assertRedirect('/');
    }
}
