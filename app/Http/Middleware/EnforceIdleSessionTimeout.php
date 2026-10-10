<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnforceIdleSessionTimeout
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $next($request);
        }

        $now = now()->timestamp;
        $lastActivity = (int) $request->session()->get('last_activity_at', $now);
        $idleLimit = (int) config('session.idle_timeout', 15) * 60;

        if ($now - $lastActivity >= $idleLimit) {
            AuditLog::record($request, 'auth.session.expired', metadata: [
                'idle_minutes' => (int) config('session.idle_timeout', 15),
            ]);

            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('status', 'Your session expired after 15 minutes of inactivity.');
        }

        $request->session()->put('last_activity_at', $now);

        return $next($request);
    }
}
