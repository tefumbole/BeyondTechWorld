<?php

namespace Tests\Unit;

use App\Antivirus\AntivirusApprover;
use App\Antivirus\AntivirusNotice;
use App\Antivirus\AntivirusRequest;
use App\Antivirus\AntivirusRuleException;
use App\Cloud\CloudTenant;
use App\Services\Antivirus\AntivirusNotifier;
use App\Services\Antivirus\AntivirusRequestService;
use App\Services\Cloud\CloudTenantColumns;
use App\Services\Cloud\CloudTenantContext;
use App\Support\AntivirusCatalog;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AntivirusRequestFlowTest extends TestCase
{
    /** @var AntivirusRequestService */
    protected $service;

    protected function setUp(): void
    {
        parent::setUp();
        require_once dirname(__DIR__, 2).'/database/migrations/2026_11_11_180000_create_antivirus_requests.php';
        (new \CreateAntivirusRequests())->up();
        CloudTenantColumns::forget();
        Storage::fake('local');
        $tenant = new CloudTenant();
        $tenant->id = 7;
        $tenant->exists = true;
        app(CloudTenantContext::class)->set($tenant);
        $this->service = new AntivirusRequestService(new AntivirusFlowNotifier());
    }

    protected function tearDown(): void
    {
        \Carbon\Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_one_approver_submission_and_a_second_click_does_not_send_again()
    {
        $draft = $this->readyDraft();
        $this->service->saveApprovers($draft, [[
            'role' => 'administrator',
            'role_other' => '',
            'phone' => '699112233',
            'manual_name' => 'Ada Lovelace',
        ]], []);
        $row = $this->service->submit($draft->fresh());
        $again = $this->service->submit($row);

        $this->assertSame('AV/2027/0001', $row->reference);
        $this->assertSame('awaiting_station', $again->status);
        $this->assertSame(1, AntivirusNotice::where('kind', 'submitted_requester')->count());
        $body = AntivirusNotice::where('kind', 'submitted_requester')->value('body');
        $this->assertSame(
            AntivirusCatalog::submittedMessage($row, 'Ada Lovelace (Administrator)'),
            $body
        );
        $invite = AntivirusNotice::where('kind', 'approver_link')->value('body');
        $this->assertStringContainsString('Please review and sign here:', $invite);
        $this->assertStringContainsString('Tefu Roland, IT Technician at Bafia District Hospital', $invite);
        $this->assertStringContainsString('CBC Health Services', $body);
        $this->assertStringNotContainsString('Beyond Enterprise', $body);
        $this->assertStringContainsString('CBC Health Services', $invite);
        $this->assertStringNotContainsString('Beyond Enterprise', $invite);
    }

    public function test_two_approvers_forward_once_even_if_the_first_signs_again()
    {
        $settings = $this->service->settings();
        $settings->chief_name = 'Amina Chief';
        $settings->chief_phone = '237600000001';
        $settings->save();
        $draft = $this->readyDraft();
        $this->service->saveApprovers($draft, [
            ['role' => 'administrator', 'phone' => '699112233', 'manual_name' => 'Ada Lovelace'],
            ['role' => 'assistant_finance', 'phone' => '655443322', 'manual_name' => 'Grace Hopper'],
        ], []);
        $row = $this->service->submit($draft->fresh());
        $approvers = AntivirusApprover::where('antivirus_request_id', $row->id)->orderBy('position')->get();
        $this->service->markApproverVerified($approvers[0]);
        $this->service->signApprover($approvers[0], $this->pngData(), true);
        $this->assertSame(0, AntivirusNotice::where('kind', 'station_requester')->count());
        $this->assertSame('awaiting_station', $row->fresh()->status);

        $this->service->markApproverVerified($approvers[1]);
        $this->service->signApprover($approvers[1], $this->pngData(), true);
        $this->service->signApprover($approvers[0]->fresh(), $this->pngData(), true);

        $this->assertSame('awaiting_chief', $row->fresh()->status);
        $this->assertSame(1, AntivirusNotice::where('kind', 'station_requester')->count());
        $this->assertSame(1, AntivirusNotice::where('kind', 'station_chief')->count());
        $this->assertSame(
            AntivirusCatalog::stationApprovedMessage($row->fresh()),
            AntivirusNotice::where('kind', 'station_requester')->value('body')
        );
    }

    public function test_decline_manual_name_duplicates_and_a_changed_quantity()
    {
        $draft = $this->readyDraft();
        $self = $this->service->saveApprovers($draft, [[
            'role' => 'administrator',
            'phone' => '675321739',
            'manual_name' => 'Tefu Roland',
        ]], []);
        $this->assertSame('675321739', substr($self[0]['phone'], -9));
        try {
            $this->service->saveApprovers($draft, [
                ['role' => 'administrator', 'phone' => '699112233', 'manual_name' => 'Ada Lovelace'],
                ['role' => 'other', 'role_other' => 'Stores', 'phone' => '237699112233', 'manual_name' => 'Ada Again'],
            ], []);
            $this->fail('A duplicate approver was accepted.');
        } catch (AntivirusRuleException $e) {
            $this->assertSame('approver_phone', $e->field);
        }

        $this->service->saveApprovers($draft, [[
            'role' => 'other',
            'role_other' => 'Stores',
            'phone' => '699112233',
            'manual_name' => 'Ada Lovelace',
        ]], []);
        $row = $this->service->submit($draft->fresh());
        $approver = AntivirusApprover::where('antivirus_request_id', $row->id)->first();
        $this->assertSame('manual', $approver->name_source);
        $foreign = $this->readyDraft();
        $saved = $this->service->saveApprovers($foreign, [[
            'role' => 'administrator',
            'phone' => '+250794006160',
            'manual_name' => 'Alpha Brindge Technologies',
        ]], [
            '250794006160' => ['name' => 'Alpha Brindge Technologies', 'source' => 'pawapay'],
        ]);
        $this->assertSame('250794006160', $saved[0]['phone']);
        $this->assertSame('pawapay', $saved[0]['name_source']);
        $this->service->markApproverVerified($approver);
        try {
            $this->service->signApprover($approver, $this->pngData(), false);
            $this->fail('A manual name was signed without confirmation.');
        } catch (AntivirusRuleException $e) {
            $this->assertSame('name', $e->field);
        }

        $other = $this->readyDraft();
        $this->service->saveStation($other, 'Centre', 'Bafia District Hospital', 8);
        $this->assertNull($other->fresh()->signature_path);
        try {
            $this->service->submit($other->fresh());
            $this->fail('A stale signature was accepted.');
        } catch (AntivirusRuleException $e) {
            $this->assertSame('signature', $e->field);
        }
    }

    public function test_chief_approval_payment_receipt_and_expired_links()
    {
        $settings = $this->service->settings();
        $settings->chief_name = 'Amina Chief';
        $settings->chief_phone = '237600000001';
        $settings->save();
        $draft = $this->readyDraft();
        $this->service->saveApprovers($draft, [[
            'role' => 'administrator',
            'phone' => '699112233',
            'manual_name' => 'Ada Lovelace',
        ]], []);
        $row = $this->service->submit($draft->fresh());
        $approver = AntivirusApprover::where('antivirus_request_id', $row->id)->first();
        $this->service->markApproverVerified($approver);
        $this->service->declineApprover($approver, 'Wrong station');
        $this->assertSame('declined', $row->fresh()->status);
        $this->assertStringContainsString('Wrong station', AntivirusNotice::where('kind', 'declined_requester')->value('body'));
        $this->service->declineApprover($approver->fresh(), 'Wrong station');
        $this->assertSame(1, AntivirusNotice::where('kind', 'declined_requester')->count());

        $draft = $this->readyDraft();
        $this->service->saveApprovers($draft, [[
            'role' => 'assistant_personnel',
            'phone' => '655443322',
            'manual_name' => 'Grace Hopper',
        ]], []);
        $row = $this->service->submit($draft->fresh());
        $approver = AntivirusApprover::where('antivirus_request_id', $row->id)->first();
        $this->service->markApproverVerified($approver);
        $row = $this->service->signApprover($approver, $this->pngData(), true);
        $this->service->markChiefVerified($row);
        $row = $this->service->signChief($row, $this->pngData());
        $this->service->signChief($row->fresh(), $this->pngData());

        $this->assertSame('approved', $row->fresh()->status);
        $this->assertSame('awaiting', $row->fresh()->receipt_status);
        $this->assertSame(1, AntivirusNotice::where('kind', 'approved_requester')->count());
        $approved = AntivirusNotice::where('kind', 'approved_requester')->value('body');
        $this->assertStringContainsString('The Chief of Administration and Finance has approved this Anti Virus request.', $approved);
        $this->assertStringContainsString('CBC Health Services', $approved);
        $this->assertStringNotContainsString('Beyond Enterprise', $approved);
        $this->assertSame(1, AntivirusNotice::where('kind', 'receipt_requester')->count());

        $row = $this->service->recordPayment($row->fresh(), '2027-03-01', '150000', 'TRX-77');
        $this->assertSame('recorded', $row->payment_status);
        $this->service->markReceiptVerified($row);
        $row = $this->service->confirmReceipt($row, 10, 'Two still to come');
        $this->assertSame('shortfall', $row->receipt_status);
        $this->assertSame(10, (int) $row->receipt_quantity);

        $token = $this->service->issueHandoff($this->readyDraft());
        \Carbon\Carbon::setTestNow(now()->addMinutes(21));
        try {
            $this->service->openHandoff($token);
            $this->fail('An expired phone link was accepted.');
        } catch (AntivirusRuleException $e) {
            $this->assertSame('link', $e->field);
        }
        \Carbon\Carbon::setTestNow();

        preg_match('#/letter/([a-f0-9]{32})#', $approved, $match);
        $letter = AntivirusRequest::find($row->id);
        $letter->pdf_expires_at = now()->subDay();
        $letter->save();
        try {
            $this->service->findByPdfToken($match[1]);
            $this->fail('An expired approval link was accepted.');
        } catch (AntivirusRuleException $e) {
            $this->assertSame('link', $e->field);
        }
    }

    protected function readyDraft()
    {
        $draft = $this->service->beginDraft('675321739', 'Tefu Roland', 'manual');
        $draft = $this->service->saveRole($draft, 'it_technician', null);
        $draft = $this->service->saveStation($draft, 'Centre', 'Bafia District Hospital', 12);
        $draft = $this->service->saveSelfieBytes($draft, $this->pngBytes());
        $draft = $this->service->saveSignature($draft, $this->pngData());

        return $draft->fresh();
    }

    protected function pngBytes()
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8BQDwAEhQGAhKmMIQAAAABJRU5ErkJggg==');
    }

    protected function pngData()
    {
        return 'data:image/png;base64,'.base64_encode($this->pngBytes());
    }
}

class AntivirusFlowNotifier extends AntivirusNotifier
{
    public function queue($noticeId)
    {
    }
}
