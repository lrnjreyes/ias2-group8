<?php

namespace Database\Seeders;

use App\Models\EmployeeRecord;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $admin = User::factory()->create([
            'name' => 'HR Administrator',
            'email' => 'admin@example.test',
            'role' => 'admin',
            'password' => 'DemoPassword!2026',
            'email_verified_at' => now(),
        ]);

        $employee = User::factory()->create([
            'name' => 'Sample Employee',
            'email' => 'employee@example.test',
            'role' => 'employee',
            'password' => 'DemoPassword!2026',
            'email_verified_at' => now(),
        ]);

        EmployeeRecord::create([
            'user_id' => $employee->id,
            'employee_number' => 'EMP-1001',
            'department' => 'Information Technology',
            'position' => 'Support Specialist',
            'hire_date' => '2024-06-03',
            'leave_balance' => 10,
        ]);

        EmployeeRecord::create([
            'user_id' => $admin->id,
            'employee_number' => 'HR-0001',
            'department' => 'Human Resources',
            'position' => 'HR Administrator',
            'hire_date' => '2023-01-16',
            'leave_balance' => 12,
        ]);
    }
}
