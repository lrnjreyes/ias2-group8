<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ImplementationLogEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_password_lockout_blocks_sixth_request_and_expires_after_sixty_seconds(): void
    {
        $user = User::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/login', ['email' => $user->email, 'password' => 'wrong-password'])
                ->assertSessionHasErrors('email');
        }
        $this->assertSame(5, AuditLog::where('event', 'auth.login.failed')->count());
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.locked']);
        $this->travel(61)->seconds();
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/mfa-challenge');
        $this->assertGuest();
    }

    public function test_mfa_lockout_blocks_valid_code_then_allows_it_after_expiry(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create(['mfa_enabled' => true, 'mfa_secret' => $secret, 'mfa_confirmed_at' => now()]);
        $invalid = '000000';
        while (Totp::verify($secret, $invalid)) {
            $invalid = str_pad((string) ((int) $invalid + 1), 6, '0', STR_PAD_LEFT);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->withSession(['auth.mfa.user_id' => $user->id])
                ->post('/mfa-challenge', ['code' => $invalid])->assertSessionHasErrors('code');
        }
        $this->assertSame(5, AuditLog::where('event', 'auth.mfa.failed')->count());
        $this->post('/mfa-challenge', ['code' => Totp::getCode($secret)])
            ->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.mfa.locked', 'actor_id' => $user->id]);
        $this->travel(61)->seconds();
        $this->post('/mfa-challenge', ['code' => Totp::getCode($secret)])
            ->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
    }

    public function test_confirmed_account_still_discloses_enrollment_secret_before_mfa_verification(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create(['mfa_enabled' => true, 'mfa_secret' => $secret, 'mfa_confirmed_at' => now()]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password'])
            ->assertRedirect('/mfa-challenge');
        $this->get('/mfa-challenge')->assertInertia(fn (Assert $page) => $page
            ->component('Auth/MfaChallenge')
            ->where('isSetup', false)
            ->where('secret', $secret)
            ->where('otpAuthUri', fn ($uri) => str_contains($uri, 'secret='.$secret)));
        $this->assertGuest();
    }

    public function test_audit_metadata_records_context_without_password_or_otp(): void
    {
        $secret = Totp::generateSecret();
        $user = User::factory()->create(['mfa_enabled' => true, 'mfa_secret' => $secret]);
        $invalid = '000000';
        while (Totp::verify($secret, $invalid)) {
            $invalid = str_pad((string) ((int) $invalid + 1), 6, '0', STR_PAD_LEFT);
        }
        $this->withSession(['auth.mfa.user_id' => $user->id])
            ->withHeaders(['User-Agent' => 'ImplementationLogEvidence/1.0'])
            ->post('/mfa-challenge', ['code' => $invalid]);
        $log = AuditLog::where('event', 'auth.mfa.failed')->firstOrFail();
        $this->assertSame($user->id, $log->actor_id);
        $this->assertSame('127.0.0.1', $log->ip_address);
        $this->assertSame('ImplementationLogEvidence/1.0', $log->user_agent);
        $this->assertNotNull($log->created_at);
        $this->assertSame(['attempted_length' => 6], $log->metadata);
    }
}
