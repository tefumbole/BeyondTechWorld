<?php

namespace App\Services\Antivirus;

use App\Antivirus\AntivirusActivity;
use App\Antivirus\AntivirusApprover;
use App\Antivirus\AntivirusHandoff;
use App\Antivirus\AntivirusNotice;
use App\Antivirus\AntivirusRequest;
use App\Antivirus\AntivirusRuleException;
use App\Antivirus\AntivirusSetting;
use App\Cloud\CloudTenant;
use App\GeneralSetting;
use App\Services\Cloud\CloudTenantContext;
use App\Support\AntivirusCatalog;
use App\Support\WhatsAppPhone;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

class AntivirusRequestService
{
    /** @var AntivirusNotifier */
    protected $notifier;

    /** @var array<int> */
    protected $queue = [];

    public function __construct(AntivirusNotifier $notifier = null)
    {
        $this->notifier = $notifier ?: new AntivirusNotifier();
    }

    public function settings()
    {
        $row = AntivirusSetting::query()->first();
        if ($row) {
            return $row;
        }
        $row = new AntivirusSetting();
        $row->campaign_year = 2027;
        $row->chief_role = AntivirusCatalog::CHIEF_ROLE;
        $row->save();

        return $row;
    }

    public function saveSettings($year, $chiefName, $chiefPhone)
    {
        $year = (int) $year;
        if ($year < 2024 || $year > 2100) {
            throw new AntivirusRuleException('campaign_year', 'Enter the campaign year, for example 2027.');
        }
        $name = $this->cleanName($chiefName);
        $phone = trim((string) $chiefPhone);
        if ($phone !== '') {
            $phone = $this->phone($phone, 'chief_phone');
            if ($name === '') {
                throw new AntivirusRuleException('chief_name', 'Enter the Chief’s full name.');
            }
        }
        $row = $this->settings();
        $row->campaign_year = $year;
        $row->chief_name = $name !== '' ? $name : null;
        $row->chief_phone = $phone !== '' ? $phone : null;
        $row->chief_role = AntivirusCatalog::CHIEF_ROLE;
        $row->save();

        return $row;
    }

    public function beginDraft($phone, $name, $source)
    {
        $phone = $this->phone($phone);
        $name = $this->cleanName($name);
        if ($name === '') {
            throw new AntivirusRuleException('name', 'Enter your full name.');
        }
        if (! in_array($source, ['campay', 'pawapay', 'manual'], true)) {
            $source = 'manual';
        }
        $draft = new AntivirusRequest();
        $draft->status = 'draft';
        $draft->payment_status = 'none';
        $draft->receipt_status = 'none';
        $draft->requester_phone = $phone;
        $draft->requester_name = $name;
        $draft->requester_name_source = $source;
        $draft->year = (int) $this->settings()->campaign_year ?: 2027;
        $draft->phone_verified_at = now();
        $draft->save();
        $this->activity($draft, 'Identity verified', $name);

        return $draft;
    }

    public function saveRole(AntivirusRequest $request, $role, $other)
    {
        $this->draftOnly($request);
        if (! isset(AntivirusCatalog::requesterRoles()[$role])) {
            throw new AntivirusRuleException('role', 'Choose your role.');
        }
        $other = trim(strip_tags((string) $other));
        if ($role === 'other' && $other === '') {
            throw new AntivirusRuleException('role_other', 'Describe your role.');
        }
        $request->requester_role = $role;
        $request->requester_role_other = $role === 'other' ? mb_substr($other, 0, 191) : null;
        $this->forgetSignatureIfStale($request);
        $request->save();

        return $request;
    }

    public function saveStation(AntivirusRequest $request, $region, $station, $quantity)
    {
        $this->draftOnly($request);
        if (! in_array($region, AntivirusCatalog::regions(), true)) {
            throw new AntivirusRuleException('region', 'Choose a region.');
        }
        $station = trim(preg_replace('/\s+/', ' ', strip_tags((string) $station)));
        if (mb_strlen($station) < 2) {
            throw new AntivirusRuleException('station', 'Enter the station or hospital name.');
        }
        $qtyRaw = trim((string) $quantity);
        if (! preg_match('/^\d+$/', $qtyRaw) || (int) $qtyRaw < 1 || (int) $qtyRaw > 100000) {
            throw new AntivirusRuleException('quantity', 'Enter the number of licences as a whole number.');
        }
        $request->region = $region;
        $request->station = mb_substr($station, 0, 191);
        $request->quantity = (int) $qtyRaw;
        $request->year = (int) $this->settings()->campaign_year ?: 2027;
        $this->forgetSignatureIfStale($request);
        $request->save();

        return $request;
    }

