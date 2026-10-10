<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $record = $request->user()->employeeRecord;

        return Inertia::render('Dashboard', [
            'record' => $record,
            'isAdmin' => $request->user()->role === 'admin',
        ]);
    }
}
