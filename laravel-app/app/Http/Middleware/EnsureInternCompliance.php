<?php

namespace App\Http\Middleware;

use App\Support\InternCompliance;
use Closure;
use Illuminate\Support\Facades\Auth;

class EnsureInternCompliance
{
    public function handle($request, Closure $next)
    {
        if (! Auth::guard('web')->check()) {
            return $next($request);
        }

        $user = Auth::guard('web')->user();
        if (! InternCompliance::appliesTo($user)) {
            return $next($request);
        }

        if ($this->isExemptPath($request)) {
            return $next($request);
        }

        if (! InternCompliance::workingWeekConfigured($user)) {
            if ($request->is('admin/timesheet/working-week') || $request->is('admin/timesheet/working-week/*')) {
                return $next($request);
            }

            return redirect()->route('timesheet.working-week')
                ->with('message', 'Please configure your working week before continuing your internship.');
        }

        $missing = InternCompliance::missingTimesheetDate($user);
        if ($missing) {
            if ($this->isTimesheetPath($request)) {
                return $next($request);
            }

            return redirect()->route('timesheet.fill', ['date' => $missing, 'intern' => 1])
                ->with('message', 'Please fill your timesheet for '.$missing.' before continuing. Interns log hours at the end of each working day.');
        }

        return $next($request);
    }

    protected function isExemptPath($request)
    {
        return $request->is('logout')
            || $request->is('portal/logout')
            || $request->is('otp/*')
            || $request->is('staff-set-password')
            || $request->is('staff-otp-login*');
    }

    protected function isTimesheetPath($request)
    {
        return $request->is('admin/timesheet/*')
            || $request->is('admin/timesheet');
    }
}
