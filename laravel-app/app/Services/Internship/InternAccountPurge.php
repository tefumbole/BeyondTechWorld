<?php

namespace App\Services\Internship;

use App\Application;
use App\BeyondProfile;
use App\BeyondUser;
use App\InternshipDraftFile;
use App\InternshipEnrolment;
use App\InternshipGrade;
use App\InternshipSubmission;
use App\InternshipSubmissionFile;
use App\InternshipTaskAssignment;
use App\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Remove an intern (and their login) so they no longer appear in Supervisor,
 * Interns, People, or the portal.
 */
class InternAccountPurge
{
    public function purgeEnrolment(InternshipEnrolment $enrolment, User $actor)
    {
        $student = $enrolment->student;
        if ($student) {
            return $this->purgeUser($student, $actor);
        }

        $this->wipeEnrolment($enrolment);

        return 'placement';
    }

    public function purgeApplication(Application $application, User $actor)
    {
        $user = null;
        if ($application->user_id) {
            $user = User::find($application->user_id);
        }
        if (! $user && $application->email) {
            $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($application->email))])->first();
        }
        if ($user) {
            return $this->purgeUser($user, $actor);
        }

        $this->deleteApplicationRow($application);

        return 'application';
    }

    public function purgeUser(User $user, User $actor)
    {
        if ((int) $user->id === (int) $actor->id) {
            throw new RuntimeException('You cannot delete your own account from this screen.');
        }
        if ((int) $user->role_id <= 2) {
            throw new RuntimeException('Administrator accounts cannot be deleted here.');
        }

        $name = $user->name;

        DB::transaction(function () use ($user) {
            $enrolments = InternshipEnrolment::where('student_user_id', $user->id)->get();
            foreach ($enrolments as $enrolment) {
                $this->wipeEnrolment($enrolment);
            }

            if (Schema::hasTable('applications')) {
                $apps = Application::query()
                    ->where(function ($q) use ($user) {
                        if ($user->id) {
                            $q->where('user_id', $user->id);
                        }
                        if ($user->email) {
                            $q->orWhereRaw('LOWER(email) = ?', [strtolower(trim($user->email))]);
                        }
                    })
                    ->get();
                foreach ($apps as $app) {
                    $this->deleteApplicationRow($app);
                }
            }

            if (Schema::hasTable('be_timesheet_entries') && Schema::hasColumn('be_timesheet_entries', 'user_id')) {
                DB::table('be_timesheet_entries')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('be_working_week')) {
                DB::table('be_working_week')->where('user_id', $user->id)->delete();
            }
            if (Schema::hasTable('internship_notification_logs')) {
                DB::table('internship_notification_logs')->where('user_id', $user->id)->delete();
            }

            $this->wipeBeyondAccount($user);

            // SalePro uses users.role_id + role_has_permissions. Spatie's
            // HasRoles trait still tries to detach model_has_roles on
            // $user->delete(), and that table is not in this database.
            $this->forgetSpatieAssignments($user->id);
            DB::table('users')->where('id', $user->id)->delete();
        });

        return $name;
    }

    protected function wipeEnrolment(InternshipEnrolment $enrolment)
    {
        $assignments = InternshipTaskAssignment::where('enrolment_id', $enrolment->id)->get();
        foreach ($assignments as $assignment) {
            if (Schema::hasTable('internship_draft_files')) {
                $drafts = InternshipDraftFile::where('assignment_id', $assignment->id)->get();
                foreach ($drafts as $draft) {
                    try {
                        Storage::disk($draft->disk ?: 'local')->delete($draft->path);
                    } catch (\Throwable $e) {
                    }
                    $draft->delete();
                }
            }
            $submissions = InternshipSubmission::where('assignment_id', $assignment->id)->get();
            foreach ($submissions as $submission) {
                $files = InternshipSubmissionFile::where('submission_id', $submission->id)->get();
                foreach ($files as $file) {
                    try {
                        Storage::disk($file->disk ?: 'local')->delete($file->path);
                    } catch (\Throwable $e) {
                    }
                    $file->delete();
                }
                InternshipGrade::where('submission_id', $submission->id)->delete();
                $submission->delete();
            }
            $assignment->delete();
        }

        if (Schema::hasTable('internship_activity_logs')) {
            DB::table('internship_activity_logs')->where('enrolment_id', $enrolment->id)->delete();
        }

        $enrolment->delete();
    }

    protected function deleteApplicationRow(Application $application)
    {
        $jobId = $application->job_id;
        $application->delete();
        if ($jobId && Schema::hasTable('job_postings')) {
            try {
                DB::table('job_postings')->where('id', $jobId)->where('current_applicants', '>', 0)->decrement('current_applicants');
            } catch (\Throwable $e) {
            }
        }
    }

    protected function forgetSpatieAssignments($userId)
    {
        foreach (['model_has_roles', 'model_has_permissions'] as $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            try {
                DB::table($table)
                    ->where('model_id', $userId)
                    ->where('model_type', User::class)
                    ->delete();
            } catch (\Throwable $e) {
            }
        }
    }

    protected function wipeBeyondAccount(User $user)
    {
        if (! Schema::hasTable('be_users')) {
            return;
        }

        $query = BeyondUser::query();
        $query->where(function ($q) use ($user) {
            if ($user->email) {
                $q->orWhereRaw('LOWER(email) = ?', [strtolower(trim($user->email))]);
            }
            $digits = preg_replace('/\D+/', '', (string) $user->phone);
            if (strlen($digits) >= 8) {
                $tail = substr($digits, -9);
                $q->orWhereRaw(
                    "RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(phone,''), '+', ''), ' ', ''), '-', ''), '(', ''), 9) = ?",
                    [$tail]
                );
            }
        });

        foreach ($query->get() as $beyond) {
            if (Schema::hasTable('be_profiles')) {
                BeyondProfile::where('id', $beyond->id)->delete();
            }
            $beyond->delete();
        }
    }
}
