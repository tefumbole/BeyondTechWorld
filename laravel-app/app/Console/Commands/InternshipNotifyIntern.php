<?php

namespace App\Console\Commands;

use App\Application;
use App\Customer;
use App\InternshipEnrolment;
use App\InternshipTaskAssignment;
use App\Services\Internship\InternshipProgramService;
use App\Services\InternshipAcceptanceLetterService;
use App\Support\WhatsAppPhone;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class InternshipNotifyIntern extends Command
{
    protected $signature = 'internship:notify-intern
        {email : Intern email}
        {--phone= : Replacement WhatsApp number}
        {--accept-placement : Send the internship acceptance letter}
        {--resend-tasks : Resend every open released task}';

    protected $description = 'Update an intern WhatsApp number and send placement / task messages to that number only';

    public function handle(InternshipProgramService $program)
    {
        $email = strtolower(trim((string) $this->argument('email')));
        $application = Application::whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $application) {
            $this->error('No application for '.$email);

            return 1;
        }

        $phoneOpt = trim((string) $this->option('phone'));
        if ($phoneOpt !== '') {
            $new = WhatsAppPhone::sanitizeForStorage($phoneOpt);
            $this->syncInternPhone($application, $new);
            $application->refresh();
            $this->info('WhatsApp set to '.$new.' for '.$application->full_name);
        }

        $admin = User::where('is_deleted', false)->where('id', 1)->first()
            ?: User::where('is_deleted', false)->where('role_id', 1)->where('is_active', 1)->first();
        if ($admin) {
            Auth::login($admin);
        }

        if ($this->option('accept-placement')) {
            $result = app(InternshipAcceptanceLetterService::class)
                ->notifyApplications([$application->id]);
            $this->info('Acceptance letter sent='.$result['sent'].' skipped='.$result['skipped']);
            if (! empty($result['errors'])) {
                $this->warn(implode(' ', $result['errors']));
            }
        }

        if ($this->option('resend-tasks')) {
            $enrolment = InternshipEnrolment::where('application_id', $application->id)
                ->orderByDesc('id')
                ->first();
            if (! $enrolment) {
                $user = User::where('is_deleted', false)->whereRaw('LOWER(email) = ?', [$email])->first();
                if ($user) {
                    $enrolment = InternshipEnrolment::where('student_user_id', $user->id)
                        ->orderByDesc('id')
                        ->first();
                }
            }
            if (! $enrolment) {
                $this->error('No internship enrolment for '.$email);

                return 1;
            }

            $assignments = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                ->whereNotNull('released_at')
                ->whereIn('status', ['available', 'in_progress', 'revision_required', 'submitted'])
                ->orderBy('id')
                ->get();

            if ($assignments->isEmpty()) {
                $ok = $program->tryReleaseNext($enrolment->fresh(['student', 'program']), now(), true, true);
                $this->info($ok ? 'Released first task.' : 'Could not release first task.');
                $enrolment->refresh();
                $assignments = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)
                    ->whereNotNull('released_at')
                    ->whereIn('status', ['available', 'in_progress', 'revision_required', 'submitted'])
                    ->orderBy('id')
                    ->get();
            }

            foreach ($assignments as $assignment) {
                $result = $program->resendTaskReleased($assignment, false);
                $phone = optional($enrolment->fresh('student')->student)->phone;
                if (! empty($result['success'])) {
                    $this->info('Task #'.$assignment->progression_day.' sent to '.$phone);
                } else {
                    $this->error('Task #'.$assignment->progression_day.' failed: '.($result['error'] ?? 'unknown'));
                }
            }
        }

        return 0;
    }

    protected function syncInternPhone(Application $application, $new)
    {
        $oldDigits = ['237675321739', '675321739', '+237675321739'];

        $application->phone = $new;
        $application->whatsapp_number = $new;
        $application->save();

        $email = strtolower(trim((string) $application->email));
        $users = User::where('is_deleted', false)
            ->where(function ($q) use ($email, $application) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
                if ($application->full_name) {
                    $q->orWhere('name', $application->full_name);
                }
            })
            ->get();

        foreach ($users as $user) {
            $user->phone = $new;
            if (in_array((string) $user->additional_phone, $oldDigits, true)
                || (string) $user->additional_phone === '237675321739') {
                $user->additional_phone = null;
            }
            $username = preg_replace('/\D+/', '', (string) $user->username);
            if (in_array($username, ['237675321739', '675321739'], true)) {
                $user->username = $new;
            }
            $user->save();
        }

        if (Schema::hasTable('customers')) {
            Customer::where(function ($q) use ($email, $application) {
                $q->whereRaw('LOWER(email) = ?', [$email]);
                if ($application->full_name) {
                    $q->orWhere('name', $application->full_name);
                }
            })->update(['phone_number' => $new]);
        }
    }
}
