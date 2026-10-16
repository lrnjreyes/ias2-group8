<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_employee_cannot_manage_users(): void
    {
        $employee = User::factory()->create(['role' => 'employee']);

        $this->actingAs($employee)->get('/admin/users')->assertForbidden();
        $this->actingAs($employee)->post('/admin/users', [])->assertForbidden();
    }

    public function test_admin_can_create_update_and_delete_a_user_with_audit_logs(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $data = [
            'name' => 'New Employee', 'email' => 'new.employee@example.test',
            'password' => 'Temporary!2026', 'password_confirmation' => 'Temporary!2026',
            'role' => 'employee', 'employee_number' => 'EMP-2001',
            'department' => 'IT', 'position' => 'Analyst', 'hire_date' => '2026-10-17',
            'leave_balance' => 10,
        ];

        $this->actingAs($admin)->post('/admin/users', $data)->assertRedirect();
        $user = User::where('email', $data['email'])->firstOrFail();
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.created', 'subject_id' => $user->id]);

        $this->actingAs($admin)->patch("/admin/users/{$user->id}", [
            ...$data, 'name' => 'Updated Employee', 'password' => null,
            'password_confirmation' => null,
        ])->assertRedirect();
        $this->assertSame('Updated Employee', $user->fresh()->name);
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.updated', 'subject_id' => $user->id]);

        $this->actingAs($admin)->delete("/admin/users/{$user->id}")->assertRedirect();
        $this->assertNull($user->fresh());
        $this->assertDatabaseHas('audit_logs', ['event' => 'user.deleted', 'subject_id' => $user->id]);
    }

    public function test_admin_cannot_delete_their_own_account(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);

        $this->actingAs($admin)->delete("/admin/users/{$admin->id}")
            ->assertSessionHasErrors('user');

        $this->assertNotNull($admin->fresh());
    }
}
