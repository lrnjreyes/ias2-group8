<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\EmployeeRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class EmployeeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/Employees', [
            'employees' => EmployeeRecord::with('user:id,name,email,role')->orderBy('employee_number')->get(),
        ]);
    }

    public function update(Request $request, EmployeeRecord $employee): RedirectResponse
    {
        $validated = $request->validate([
            'department' => ['required', 'string', 'max:100'],
            'position' => ['required', 'string', 'max:100'],
            'leave_balance' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        $employee->fill($validated);

        $changes = collect($employee->getDirty())
            ->mapWithKeys(fn (mixed $after, string $field): array => [
                $field => [
                    'before' => $employee->getOriginal($field),
                    'after' => $after,
                ],
            ])
            ->all();

        if ($changes !== []) {
            $employee->save();

            AuditLog::record($request, 'employee_record.updated', $employee, [
                'employee_number' => $employee->employee_number,
                'changes' => $changes,
            ]);
        }

        return back()->with('status', 'Employee record updated.');
    }
}
