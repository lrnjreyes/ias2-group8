<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Users', [
            'users' => User::with('employeeRecord')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate($this->rules());

        $user = DB::transaction(function () use ($validated): User {
            $user = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'role' => $validated['role'],
                'mfa_enabled' => true,
                'mfa_secret' => null,
                'mfa_confirmed_at' => null,
                'email_verified_at' => now(),
            ]);

            $user->employeeRecord()->create($this->employeeData($validated));

            return $user;
        });

        AuditLog::record($request, 'user.created', $user, [
            'email' => $user->email,
            'role' => $user->role,
            'employee_number' => $user->employeeRecord->employee_number,
        ]);

        return back()->with('status', 'User account created.');
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        $validated = $request->validate($this->rules($user, false));
        $before = [
            ...$user->only(['name', 'email', 'role']),
            ...$user->employeeRecord?->only(['employee_number', 'department', 'position', 'hire_date', 'leave_balance']) ?? [],
        ];

        DB::transaction(function () use ($user, $validated): void {
            $user->update([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'role' => $validated['role'],
            ]);

            $user->employeeRecord()->updateOrCreate([], $this->employeeData($validated));
        });

        $user->load('employeeRecord');
        $after = [
            ...$user->only(['name', 'email', 'role']),
            ...$user->employeeRecord->only(['employee_number', 'department', 'position', 'hire_date', 'leave_balance']),
        ];
        $changes = collect($after)
            ->filter(fn (mixed $value, string $field): bool => (string) ($before[$field] ?? '') !== (string) $value)
            ->mapWithKeys(fn (mixed $value, string $field): array => [
                $field => ['before' => $before[$field] ?? null, 'after' => $value],
            ])->all();

        if ($changes !== []) {
            AuditLog::record($request, 'user.updated', $user, ['changes' => $changes]);
        }

        return back()->with('status', 'User account updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own administrator account.']);
        }

        if ($user->role === 'admin' && User::where('role', 'admin')->count() <= 1) {
            throw ValidationException::withMessages(['user' => 'The last administrator account cannot be deleted.']);
        }

        $actorId = $request->user()->id;
        $metadata = ['email' => $user->email, 'role' => $user->role];
        AuditLog::record($request, 'user.deleted', $user, $metadata, $actorId);
        $user->delete();

        return back()->with('status', 'User account deleted.');
    }

    private function rules(?User $user = null, bool $requirePassword = true): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user?->id)],
            'password' => [$requirePassword ? 'required' : 'nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', Rule::in(['admin', 'employee'])],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique('employee_records', 'employee_number')->ignore($user?->employeeRecord?->id)],
            'department' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'hire_date' => ['required', 'date'],
            'leave_balance' => ['required', 'integer', 'min:0', 'max:365'],
        ];
    }

    private function employeeData(array $validated): array
    {
        return collect($validated)->only([
            'employee_number', 'department', 'position', 'hire_date', 'leave_balance',
        ])->all();
    }
}
