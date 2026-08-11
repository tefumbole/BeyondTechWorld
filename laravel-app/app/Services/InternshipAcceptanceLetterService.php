<?php

namespace App\Services;

use App\Application;
use App\Customer;
use App\GeneralSetting;
use App\Http\Controllers\LetterController;
use App\InternshipEnrolment;
use App\Letter;
use App\LetterCategory;
use App\LetterTemplate;
use App\Support\LetterPlaceholders;
use App\Support\LetterReference;
use App\Support\LetterSignature;
use App\Support\WhatsAppPhone;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class InternshipAcceptanceLetterService
{
    const TEMPLATE_NAME = 'Internship Acceptance Letter';
    const DEFAULT_PASSWORD = 'system';

    /**
     * Ensure the editable Letter Template exists (Letters → Templates).
     */
    public function ensureTemplate(): LetterTemplate
    {
        $existing = LetterTemplate::where('name', self::TEMPLATE_NAME)->first();
        if ($existing) {
            $this->syncTemplateDefaults($existing);

            return $existing;
        }

        $category = LetterCategory::firstOrCreate(
            ['name' => 'Internship'],
            ['is_active' => 1]
        );

        return LetterTemplate::create([
            'category_id' => $category->id,
            'name' => self::TEMPLATE_NAME,
            'header' => '',
            'subject' => 'Internship Admission and Welcome to the [system_name] Internship Programme',
            'body' => $this->defaultBodyHtml(),
            'footer' => '<p>Beyond Company Ltd · Internship Programme</p>',
            'is_active' => 1,
            'created_by' => Auth::id() ?: 1,
        ]);
    }

    /**
     * Keep required internship placeholders on the editable template.
     */
    protected function syncTemplateDefaults(LetterTemplate $template): void
    {
        $body = (string) $template->body;
        $dirty = false;

        if (stripos($body, '[password]') === false) {
            $body = $this->defaultBodyHtml();
            $dirty = true;
        } else {
            if (stripos($body, '[end_date]') === false && stripos($body, '[start_date]') !== false) {
                $body = str_ireplace(
                    'will run from <strong>[start_date]</strong>.',
                    'will run from <strong>[start_date]</strong> to <strong>[end_date]</strong>.',
                    $body
                );
                $body = str_ireplace(
                    'will run from <strong>[start_date]</strong>',
                    'will run from <strong>[start_date]</strong> to <strong>[end_date]</strong>',
                    $body
                );
                $dirty = true;
            }
            // Letter chrome already prints Dear / Sincerely — drop duplicates from body.
            if (preg_match('/^\s*<p>\s*Dear\b/i', $body)) {
                $body = preg_replace('/^\s*<p>\s*Dear\b.*?<\/p>\s*/is', '', $body, 1) ?: $body;
                $dirty = true;
            }
            if (preg_match('/<p>\s*Sincerely,?\s*<\/p>\s*$/i', $body)) {
                $body = preg_replace('/<p>\s*Sincerely,?\s*<\/p>\s*$/i', '', $body) ?: $body;
                $dirty = true;
            }
        }

        if ($dirty) {
            $template->body = $body;
        }

        $subject = trim((string) $template->subject);
        if ($subject === '' || (stripos($subject, '[program]') === false && stripos($subject, '[system_name]') === false)) {
            $template->subject = 'Internship Admission and Welcome to the [system_name] Internship Programme';
            $dirty = true;
        } elseif (stripos($subject, 'Subject:') === 0) {
            // PDF already prefixes "Subject:"
            $template->subject = trim(preg_replace('/^Subject:\s*/i', '', $subject));
            $dirty = true;
        }

        if ($dirty) {
            $template->save();
        }
    }

    public function defaultBodyHtml(): string
    {
        return <<<'HTML'
<p><em>[school]</em></p>
<p>Congratulations, and welcome to <strong>[system_name]</strong>!</p>
<p>We are delighted to confirm that you have been <strong>admitted</strong> into the <strong>[system_name] Internship Programme</strong> for <strong>[program]</strong>.</p>
<p>You were selected because we believe in your potential, passion for technology, and willingness to learn. During your internship, you will work alongside experienced professionals on real-world projects. We encourage you to be curious, professional, collaborative, and ready to contribute fresh ideas.</p>
<p><strong>Your supervisor(s)</strong> will be <strong>[supervisors]</strong>.</p>
<p><strong>Your internship period</strong> will run from <strong>[start_date]</strong> to <strong>[end_date]</strong>.</p>
<p><strong>Duration:</strong> [duration]. You are required to <strong>submit a task daily</strong>.</p>
<p><strong>Login credentials</strong><br>
Username: <strong>[phone_number]</strong> (or email <strong>[email]</strong>)<br>
Default password: <strong>[password]</strong><br>
After you log in, please change your password immediately. Then go to <strong>Timesheets</strong> and configure your working week.</p>
<p>This is more than an internship—it is an opportunity to learn, grow, solve meaningful problems, and build your future.</p>
<p>Welcome to the Beyond family. We look forward to achieving great things together!</p>
HTML;
    }

    /**
     * Create signed acceptance letters and send WhatsApp PDFs for selected applications.
     *
     * @param  array  $applicationIds
     * @return array{sent:int,skipped:int,errors:string[],template_id:int}
     */
    public function notifyApplications(array $applicationIds): array
    {
        $template = $this->ensureTemplate();
        $ids = array_values(array_unique(array_filter(array_map('strval', $applicationIds))));
        $result = ['sent' => 0, 'skipped' => 0, 'errors' => [], 'template_id' => (int) $template->id];

        if (empty($ids)) {
            $result['errors'][] = 'No interns selected.';

            return $result;
        }

        $applications = Application::with(['job', 'internshipProgram'])
            ->whereIn('id', $ids)
            ->get();

        $controller = app(LetterController::class);
        $actorId = Auth::id() ?: optional(LetterSignature::adminWithSignature())->id;

        foreach ($applications as $application) {
            try {
                $payload = $this->buildRecipientPayload($application);
                if (empty($payload['phone_number']) && empty($payload['email'])) {
                    $result['skipped']++;
                    $result['errors'][] = ($application->full_name ?: $application->id).': no phone or email.';
                    continue;
                }

                // Keep letter login credentials accurate.
                $this->resetInternPassword($application, self::DEFAULT_PASSWORD);

                if (empty($payload['phone_number'])) {
                    $result['skipped']++;
                    $result['errors'][] = ($application->full_name ?: $application->id).': WhatsApp phone required to send letter PDF.';
                    continue;
                }

                $letter = $this->createSignedLetter($template, $application, $payload, $actorId);
                $recipient = (object) $payload;
                $to = $payload['phone_number'];
                $sendMsg = $controller->sendPDF($letter, $recipient, $to);
                $sendFailed = is_string($sendMsg) && stripos($sendMsg, 'not sent') !== false;

                if ($sendFailed) {
                    $result['skipped']++;
                    $result['errors'][] = ($application->full_name ?: $application->id).': '.$sendMsg;
                    continue;
                }

                $letter->is_sent = 1;
                $letter->sent_by = $actorId;
                $letter->save();

                $result['sent']++;
            } catch (\Throwable $e) {
                Log::warning('Internship acceptance letter failed', [
                    'application_id' => $application->id,
                    'error' => $e->getMessage(),
                ]);
                $result['skipped']++;
                $result['errors'][] = ($application->full_name ?: $application->id).': '.$e->getMessage();
            }
        }

        return $result;
    }

    protected function createSignedLetter(LetterTemplate $template, Application $application, array $payload, $actorId): Letter
    {
        $admin = LetterSignature::adminWithSignature();
        $signer = $admin ?: Auth::user();
        $signerId = $signer ? $signer->id : $actorId;
        $sigFile = null;
        if ($signer && ! empty($signer->sign)) {
            $sigFile = LetterSignature::storeFromAccountFile($signer->sign, 'sign');
        }

        $person = array_merge($payload, [
            'id' => 'applicant:'.$application->id,
            'name' => $payload['name'],
            'phone' => $payload['phone_number'],
            'email' => $payload['email'],
            'address' => $payload['address'] ?? '',
            'role' => 'intern',
            'source' => 'applicant',
        ]);

        $recipientObj = (object) $payload;
        $subject = LetterPlaceholders::replace($template->subject, $recipientObj);
        $subject = trim(preg_replace('/^Subject:\s*/i', '', $subject));
        $body = LetterPlaceholders::replace($template->body, $recipientObj);
        $footer = LetterPlaceholders::replace($template->footer, $recipientObj);
        $header = LetterPlaceholders::replace($template->header, $recipientObj);

        return Letter::create([
            'category_id' => $template->category_id,
            'template_id' => $template->id,
            'reference' => LetterReference::next(),
            'name' => self::TEMPLATE_NAME.' — '.$application->full_name,
            'people_type' => 'directory',
            'to' => $person['id'],
            'cc' => null,
            'recipients_json' => json_encode([$person]),
            'cc_json' => null,
            'header' => $header,
            'subject' => $subject,
            'body' => $body,
            'footer' => $footer,
            'is_active' => 1,
            'is_edit' => 1,
            'is_approve' => 1,
            'is_sign' => 1,
            'is_sent' => 0,
            'is_rejected' => 0,
            'created_by' => $actorId,
            'edit_by' => $actorId,
            'approved_by' => $actorId,
            'signed_by' => $signerId,
            'sign_signature' => $sigFile,
            'sign_signed_at' => now(),
            'date_time' => now(),
        ]);
    }

    /**
     * @return array<string, string>
     */
    public function buildRecipientPayload(Application $application): array
    {
        $enrolment = $this->findEnrolment($application);
        $systemName = $this->systemName();
        $program = optional($enrolment ? $enrolment->program : null)->displayName()
            ?: optional($enrolment ? $enrolment->program : null)->name
            ?: optional($application->internshipProgram)->displayName()
            ?: optional($application->internshipProgram)->name
            ?: optional($application->job)->title
            ?: 'Internship Programme';

        $supervisors = $this->supervisorNames($enrolment);
        $days = $enrolment
            ? $enrolment->plannedDurationDays()
            : Application::normalizeInternshipDurationDays($application->internship_duration_days, 90);
        $duration = $days.' day'.($days === 1 ? '' : 's');

        $startCarbon = $enrolment && $enrolment->start_date
            ? \Carbon\Carbon::parse($enrolment->start_date)->startOfDay()
            : now()->startOfDay();
        $endCarbon = $startCarbon->copy()->addDays(max(1, $days) - 1);
        $startDate = $startCarbon->format('F j, Y');
        $endDate = $endCarbon->format('F j, Y');

        $rawPhone = $application->whatsapp_number ?: $application->phone;
        $phone = $rawPhone ? WhatsAppPhone::display($rawPhone) : '';

        return [
            'name' => $application->full_name ?: 'Intern',
            'school' => $application->school ?: '—',
            'phone_number' => $phone,
            'email' => (string) ($application->email ?: ''),
            'address' => (string) ($application->country ?: ''),
            'system_name' => $systemName,
            'program' => $program,
            'supervisors' => $supervisors,
            'start_date' => $startDate,
            'end_date' => $endDate,
            'duration' => $duration,
            'password' => self::DEFAULT_PASSWORD,
            'username' => $phone ?: (string) ($application->email ?: ''),
            // Also map into columns for older templates / CSV-style docs.
            'column1' => $systemName,
            'column2' => $program,
            'column3' => $supervisors,
            'column4' => $startDate.' – '.$endDate,
            'column5' => $duration,
            'column6' => self::DEFAULT_PASSWORD,
            'column7' => $application->school ?: '',
            'column8' => $endDate,
            'column9' => '',
            'column10' => '',
        ];
    }

    protected function findEnrolment(Application $application): ?InternshipEnrolment
    {
        $enrolment = InternshipEnrolment::with(['program', 'supervisor'])
            ->where('application_id', $application->id)
            ->orderByDesc('id')
            ->first();
        if ($enrolment) {
            return $enrolment;
        }

        $email = strtolower(trim((string) $application->email));
        if ($email === '') {
            return null;
        }
        $user = User::where('is_deleted', false)->whereRaw('LOWER(email) = ?', [$email])->first();
        if (! $user) {
            return null;
        }

        return InternshipEnrolment::with(['program', 'supervisor'])
            ->where('student_user_id', $user->id)
            ->whereIn('status', ['pending', 'active', 'paused'])
            ->orderByDesc('id')
            ->first();
    }

    protected function supervisorNames(?InternshipEnrolment $enrolment): string
    {
        if (! $enrolment) {
            return 'to be assigned';
        }

        $labels = [];
        $seen = [];

        $add = function ($name, $phone = null) use (&$labels, &$seen) {
            $name = trim((string) $name);
            if ($name === '') {
                return;
            }
            $key = strtolower($name);
            if (isset($seen[$key])) {
                return;
            }
            $seen[$key] = true;
            $phone = trim((string) $phone);
            if ($phone !== '') {
                $labels[] = $name.' ('.$phone.')';
            } else {
                $labels[] = $name;
            }
        };

        if ($enrolment->supervisor) {
            $add($enrolment->supervisor->name, $this->formatSupervisorPhone($enrolment->supervisor->phone ?? null));
        }
        foreach ($enrolment->supervisorRefs() as $ref) {
            if (strpos($ref, 'user:') === 0) {
                $u = User::find((int) substr($ref, 5));
                if ($u) {
                    $add($u->name, $this->formatSupervisorPhone($u->phone ?? null));
                }
            } elseif (strpos($ref, 'customer:') === 0) {
                $c = Customer::find((int) substr($ref, 9));
                if ($c) {
                    $add($c->name, $this->formatSupervisorPhone($c->phone_number ?? null));
                }
            }
        }

        return $labels ? implode(', ', $labels) : 'to be assigned';
    }

    protected function formatSupervisorPhone($raw): string
    {
        $raw = trim((string) $raw);
        if ($raw === '' || strtoupper($raw) === 'NAN') {
            return '';
        }

        try {
            return WhatsAppPhone::display($raw);
        } catch (\Throwable $e) {
            return $raw;
        }
    }

    protected function systemName(): string
    {
        try {
            $gs = GeneralSetting::first();
            if ($gs && ! empty($gs->site_title)) {
                return (string) $gs->site_title;
            }
        } catch (\Throwable $e) {
            // fall through
        }

        return 'Beyond Company Ltd';
    }

    protected function resetInternPassword(Application $application, string $plain): void
    {
        $user = app(ApplicationService::class)->ensureErpInternUser($application, $plain);
        if ($user) {
            $user->password = bcrypt($plain);
            $user->is_active = 1;
            $user->save();
        }
    }
}
