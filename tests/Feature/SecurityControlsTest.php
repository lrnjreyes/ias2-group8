<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\EmployeeRecord;
use App\Models\User;
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