    public function saveSelfie(AntivirusRequest $request, $file)
    {
        $this->draftOnly($request);
        if (! $file || ! $file->isValid()) {
            throw new AntivirusRuleException('selfie', 'Take a selfie before continuing.');
        }
        $mime = (string) $file->getMimeType();
        if (! in_array($mime, ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'], true)) {
            throw new AntivirusRuleException('selfie', 'Use a photo from the camera.');
        }
        if ((int) $file->getSize() > 4 * 1024 * 1024) {
            throw new AntivirusRuleException('selfie', 'That selfie is too large.');
        }
        $bytes = file_get_contents($file->getRealPath());
        if ($bytes === false || strlen($bytes) < 60) {
            throw new AntivirusRuleException('selfie', 'Take a selfie before continuing.');
        }

        return $this->saveSelfieBytes($request, $bytes);
    }

    public function saveSelfieBytes(AntivirusRequest $request, $bytes)
    {
        $this->draftOnly($request);
        $old = $request->selfie_path;
        $request->selfie_path = $this->writePrivate($request->id, 'selfie.jpg', $bytes);
        $request->save();
        if ($old && $old !== $request->selfie_path) {
            $this->deletePrivate($old);
        }

        return $request;
    }

    public function saveSignature(AntivirusRequest $request, $dataUrl)
    {
        $this->draftOnly($request);
        if (! $request->region || ! $request->station || ! $request->quantity || ! $request->requester_role) {
            throw new AntivirusRuleException('signature', 'Enter the station and your role before signing.');
        }
        $bytes = $this->signatureBytes($dataUrl);
        $old = $request->signature_path;
        $request->signature_path = $this->writePrivate($request->id, 'requester-signature.png', $bytes);
        $request->signed_at = now();
        $request->sign_fingerprint = AntivirusCatalog::fingerprint($request);
        $request->save();
        if ($old && $old !== $request->signature_path) {
            $this->deletePrivate($old);
        }

        return $request;
    }

    public function issueHandoff(AntivirusRequest $request)
    {
        $this->draftOnly($request);
        if (! $request->region || ! $request->quantity) {
            throw new AntivirusRuleException('handoff', 'Enter the station details before using another device.');
        }
        AntivirusHandoff::where('antivirus_request_id', $request->id)
            ->whereNull('consumed_at')
            ->update(['expires_at' => now()]);
        $token = $this->newToken();
        $row = new AntivirusHandoff();
        $row->antivirus_request_id = $request->id;
        $row->token_hash = hash('sha256', $token);
        $row->expires_at = now()->addMinutes(20);
        $row->save();

        return $token;
    }

    public function findHandoff($token)
    {
        return $this->findByToken(AntivirusHandoff::class, $token);
    }

    public function openHandoff($token)
    {
        $row = $this->findHandoff($token);
        if (! $row) {
            throw new AntivirusRuleException('link', 'This phone link is not valid.');
        }
        if ($row->consumed_at) {
            throw new AntivirusRuleException('link', 'This phone link has already been used.');
        }
        if ($row->expires_at && $row->expires_at->lt(now())) {
            throw new AntivirusRuleException('link', 'This phone link has expired. Generate a new QR code on the original screen.');
        }

        return $row;
    }

    public function markHandoffVerified(AntivirusHandoff $handoff)
    {
        $handoff = $this->openHandoffById($handoff->id);
        $handoff->phone_verified_at = now();
        $handoff->save();

        return $handoff;
    }

    public function captureHandoff(AntivirusHandoff $handoff, $selfieFile, $dataUrl, $focus = 'selfie')
    {
        $handoff = $this->openHandoffById($handoff->id);
        $focus = $focus === 'sign' ? 'sign' : 'selfie';
        $request = AntivirusRequest::find($handoff->antivirus_request_id);
        if (! $request) {
            throw new AntivirusRuleException('link', 'This request is no longer available.');
        }
        if ($focus === 'sign') {
            $this->saveSignature($request, $dataUrl);
        } else {
            $this->saveSelfie($request, $selfieFile);
        }
        $handoff->consumed_at = now();
        $handoff->save();

        return $request->fresh();
    }

    public function saveApprovers(AntivirusRequest $request, array $raw, array $hits)
    {
        $this->draftOnly($request);
        $people = $this->normalizeApprovers($request, $raw, $hits);
        $request->approvers_json = json_encode($people);
        $request->save();

        return $people;
    }

    public function savedApprovers(AntivirusRequest $request)
    {
        $decoded = json_decode((string) $request->approvers_json, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function submit(AntivirusRequest $request)
    {
        $this->queue = [];
        try {
            $saved = DB::transaction(function () use ($request) {
                $locked = AntivirusRequest::where('id', $request->id)->lockForUpdate()->first();
                if (! $locked) {
                    throw new AntivirusRuleException('request', 'Start the request again.');
                }
                if ($locked->status !== 'draft') {
                    return $locked;
                }
                $this->assertReady($locked);
                $people = $this->savedApprovers($locked);
                if (count($people) < 1) {
                    throw new AntivirusRuleException('approvers', 'Add at least one approver.');
                }
                $locked->reference = $this->nextReference((int) $locked->year);
                $locked->status = 'awaiting_station';
                $locked->submitted_at = now();
                $locked->save();
                $links = [];
                foreach ($people as $index => $person) {
                    $token = $this->newToken();
                    $approver = new AntivirusApprover();
                    $approver->antivirus_request_id = $locked->id;
                    $approver->position = $index + 1;
                    $approver->role = $person['role'];
                    $approver->role_other = $person['role_other'];
                    $approver->phone = $person['phone'];
                    $approver->name = $person['name'];
                    $approver->name_source = $person['name_source'];
                    $approver->token_hash = hash('sha256', $token);
                    $approver->link_expires_at = now()->addDays(14);
                    $approver->status = 'pending';
                    $approver->save();
                    $links[] = [
                        'phone' => $person['phone'],
                        'name' => $person['name'],
                        'link' => route('antivirus.approve', ['token' => $token]),
                    ];
                }
                $line = AntivirusCatalog::approverList($people);
                $this->enqueue($locked, 'submitted_requester', $locked->requester_phone, AntivirusCatalog::submittedMessage($locked, $line));
                foreach ($links as $link) {
                    $this->enqueue(
                        $locked,
                        'approver_link',
                        $link['phone'],
                        AntivirusCatalog::approverInviteMessage($locked, $link['link'], $link['name'])
                    );
                }
                $this->activity($locked, 'Submitted', 'Sent to '.$line);

                return $locked;
            });
        } catch (\Throwable $e) {
            $this->queue = [];
            throw $e;
        }
        $this->attachApproverPdf($saved);
        $this->flush();

        return $saved->fresh();
    }

    protected function attachApproverPdf(AntivirusRequest $request)
    {
        try {
            $this->writePdf($request);
        } catch (\Throwable $e) {
            report($e);

            return;
        }
        $request = $request->fresh();
        if (! $request || ! $request->pdf_path) {
            return;
        }
        $name = 'Anti-Virus-'.str_replace(['/', ' '], '-', (string) $request->reference).'.pdf';
        AntivirusNotice::where('antivirus_request_id', $request->id)
            ->where('kind', 'approver_link')
            ->where('status', 'queued')
            ->update([
                'document_path' => $request->pdf_path,
                'document_name' => $name,
            ]);
    }

    public function findApprover($token)
    {
        return $this->findByToken(AntivirusApprover::class, $token);
    }

    public function openApprover($token)
    {
        $row = $this->findApprover($token);
        if (! $row) {
            throw new AntivirusRuleException('link', 'This approval link is not valid.');
        }
        if ($row->link_expires_at && $row->link_expires_at->lt(now()) && $row->status === 'pending') {
            throw new AntivirusRuleException('link', 'This approval link has expired.');
        }

        return $row;
    }

    public function markApproverVerified(AntivirusApprover $approver)
    {
        $approver->phone_verified_at = now();
        $approver->save();

        return $approver;
    }

    public function signApprover(AntivirusApprover $approver, $dataUrl, $nameConfirmed)
    {
        $this->queue = [];
        try {
            $saved = DB::transaction(function () use ($approver, $dataUrl, $nameConfirmed) {
                $lockedRequest = AntivirusRequest::where('id', $approver->antivirus_request_id)->lockForUpdate()->first();
                $locked = AntivirusApprover::where('id', $approver->id)->lockForUpdate()->first();
                if (! $locked || ! $lockedRequest) {
                    throw new AntivirusRuleException('link', 'This approval link is not valid.');
                }
                if ($locked->status === 'signed') {
                    return $lockedRequest;
                }
                if ($lockedRequest->status !== 'awaiting_station' || $locked->status === 'declined') {
                    throw new AntivirusRuleException('request', 'This request is no longer waiting for your signature.');
                }
                if (! $locked->phone_verified_at || $locked->phone_verified_at->lt(now()->subMinutes(30))) {
                    throw new AntivirusRuleException('otp', 'Enter the code sent to your phone first.');
                }
                if ($locked->name_source === 'manual' && ! $locked->name_confirmed_at && ! $nameConfirmed) {
                    throw new AntivirusRuleException('name', 'Confirm your name before signing.');
                }
                if ($locked->name_source === 'manual' && $nameConfirmed && ! $locked->name_confirmed_at) {
                    $locked->name_confirmed_at = now();
                }
                $bytes = $this->signatureBytes($dataUrl);
                $locked->signature_path = $this->writePrivate($lockedRequest->id, 'approver-'.$locked->id.'.png', $bytes);
                $locked->status = 'signed';
                $locked->signed_at = now();
                $locked->save();
                $this->activity($lockedRequest, 'Station signature', $locked->name.' · '.$locked->roleLabel());
                $waiting = AntivirusApprover::where('antivirus_request_id', $lockedRequest->id)
                    ->where('status', '!=', 'signed')
                    ->count();
                if ($waiting === 0 && $lockedRequest->status === 'awaiting_station' && ! $lockedRequest->forwarded_at) {
                    $this->forwardToChief($lockedRequest);
                }

                return $lockedRequest;
            });
        } catch (\Throwable $e) {
            $this->queue = [];
            throw $e;
        }
        $this->flush();

        return $saved->fresh();
    }

    public function declineApprover(AntivirusApprover $approver, $reason)
    {
        $reason = trim(strip_tags((string) $reason));
        if (mb_strlen($reason) < 3) {
            throw new AntivirusRuleException('reason', 'Enter the reason for declining.');
        }
        $this->queue = [];
        try {
            $saved = DB::transaction(function () use ($approver, $reason) {
                $lockedRequest = AntivirusRequest::where('id', $approver->antivirus_request_id)->lockForUpdate()->first();
                $locked = AntivirusApprover::where('id', $approver->id)->lockForUpdate()->first();
                if (! $locked || ! $lockedRequest) {
                    throw new AntivirusRuleException('link', 'This approval link is not valid.');
                }
                if ($locked->status !== 'pending' || $lockedRequest->status !== 'awaiting_station') {
                    return $lockedRequest;
                }
                if (! $locked->phone_verified_at || $locked->phone_verified_at->lt(now()->subMinutes(30))) {
                    throw new AntivirusRuleException('otp', 'Enter the code sent to your phone first.');
                }
                $locked->status = 'declined';
                $locked->declined_at = now();
                $locked->decline_reason = mb_substr($reason, 0, 500);
                $locked->save();
                $this->markDeclined($lockedRequest, $locked->decline_reason, $locked->roleLabel());

                return $lockedRequest;
            });
        } catch (\Throwable $e) {
            $this->queue = [];
            throw $e;
        }
        $this->flush();

        return $saved->fresh();
    }

    public function markChiefVerified(AntivirusRequest $request)
    {
        $request->chief_phone_verified_at = now();
        $request->save();

        return $request;
    }

    public function signChief(AntivirusRequest $request, $dataUrl)
    {
        $this->queue = [];
        $settings = $this->settings();
        if (! $settings->chief_phone || ! $settings->chief_name) {
            throw new AntivirusRuleException('chief', 'Save the Chief’s name and phone before signing.');
        }
        try {
            $saved = DB::transaction(function () use ($request, $dataUrl, $settings) {
                $locked = AntivirusRequest::where('id', $request->id)->lockForUpdate()->first();
                if (! $locked) {
                    throw new AntivirusRuleException('request', 'This request was not found.');
                }
                if ($locked->status === 'approved') {
                    return ['request' => $locked, 'pdf' => null, 'receipt' => null];
                }
                if ($locked->status !== 'awaiting_chief') {
                    throw new AntivirusRuleException('request', 'This request is not waiting for the Chief.');
                }
                if (! $locked->chief_phone_verified_at || $locked->chief_phone_verified_at->lt(now()->subMinutes(30))) {
                    throw new AntivirusRuleException('otp', 'Enter the code sent to the Chief’s phone first.');
                }
                $bytes = $this->signatureBytes($dataUrl);
                $locked->chief_name = $settings->chief_name;
                $locked->chief_phone = $settings->chief_phone;
                $locked->chief_signature_path = $this->writePrivate($locked->id, 'chief-signature.png', $bytes);
                $locked->chief_signed_at = now();
                $locked->approved_at = now();
                $locked->status = 'approved';
                $locked->receipt_status = 'awaiting';
                $pdfToken = $this->newToken();
                $receiptToken = $this->newToken();
                $locked->pdf_token_hash = hash('sha256', $pdfToken);
                $locked->pdf_expires_at = now()->addDays(90);
                $locked->receipt_token_hash = hash('sha256', $receiptToken);
                $locked->receipt_expires_at = now()->addDays(60);
                $locked->save();
                $this->activity($locked, 'Approved', AntivirusCatalog::CHIEF_ROLE);

                return [
                    'request' => $locked,
                    'pdf' => $pdfToken,
                    'receipt' => $receiptToken,
                ];
            });
        } catch (\Throwable $e) {
            $this->queue = [];
            throw $e;
        }

        $pdfToken = is_array($saved) ? $saved['pdf'] : null;
        $receiptToken = is_array($saved) ? $saved['receipt'] : null;
        $saved = is_array($saved) ? $saved['request'] : $saved;
        if ($saved->status === 'approved' && $pdfToken) {
            try {
                $this->writePdf($saved);
            } catch (\Throwable $e) {
                report($e);
            }
            $saved = $saved->fresh();
            $letter = route('antivirus.letter', ['token' => $pdfToken]);
            $receipt = route('antivirus.receipt', ['token' => $receiptToken]);
            $fileName = 'Antivirus-'.$saved->reference.'.pdf';
            $fileName = str_replace(['/', ' '], '-', $fileName);
            $this->enqueue($saved, 'approved_requester', $saved->requester_phone, AntivirusCatalog::finalApprovalMessage($saved, $letter, $saved->requester_name), $saved->pdf_path, $fileName);
            $this->enqueue($saved, 'receipt_requester', $saved->requester_phone, AntivirusCatalog::receiptMessage($saved, $receipt));
            foreach ($saved->approvers as $approver) {
                if ($approver->status !== 'signed') {
                    continue;
                }
                $this->enqueue($saved, 'approved_approver', $approver->phone, AntivirusCatalog::finalApprovalMessage($saved, $letter, $approver->name), $saved->pdf_path, $fileName);
            }
            $this->flush();
        }

        return $saved->fresh();
    }

    public function declineChief(AntivirusRequest $request, $reason)
    {
        $reason = trim(strip_tags((string) $reason));
        if (mb_strlen($reason) < 3) {
            throw new AntivirusRuleException('reason', 'Enter the reason for declining.');
        }
        $this->queue = [];
        try {
            $saved = DB::transaction(function () use ($request, $reason) {
                $locked = AntivirusRequest::where('id', $request->id)->lockForUpdate()->first();
                if (! $locked) {
                    throw new AntivirusRuleException('request', 'This request was not found.');
                }
                if ($locked->status === 'declined' || $locked->status === 'approved') {
                    return $locked;
                }
                if ($locked->status !== 'awaiting_chief') {
                    throw new AntivirusRuleException('request', 'This request is not waiting for the Chief.');
                }
                if (! $locked->chief_phone_verified_at || $locked->chief_phone_verified_at->lt(now()->subMinutes(30))) {
                    throw new AntivirusRuleException('otp', 'Enter the code sent to the Chief’s phone first.');
                }
                $this->markDeclined($locked, mb_substr($reason, 0, 500), AntivirusCatalog::CHIEF_ROLE);

                return $locked;
            });
        } catch (\Throwable $e) {
            $this->queue = [];
            throw $e;
        }
        $this->flush();

        return $saved->fresh();
    }

    public function acknowledge(AntivirusRequest $request)
    {
        if (! $request->forwarded_at) {
            throw new AntivirusRuleException('request', 'This request has not been forwarded yet.');
        }
        if (! $request->finance_acknowledged_at) {
            $request->finance_acknowledged_at = now();
            $request->save();
            $this->activity($request, 'Finance acknowledged', 'The forwarded request was acknowledged.');
        }

        return $request;
    }

    public function recordPayment(AntivirusRequest $request, $date, $amount, $reference)
    {
        if ($request->status !== 'approved') {
            throw new AntivirusRuleException('payment', 'Record payment after the Chief has approved the request.');
        }
        $date = trim((string) $date);
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            throw new AntivirusRuleException('payment_date', 'Enter the payment date.');
        }
        $amountRaw = str_replace([',', ' '], '', trim((string) $amount));
        if (! is_numeric($amountRaw) || (float) $amountRaw <= 0) {
            throw new AntivirusRuleException('payment_amount', 'Enter the amount paid.');
        }
        $reference = trim(strip_tags((string) $reference));
        if ($reference === '') {
            throw new AntivirusRuleException('payment_reference', 'Enter the payment reference.');
        }
        $request->payment_date = $date;
        $request->payment_amount = round((float) $amountRaw, 2);
        $request->payment_reference = mb_substr($reference, 0, 191);
        $request->payment_recorded_at = now();
        $request->payment_status = 'recorded';
        $request->save();
        $this->activity($request, 'Payment recorded', $request->payment_reference.' · '.$request->payment_amount);

        return $request;
    }

    public function markReceiptVerified(AntivirusRequest $request)
    {
        $request->receipt_phone_verified_at = now();
        $request->save();

        return $request;
    }

    public function confirmReceipt(AntivirusRequest $request, $quantity, $note)
    {
        if ($request->status !== 'approved') {
            throw new AntivirusRuleException('receipt', 'Receipt is confirmed after the Chief approves the request.');
        }
        if (! $request->receipt_phone_verified_at || $request->receipt_phone_verified_at->lt(now()->subMinutes(30))) {
            throw new AntivirusRuleException('otp', 'Enter the code sent to your phone first.');
        }
        $qtyRaw = trim((string) $quantity);
        if (! preg_match('/^\d+$/', $qtyRaw) || (int) $qtyRaw < 0 || (int) $qtyRaw > 100000) {
            throw new AntivirusRuleException('receipt_quantity', 'Enter how many licences were received.');
        }
        $received = (int) $qtyRaw;
        $request->receipt_quantity = $received;
        $request->receipt_note = trim(strip_tags((string) $note)) !== '' ? mb_substr(trim(strip_tags((string) $note)), 0, 500) : null;
        $request->receipt_confirmed_at = now();
        $request->receipt_status = $received < (int) $request->quantity ? 'shortfall' : 'confirmed';
        $request->save();
        $this->activity(
            $request,
            $request->receipt_status === 'shortfall' ? 'Receipt shortfall' : 'Receipt confirmed',
            $received.' of '.$request->quantity
        );

        return $request;
    }

    public function findByReceiptToken($token)
    {
        return $this->findRequestByHash('receipt_token_hash', $token, 'receipt_expires_at');
    }

    public function findByPdfToken($token)
    {
        return $this->findRequestByHash('pdf_token_hash', $token, 'pdf_expires_at');
    }

    public function writePdf(AntivirusRequest $request)
    {
        $request->load('approvers');
        $setting = null;
        if (Schema::hasTable('general_settings')) {
            $setting = GeneralSetting::query()->first();
        }
        $pdf = \PDF::loadView('pdf.antivirus_approval', [
            'general_setting' => $setting,
            'use_system_letterhead' => true,
            'letterhead_flow' => true,
            'antivirus' => $request,
            'requesterSignature' => $this->dataUri($request->signature_path),
            'chiefSignature' => $this->dataUri($request->chief_signature_path),
            'approverSignatures' => $this->approverSignatures($request),
        ])->setPaper('A4', 'portrait');
        $path = 'antivirus/'.$request->id.'/approval.pdf';
        Storage::disk('local')->put($path, $pdf->output());
        $request->pdf_path = $path;
        $request->save();

        return $path;
    }

    public function retryQueued()
    {
        $ids = AntivirusNotice::where('status', 'queued')->pluck('id');
        foreach ($ids as $id) {
            $this->notifier->queue($id);
        }

        return count($ids);
    }

    public function resendApprover(AntivirusApprover $approver)
    {
        $request = $approver->requestRow;
        if (! $request || $approver->status !== 'pending') {
            throw new AntivirusRuleException('request', 'This approver has already answered.');
        }
        $token = $this->newToken();
        $approver->token_hash = hash('sha256', $token);
        $approver->link_expires_at = now()->addDays(14);
        $approver->save();
        $this->replaceNotice(
            $request,
            'approver_link',
            $approver->phone,
            AntivirusCatalog::approverInviteMessage($request, route('antivirus.approve', ['token' => $token]), $approver->name)
        );
        if ($request->pdf_path) {
            $name = 'Anti-Virus-'.str_replace(['/', ' '], '-', (string) $request->reference).'.pdf';
            AntivirusNotice::where('antivirus_request_id', $request->id)
                ->where('kind', 'approver_link')
                ->where('phone', WhatsAppPhone::forWasender($approver->phone))
                ->update([
                    'document_path' => $request->pdf_path,
                    'document_name' => $name,
                    'document_sent_at' => null,
                ]);
        }
    }

    public function resendChief(AntivirusRequest $request)
    {
        if ($request->status !== 'awaiting_chief') {
            throw new AntivirusRuleException('request', 'This request is not waiting for the Chief.');
        }
        $settings = $this->settings();
        if (! $settings->chief_phone) {
            throw new AntivirusRuleException('chief_phone', 'Save the Chief’s phone first.');
        }
        $this->replaceNotice(
            $request,
            'station_chief',
            $settings->chief_phone,
            AntivirusCatalog::chiefForwardMessage($request, route('admin.antivirus.show', ['id' => $request->id]))
        );
    }

    public function discard(AntivirusRequest $request)
    {
        $this->draftOnly($request);
        foreach ([$request->selfie_path, $request->signature_path] as $path) {
            $this->deletePrivate($path);
        }
        AntivirusHandoff::where('antivirus_request_id', $request->id)->delete();
        AntivirusActivity::where('antivirus_request_id', $request->id)->delete();
        $request->delete();
    }

    public function signatureBytes($dataUrl)
    {
        $dataUrl = trim((string) $dataUrl);
        if (! preg_match('#^data:image/png;base64,#', $dataUrl)) {
            throw new AntivirusRuleException('signature', 'Draw your signature before continuing.');
        }
        $raw = base64_decode(substr($dataUrl, 22), true);
        if ($raw === false || strlen($raw) < 60 || strlen($raw) > 500000) {
            throw new AntivirusRuleException('signature', 'Draw your signature before continuing.');
        }
        $info = @getimagesizefromstring($raw);
        if (! $info || (int) $info[0] < 1 || (int) $info[1] < 1) {
            throw new AntivirusRuleException('signature', 'Draw your signature before continuing.');
        }

        return $raw;
    }

    protected function forwardToChief(AntivirusRequest $request)
    {
        $request->status = 'awaiting_chief';
        $request->station_approved_at = now();
        $request->forwarded_at = now();
        $request->save();
        $this->activity($request, 'Forwarded to the Chief', AntivirusCatalog::CHIEF_ROLE);
        $this->enqueue($request, 'station_requester', $request->requester_phone, AntivirusCatalog::stationApprovedMessage($request));
        $settings = $this->settings();
        if ($settings->chief_phone) {
            $this->enqueue(
                $request,
                'station_chief',
                $settings->chief_phone,
                AntivirusCatalog::chiefForwardMessage($request, route('admin.antivirus.show', ['id' => $request->id]))
            );
        } else {
            $this->activity($request, 'Chief phone missing', 'Set the Chief’s phone and resend.');
        }
    }

    protected function markDeclined(AntivirusRequest $request, $reason, $by)
    {
        $request->status = 'declined';
        $request->declined_at = now();
        $request->decline_reason = $reason;
        $request->declined_by = $by;
        $request->save();
        $this->activity($request, 'Declined', $by.': '.$reason);
        $this->enqueue($request, 'declined_requester', $request->requester_phone, AntivirusCatalog::declinedMessage($request));
    }

    protected function assertReady(AntivirusRequest $request)
    {
        if (! $request->phone_verified_at) {
            throw new AntivirusRuleException('phone', 'Verify your phone number before submitting.');
        }
        if (! $request->requester_role || ! $request->region || ! $request->station || ! $request->quantity) {
            throw new AntivirusRuleException('station', 'Complete the station details before submitting.');
        }
        if (! $request->selfie_path) {
            throw new AntivirusRuleException('selfie', 'Take a selfie before submitting.');
        }
        if (! $request->signatureIsCurrent()) {
            throw new AntivirusRuleException('signature', 'Sign again. The station, quantity, year, or your identity changed after the last signature.');
        }
    }

    protected function normalizeApprovers(AntivirusRequest $request, array $raw, array $hits)
    {
        $people = [];
        $seen = [];
        foreach (array_values($raw) as $item) {
            $phoneRaw = trim((string) (isset($item['phone']) ? $item['phone'] : ''));
            $role = (string) (isset($item['role']) ? $item['role'] : '');
            if ($phoneRaw === '' && $role === '' && count($people) > 0) {
                continue;
            }
            if (! isset(AntivirusCatalog::approverRoles()[$role])) {
                throw new AntivirusRuleException('approver_role', 'Choose the approver’s role first.');
            }
            $other = trim(strip_tags((string) (isset($item['role_other']) ? $item['role_other'] : '')));
            if ($role === 'other' && $other === '') {
                throw new AntivirusRuleException('approver_role_other', 'Describe the approver’s role.');
            }
            $phone = $this->approverPhone($phoneRaw);
            $keys = [$phone];
            if (strpos($phone, '237') === 0 && strlen($phone) === 12) {
                $keys[] = substr($phone, -9);
            }
            foreach ($keys as $key) {
                if (isset($seen[$key])) {
                    throw new AntivirusRuleException('approver_phone', 'Each approver needs a different phone number.');
                }
            }
            foreach ($keys as $key) {
                $seen[$key] = true;
            }
            $manual = $this->cleanName(isset($item['manual_name']) ? $item['manual_name'] : '');
            $hit = isset($hits[$phone]) ? $hits[$phone] : null;
            $hitName = (is_array($hit) && ! empty($hit['name'])) ? $this->cleanName($hit['name']) : '';
            $hitSource = is_array($hit) && isset($hit['source']) ? $hit['source'] : '';
            if ($manual !== '' && $hitName !== '' && strcasecmp($manual, $hitName) === 0 && in_array($hitSource, ['campay', 'pawapay'], true)) {
                $name = $hitName;
                $source = $hitSource;
            } elseif ($manual !== '') {
                $name = $manual;
                $source = 'manual';
            } elseif (is_array($hit) && $hitName !== '' && in_array($hitSource, ['campay', 'pawapay'], true)) {
                $name = $this->cleanName($hit['name']);
                $source = $hit['source'];
            } else {
                throw new AntivirusRuleException('approver_name', 'Enter the approver’s full name. The number lookup did not return one.');
            }
            if ($name === '') {
                throw new AntivirusRuleException('approver_name', 'Enter the approver’s full name.');
            }
            $people[] = [
                'role' => $role,
                'role_other' => $role === 'other' ? mb_substr($other, 0, 191) : null,
                'phone' => $phone,
                'name' => $name,
                'name_source' => $source,
            ];
            if (count($people) === 2) {
                break;
            }
        }
        if (count($people) < 1) {
            throw new AntivirusRuleException('approvers', 'Add at least one approver.');
        }

        return $people;
    }

    protected function nextReference($year)
    {
        $prefix = 'AV/'.$year.'/';
        $last = AntivirusRequest::where('reference', 'like', $prefix.'%')
            ->orderBy('reference', 'desc')
            ->lockForUpdate()
            ->value('reference');
        $next = 1;
        if ($last && preg_match('#/(\d+)$#', (string) $last, $match)) {
            $next = (int) $match[1] + 1;
        }

        return $prefix.str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    protected function enqueue(AntivirusRequest $request, $kind, $phone, $body, $documentPath = null, $documentName = null)
    {
        $phone = WhatsAppPhone::forWasender($phone);
        $notice = AntivirusNotice::where('antivirus_request_id', $request->id)
            ->where('kind', $kind)
            ->where('phone', $phone)
            ->first();
        if ($notice && in_array($notice->status, ['queued', 'sending', 'sent'], true)) {
            return;
        }
        if (! $notice) {
            $notice = new AntivirusNotice();
            $notice->antivirus_request_id = $request->id;
            $notice->kind = $kind;
            $notice->phone = $phone;
        }
        $notice->body = $body;
        $notice->document_path = $documentPath;
        $notice->document_name = $documentName;
        $notice->status = 'queued';
        $notice->save();
        $this->queue[] = (int) $notice->id;
    }

    protected function replaceNotice(AntivirusRequest $request, $kind, $phone, $body)
    {
        $phone = WhatsAppPhone::forWasender($phone);
        $notice = AntivirusNotice::where('antivirus_request_id', $request->id)
            ->where('kind', $kind)
            ->where('phone', $phone)
            ->first();
        if (! $notice) {
            $notice = new AntivirusNotice();
            $notice->antivirus_request_id = $request->id;
            $notice->kind = $kind;
            $notice->phone = $phone;
        }
        $notice->body = $body;
        $notice->status = 'queued';
        $notice->text_sent_at = null;
        $notice->save();
        $this->notifier->queue($notice->id);
    }

    protected function flush()
    {
        $ids = $this->queue;
        $this->queue = [];
        foreach ($ids as $id) {
            $this->notifier->queue($id);
        }
    }

    protected function activity(AntivirusRequest $request, $event, $detail = null)
    {
        $row = new AntivirusActivity();
        $row->antivirus_request_id = $request->id;
        $row->event = mb_substr($event, 0, 191);
        $row->detail = $detail !== null ? mb_substr((string) $detail, 0, 500) : null;
        $row->save();
    }

    protected function draftOnly(AntivirusRequest $request)
    {
        if ($request->status !== 'draft') {
            throw new AntivirusRuleException('request', 'This request has already been submitted.');
        }
    }

    protected function forgetSignatureIfStale(AntivirusRequest $request)
    {
        if (! $request->sign_fingerprint) {
            return;
        }
        if (hash_equals($request->sign_fingerprint, AntivirusCatalog::fingerprint($request))) {
            return;
        }
        $this->deletePrivate($request->signature_path);
        $request->signature_path = null;
        $request->signed_at = null;
        $request->sign_fingerprint = null;
    }

    protected function phone($raw, $field = 'phone')
    {
        try {
            $digits = WhatsAppPhone::normalize($raw);
        } catch (\InvalidArgumentException $e) {
            throw new AntivirusRuleException($field, 'Enter a Cameroon phone number, for example 675321739.');
        }
        if (strpos($digits, '237') !== 0 || strlen($digits) !== 12) {
            throw new AntivirusRuleException($field, 'Enter a Cameroon phone number, for example 675321739.');
        }

        return $digits;
    }

    protected function approverPhone($raw)
    {
        try {
            $digits = WhatsAppPhone::normalize($raw);
        } catch (\InvalidArgumentException $e) {
            throw new AntivirusRuleException('approver_phone', 'Enter the phone number with its country code, for example +250794006160.');
        }
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            throw new AntivirusRuleException('approver_phone', 'Enter the phone number with its country code, for example +250794006160.');
        }

        return $digits;
    }

    protected function cleanName($name)
    {
        $name = trim(preg_replace('/\s+/', ' ', strip_tags((string) $name)));
        if (mb_strlen($name) < 3 || mb_strlen($name) > 191) {
            return '';
        }
        if (preg_match('/^\+?\d+$/', $name)) {
            return '';
        }

        return $name;
    }

    protected function newToken()
    {
        return bin2hex(random_bytes(16));
    }

    protected function writePrivate($requestId, $name, $bytes)
    {
        $path = 'antivirus/'.(int) $requestId.'/'.$name;
        Storage::disk('local')->put($path, $bytes);

        return $path;
    }

    protected function deletePrivate($path)
    {
        if ($path && Storage::disk('local')->exists($path)) {
            Storage::disk('local')->delete($path);
        }
    }

    protected function dataUri($path)
    {
        if (! $path || ! Storage::disk('local')->exists($path)) {
            return null;
        }
        $bytes = Storage::disk('local')->get($path);
        $mime = (substr($path, -4) === '.jpg' || substr($path, -5) === '.jpeg') ? 'image/jpeg' : 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($bytes);
    }

    protected function approverSignatures(AntivirusRequest $request)
    {
        $out = [];
        foreach ($request->approvers as $approver) {
            $out[] = [
                'name' => $approver->name,
                'role' => $approver->roleLabel(),
                'signed_at' => $approver->signed_at,
                'image' => $this->dataUri($approver->signature_path),
            ];
        }

        return $out;
    }

    protected function findByToken($class, $token)
    {
        $token = strtolower(preg_replace('/[^a-f0-9]/i', '', (string) $token));
        if (strlen($token) !== 32) {
            return null;
        }
        $hash = hash('sha256', $token);
        $row = app(CloudTenantContext::class)->withoutIsolation(function () use ($class, $hash) {
            return $class::where('token_hash', $hash)->first();
        });
        if (! $row) {
            return null;
        }
        $this->adoptTenant($row->cloud_tenant_id);

        return $class::find($row->id);
    }

    protected function findRequestByHash($column, $token, $expiryColumn)
    {
        $token = strtolower(preg_replace('/[^a-f0-9]/i', '', (string) $token));
        if (strlen($token) !== 32) {
            throw new AntivirusRuleException('link', 'This link is not valid.');
        }
        $hash = hash('sha256', $token);
        $row = app(CloudTenantContext::class)->withoutIsolation(function () use ($column, $hash) {
            return AntivirusRequest::where($column, $hash)->first();
        });
        if (! $row) {
            throw new AntivirusRuleException('link', 'This link is not valid.');
        }
        $this->adoptTenant($row->cloud_tenant_id);
        $fresh = AntivirusRequest::find($row->id);
        if ($fresh && $fresh->{$expiryColumn} && $fresh->{$expiryColumn}->lt(now())) {
            throw new AntivirusRuleException('link', 'This link has expired.');
        }

        return $fresh;
    }

    protected function adoptTenant($id)
    {
        $context = app(CloudTenantContext::class);
        if ((int) $context->id() === (int) $id) {
            return;
        }
        $tenant = CloudTenant::find($id);
        if (! $tenant) {
            throw new AntivirusRuleException('link', 'This link is not available.');
        }
        $context->set($tenant);
    }

    protected function openHandoffById($id)
    {
        $row = AntivirusHandoff::find($id);
        if (! $row || $row->consumed_at) {
            throw new AntivirusRuleException('link', 'This phone link has already been used.');
        }
        if ($row->expires_at && $row->expires_at->lt(now())) {
            throw new AntivirusRuleException('link', 'This phone link has expired. Generate a new QR code on the original screen.');
        }

        return $row;
    }

}
