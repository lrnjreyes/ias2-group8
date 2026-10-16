<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmployeeRecord;
use App\Models\User;
use App\Support\Totp;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityControlsTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_access_admin_pages(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);
        $this->actingAs($employee)->get('/admin/employees')->assertForbidden();
        $this->actingAs($employee)->get('/admin/audit-logs')->assertForbidden();
    }

    public function test_admin_can_access_admin_pages(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/admin/employees')->assertOk();
        $this->actingAs($admin)->get('/admin/audit-logs')->assertOk();
    }

    public function test_employee_update_is_audited(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $record = EmployeeRecord::create(['user_id' => $employee->id, 'employee_number' => 'EMP-9001', 'department' => 'Support', 'position' => 'Analyst', 'hire_date' => '2025-01-01', 'leave_balance' => 10]);

        $this->actingAs($admin)->patch("/admin/employees/{$record->id}", ['department' => 'Security', 'position' => 'Security Analyst', 'leave_balance' => 9])->assertRedirect();

        $this->assertDatabaseHas('audit_logs', ['actor_id' => $admin->id, 'event' => 'employee_record.updated', 'subject_id' => $record->id]);

        $log = AuditLog::where('event', 'employee_record.updated')->firstOrFail();
        $this->assertSame('EMP-9001', $log->metadata['employee_number']);
        $this->assertSame('Support', $log->metadata['changes']['department']['before']);
        $this->assertSame('Security', $log->metadata['changes']['department']['after']);
        $this->assertSame(10, $log->metadata['changes']['leave_balance']['before']);
        $this->assertSame(9, $log->metadata['changes']['leave_balance']['after']);
    }

    public function test_saving_an_unchanged_employee_record_does_not_create_an_update_log(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $employee = User::factory()->create(['role' => 'employee']);
        $record = EmployeeRecord::create(['user_id' => $employee->id, 'employee_number' => 'EMP-9002', 'department' => 'Support', 'position' => 'Analyst', 'hire_date' => '2025-01-01', 'leave_balance' => 10]);

        $this->actingAs($admin)->patch("/admin/employees/{$record->id}", [
            'department' => 'Support',
            'position' => 'Analyst',
            'leave_balance' => 10,
        ])->assertRedirect();

        $this->assertDatabaseMissing('audit_logs', [
            'event' => 'employee_record.updated',
            'subject_id' => $record->id,
        ]);
    }

    public function test_login_is_rate_limited_after_five_failures(): void
    {
        RateLimiter::clear('employee@example.test|127.0.0.1');
        User::factory()->create(['email' => 'employee@example.test']);
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $this->post('/login', ['email' => 'employee@example.test', 'password' => 'wrong-password']);
        }

        $response = $this->post('/login', ['email' => 'employee@example.test', 'password' => 'password']);
        $response->assertSessionHasErrors('email');
        $this->assertDatabaseHas('audit_logs', ['event' => 'auth.login.locked']);
    }

    public function test_login_with_mfa_enabled_redirects_to_mfa_challenge_and_records_audit_event(): void
    {
        $user = User::factory()->create([
            'email' => 'mfa-test@example.test',
            'password' => bcrypt('ValidPassword!2026'),
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->post('/login', [
            'email' => 'mfa-test@example.test',
            'password' => 'ValidPassword!2026',
        ]);

        $response->assertRedirect('/mfa-challenge');
        $this->assertGuest();
        $this->assertEquals($user->id, session('auth.mfa.user_id'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.mfa.challenged',
        ]);
    }

    public function test_invalid_mfa_code_is_rejected_and_audited(): void
    {
        $user = User::factory()->create([
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $response = $this->withSession(['auth.mfa.user_id' => $user->id])
            ->post('/mfa-challenge', ['code' => '000000']);

        $response->assertSessionHasErrors('code');
        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.mfa.failed',
        ]);
    }

    public function test_valid_mfa_code_authenticates_user_and_records_audit_events(): void
    {
        $user = User::factory()->create([
            'mfa_enabled' => true,
            'mfa_secret' => 'JBSWY3DPEHPK3PXP',
        ]);

        $validCode = Totp::getCode('JBSWY3DPEHPK3PXP');

        $response = $this->withSession(['auth.mfa.user_id' => $user->id])
            ->post('/mfa-challenge', ['code' => $validCode]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticatedAs($user);
        $this->assertNull(session('auth.mfa.user_id'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.mfa.succeeded',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.login.succeeded',
        ]);
    }

    public function test_mfa_cancel_clears_mfa_session_and_redirects_to_login(): void
    {
        $user = User::factory()->create();

        $response = $this->withSession(['auth.mfa.user_id' => $user->id])
            ->post('/mfa-cancel');

        $response->assertRedirect('/login');
        $this->assertNull(session('auth.mfa.user_id'));
    }

    public function test_logout_invalidates_authenticated_session(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/dashboard')->assertRedirect('/login');
        $this->assertTrue(AuditLog::where('event', 'auth.logout')->exists());
    }

    public function test_timeout_endpoint_records_expired_session_and_logs_user_out(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post('/session/timeout')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.session.expired',
        ]);
    }

    public function test_stale_authenticated_session_is_ended_and_audited(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withSession(['last_activity_at' => now()->subMinutes(16)->timestamp])
            ->get('/dashboard')
            ->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $user->id,
            'event' => 'auth.session.expired',
        ]);
    }
}
