<?php

namespace App\Services;

use App\GeneralSetting;
use App\Http\Controllers\LetterController;
use App\Letter;
use App\LetterCategory;
use App\LetterTemplate;
use App\StaffPermission;
use App\Support\LetterPlaceholders;
use App\Support\LetterReference;
use App\Support\LetterSignature;
use App\Support\WhatsAppPhone;
use App\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class StaffPermissionLetterService
{
    const APPROVED_TEMPLATE = 'Permission Approved Letter';
    const DENIED_TEMPLATE = 'Permission Denied Letter';

    /**
     * Create a signed Letters record and queue WhatsApp/email PDF delivery.
     *
     * @return array{ok:bool,letter:?Letter,warnings:string[],error:?string}
     */
    public function issueDecisionLetter(StaffPermission $permission, User $actor, $approved)
    {
        $result = ['ok' => false, 'letter' => null, 'warnings' => [], 'error' => null];

        try {
            $template = $this->ensureTemplate($approved);
            $letter = $this->createSignedLetter($template, $permission, $actor, $approved);
            $letter->is_sent = 1;
            $letter->sent_by = $actor->id;
            $letter->save();

            $controller = app(LetterController::class);
            $controller->queueDeliveryAfterResponse($letter, $letter->id, null);

            $permission->letter_id = $letter->id;
            $permission->save();

            $result['ok'] = true;
            $result['letter'] = $letter;
            $result['warnings'] = $this->missingStampWarnings($actor);
        } catch (\Throwable $e) {
            Log::warning('Permission decision letter failed', [
                'permission_id' => $permission->id,
                'error' => $e->getMessage(),
            ]);
            $result['error'] = $e->getMessage();
        }

        return $result;
    }

    public function ensureTemplate($approved)
    {
        $name = $approved ? self::APPROVED_TEMPLATE : self::DENIED_TEMPLATE;
        $existing = LetterTemplate::where('name', $name)->first();
        if ($existing) {
            $this->syncTemplateDefaults($existing, $approved);

            return $existing;
        }

        $category = LetterCategory::firstOrCreate(
            ['name' => 'Permissions'],
            ['is_active' => 1]
        );

        return LetterTemplate::create([
            'category_id' => $category->id,
            'name' => $name,
            'header' => '',
            'subject' => $approved
                ? 'Permission Approved... [Column3]'
                : 'Permission Denied... [Column3]',
            'body' => $approved ? $this->approvedBodyHtml() : $this->deniedBodyHtml(),
            'footer' => '',
            'is_active' => 1,
            'created_by' => Auth::id() ?: 1,
        ]);
    }

    protected function syncTemplateDefaults(LetterTemplate $template, $approved)
    {
        $body = (string) $template->body;
        $dirty = false;
        if (stripos($body, '[Column4]') === false || stripos($body, 'Subject:') === false) {
            $template->body = $approved ? $this->approvedBodyHtml() : $this->deniedBodyHtml();
            $dirty = true;
        }
        $want = $approved ? 'Permission Approved... [Column3]' : 'Permission Denied... [Column3]';
        if (trim((string) $template->subject) === '' || stripos((string) $template->subject, '[Column3]') === false) {
            $template->subject = $want;
            $dirty = true;
        }
        if ($dirty) {
            $template->save();
        }
    }

    public function approvedBodyHtml()
    {
        return <<<'HTML'
<p>This letter confirms that your permission request has been <strong>approved</strong>.</p>
<p><strong>Subject:</strong> [Column3]</p>
<p><strong>Period:</strong> [start_date] to [end_date]</p>
<p><strong>Role:</strong> [program]</p>
<p><strong>Reference:</strong> [Column1]</p>
<p><strong>Explanation:</strong> [Column4]</p>
<p>[Column2]</p>
HTML;
    }

    public function deniedBodyHtml()
    {
        return <<<'HTML'
<p>This letter confirms that your permission request has been <strong>denied</strong>.</p>
<p><strong>Subject:</strong> [Column3]</p>
<p><strong>Period requested:</strong> [start_date] to [end_date]</p>
<p><strong>Role:</strong> [program]</p>
<p><strong>Reference:</strong> [Column1]</p>
<p><strong>Explanation:</strong> [Column4]</p>
<p>[Column2]</p>
HTML;
    }

    /**
     * Prefill the approval footer from the logged-in user account.
     */
    public static function defaultFooter(User $user)
    {
        $lines = [];
        $name = trim((string) $user->name);
        $company = trim((string) ($user->company_name ?? ''));
        if ($name !== '') {
            $lines[] = $name;
        }
        if ($company !== '') {
            $lines[] = $company;
        }

        return implode("\n", $lines);
    }

    protected function createSignedLetter(LetterTemplate $template, StaffPermission $permission, User $actor, $approved)
    {
        $payload = $this->buildRecipientPayload($permission);
        $person = array_merge($payload, [
            'id' => 'permission:'.$permission->id,
            'name' => $payload['name'],
            'phone' => $payload['phone_number'],
            'email' => $payload['email'],
            'address' => '',
            'role' => $permission->company_role,
            'source' => 'staff_permission',
        ]);

        $recipientObj = (object) $payload;
        $topic = $this->letterTopic($permission);
        $subject = $approved
            ? 'Permission Approved... '.$topic
            : 'Permission Denied... '.$topic;
        $subject = trim(preg_replace('/^Subject:\s*/i', '', $subject));

        $body = LetterPlaceholders::replace($template->body, $recipientObj);
        $header = LetterPlaceholders::replace($template->header, $recipientObj);
        $footer = $this->footerHtml($permission->letter_footer ?: self::defaultFooter($actor));

        $stamps = $this->affixActorStamps($actor);

        return Letter::create([
            'category_id' => $template->category_id,
            'template_id' => $template->id,
            'reference' => LetterReference::next(),
            'name' => ($approved ? 'Permission Approved' : 'Permission Denied').' — '.$permission->full_name,
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
            'created_by' => $actor->id,
            'edit_by' => $actor->id,
            'approved_by' => $actor->id,
            'signed_by' => $actor->id,
            'edit_signature' => $stamps['edit'],
            'approve_signature' => $stamps['approve'],
            'sign_signature' => $stamps['sign'],
            'edit_signed_at' => now(),
            'approve_signed_at' => now(),
            'sign_signed_at' => now(),
            'date_time' => now(),
        ]);
    }

    /**
     * @return array{edit:?string,approve:?string,sign:?string}
     */
    protected function affixActorStamps(User $actor)
    {
        $edit = ! empty($actor->stemp) ? LetterSignature::storeFromAccountFile($actor->stemp, 'edit') : null;
        $approve = ! empty($actor->approve) ? LetterSignature::storeFromAccountFile($actor->approve, 'approve') : null;
        $sign = ! empty($actor->sign) ? LetterSignature::storeFromAccountFile($actor->sign, 'sign') : null;

        return [
            'edit' => $edit,
            'approve' => $approve,
            'sign' => $sign,
        ];
    }

    /**
     * @return string[]
     */
    protected function missingStampWarnings(User $actor)
    {
        $missing = [];
        if (empty($actor->stemp)) {
            $missing[] = 'Edit';
        }
        if (empty($actor->approve)) {
            $missing[] = 'Approve';
        }
        if (empty($actor->sign)) {
            $missing[] = 'Sign';
        }

        if (! $missing) {
            return [];
        }

        return ['Letter sent without these account stamps: '.implode(', ', $missing).'. Add them under your user profile.'];
    }

    protected function footerHtml($text)
    {
        $text = trim((string) $text);
        if ($text === '') {
            return '';
        }
        $lines = preg_split("/\r\n|\r|\n/", $text);
        $safe = [];
        foreach ($lines as $line) {
            $safe[] = e($line);
        }

        return '<p>'.implode('<br>', $safe).'</p>';
    }

    /**
     * @return array<string, string>
     */
    protected function buildRecipientPayload(StaffPermission $permission)
    {
        $from = $permission->from_at ? $permission->from_at->format('l, F j, Y \a\t g:i A') : '—';
        $to = $permission->to_at ? $permission->to_at->format('l, F j, Y \a\t g:i A') : '—';
        $phone = $permission->phone ? WhatsAppPhone::display($permission->phone) : '';
        $instructions = trim((string) $permission->instructions);
        $reason = trim((string) $permission->reason);
        $topic = $this->letterTopic($permission);
        $setting = GeneralSetting::first();

        return [
            'name' => $permission->full_name,
            'phone_number' => $phone,
            'email' => (string) $permission->email,
            'address' => '',
            'system_name' => $setting ? (string) $setting->site_title : 'Beyond Enterprise',
            'program' => (string) $permission->company_role,
            'start_date' => $from,
            'end_date' => $to,
            'duration' => $from.' — '.$to,
            'column1' => (string) $permission->reference_number,
            'column2' => $instructions,
            'column3' => $topic,
            'column4' => $reason,
        ];
    }

    protected function letterTopic(StaffPermission $permission)
    {
        $topic = trim((string) $permission->subject);
        if ($topic !== '') {
            return $topic;
        }
        $reason = trim((string) $permission->reason);

        return $reason !== '' ? $reason : 'Permission';
    }
}
