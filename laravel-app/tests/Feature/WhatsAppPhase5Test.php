<?php

namespace Tests\Feature;

use App\Contracts\Ai\AiProviderInterface;
use App\Contracts\WhatsApp\WhatsAppProviderInterface;
use App\Customer;
use App\InternshipEnrolment;
use App\InternshipGrade;
use App\InternshipProgram;
use App\InternshipProgramTask;
use App\InternshipSubmission;
use App\InternshipSubmissionFile;
use App\InternshipTaskAssignment;
use App\Jobs\RetrieveInternshipMedia;
use App\Services\Assistant\AssistantIntentRouter;
use App\Services\Assistant\AssistantToolExecutor;
use App\Services\Assistant\Providers\NullAiProvider;
use App\Services\Internship\InternshipWhatsAppService;
use App\Support\InternshipSubmissionFileGuard;
use App\User;
use App\WhatsApp\InternshipIntake;
use App\WhatsApp\InternshipIntakeFile;
use App\WhatsApp\WhatsAppContactLink;
use App\WhatsApp\WhatsAppConversation;
use App\WhatsApp\WhatsAppMessage;
use App\WhatsApp\WhatsAppSetting;
use App\WhatsApp\WhatsAppWebhookEvent;
use Carbon\Carbon;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Tests\WhatsAppHubTestCase;

class WhatsAppPhase5Test extends WhatsAppHubTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(WhatsAppProviderInterface::class, $this->fakeProvider());
        $this->app->instance(AiProviderInterface::class, new NullAiProvider());
        $this->extraTables();
        config(['services.whatsapp.internship_max_bytes' => 20 * 1024 * 1024]);
    }

    public function test_known_intern_receives_released_task_only()
    {
        $built = $this->intern('675510001', 'Write the day report');
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510001', 'What is my current task?', 'P5TASK'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertStringContainsString('Write the day report', $body);
        $this->assertStringContainsString('Open the brief', $body);
    }

    public function test_unknown_number_receives_no_internship_record()
    {
        $this->intern('675510001', 'SECRET_TASK_TITLE');
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675519999', 'What is my current task?', 'P5UNK'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertStringNotContainsString('SECRET_TASK_TITLE', $body);
        $this->assertStringNotContainsString('Open the brief', $body);
    }

    public function test_inactive_enrolment_is_not_treated_as_active()
    {
        $built = $this->intern('675510002', 'Paused Secret');
        $built['enrolment']->status = 'paused';
        $built['enrolment']->save();
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510002', 'What is my current task?', 'P5PAUSE'))->assertStatus(200);
        $this->assertStringContainsString('active internship', strtolower($this->latestAssistant()));
        $this->assertStringNotContainsString('Paused Secret', $this->latestAssistant());
    }

    public function test_multiple_active_enrolments_ask_instead_of_guessing()
    {
        $user = $this->user('675510003');
        $first = $this->program('Alpha Track');
        $second = $this->program('Beta Track');
        InternshipEnrolment::create(['student_user_id' => $user->id, 'program_id' => $first->id, 'status' => 'active', 'planned_duration_days' => 10, 'completed_count' => 0]);
        InternshipEnrolment::create(['student_user_id' => $user->id, 'program_id' => $second->id, 'status' => 'active', 'planned_duration_days' => 10, 'completed_count' => 0]);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510003', 'What is my current task?', 'P5MULTI'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertStringContainsString('Which one', $body);
        $this->assertStringContainsString('Alpha Track', $body);
        $this->assertStringContainsString('Beta Track', $body);
    }

    public function test_multi_role_contact_keeps_customer_and_intern()
    {
        $built = $this->intern('675510004', 'Role Task');
        Customer::create(['name' => 'Same Person', 'phone_number' => '675510004', 'is_active' => true]);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510004', 'What is my current task?', 'P5ROLE'))->assertStatus(200);
        $this->assertStringContainsString('Role Task', $this->latestAssistant());
        $roles = WhatsAppContactLink::pluck('role')->all();
        $this->assertContains('intern', $roles);
        $this->assertContains('customer', $roles);
        $this->assertContains('user', $roles);
    }

    public function test_locked_and_tomorrow_tasks_hide_title()
    {
        $built = $this->intern('675510005', 'Visible Today');
        $hidden = InternshipProgramTask::create([
            'program_id' => $built['program']->id,
            'day_number' => 2,
            'title' => 'TOMORROW_HIDDEN',
            'instructions_json' => json_encode(['Do not reveal this']),
            'submission_requirements' => 'PDF',
        ]);
        InternshipTaskAssignment::create([
            'enrolment_id' => $built['enrolment']->id,
            'program_task_id' => $hidden->id,
            'progression_day' => 2,
            'status' => 'available',
            'released_at' => null,
            'attempt_count' => 0,
        ]);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510005', "What is tomorrow's task?", 'P5TOM'))->assertStatus(200);
        $this->assertStringNotContainsString('TOMORROW_HIDDEN', $this->latestAssistant());
        $this->assertStringContainsString('not been released', $this->latestAssistant());

        $this->postWebhook($this->incomingText('+237675510005', 'What is day 2?', 'P5DAY2'))->assertStatus(200);
        $this->assertStringNotContainsString('TOMORROW_HIDDEN', $this->latestAssistant());
    }

    public function test_unreleased_current_task_does_not_invent_work()
    {
        $user = $this->user('675510006');
        $program = $this->program('Quiet Programme');
        $task = InternshipProgramTask::create([
            'program_id' => $program->id,
            'day_number' => 4,
            'title' => 'LOCKED_TITLE',
            'instructions_json' => json_encode(['Hidden steps']),
            'submission_requirements' => 'PDF',
        ]);
        $enrolment = InternshipEnrolment::create([
            'student_user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
            'planned_duration_days' => 10,
            'completed_count' => 0,
        ]);
        InternshipTaskAssignment::create([
            'enrolment_id' => $enrolment->id,
            'program_task_id' => $task->id,
            'progression_day' => 4,
            'status' => 'available',
            'released_at' => null,
            'attempt_count' => 0,
        ]);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510006', 'What is my current task?', 'P5LOCK'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertStringNotContainsString('LOCKED_TITLE', $body);
        $this->assertStringNotContainsString('Hidden steps', $body);
        $this->assertStringContainsString('not been released', $body);
    }

    public function test_materials_use_existing_task_text_when_no_handbook_file()
    {
        $this->intern('675510007', 'Material Task');
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510007', 'Send the instructions', 'P5MAT'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertTrue(
            strpos($body, 'Open the brief') !== false || strpos($body, 'do not have a separate file') !== false
        );
        $this->assertStringNotContainsString('http://', $body);
    }

    public function test_progress_comes_from_enrolment_counts()
    {
        $built = $this->intern('675510008', 'Progress Task');
        $built['enrolment']->completed_count = 2;
        $built['enrolment']->planned_duration_days = 10;
        $built['enrolment']->start_date = '2020-01-01';
        $built['enrolment']->save();
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510008', 'How far have I gone?', 'P5PROG'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertStringContainsString('2', $body);
        $this->assertStringContainsString('10', $body);
        $this->assertStringNotContainsString('2020', $body);
    }

    public function test_done_does_not_satisfy_a_pdf_requirement()
    {
        $built = $this->conversationIntern('675510009', 'PDF Task');
        $service = app(InternshipWhatsAppService::class);
        $prepared = $service->prepare($built['context'], ['text' => 'Done']);
        $this->assertTrue(! empty($prepared['awaiting_confirm']) || ! empty($prepared['success']));
        $this->assertSame(0, InternshipSubmission::count());
        $confirmed = $service->confirm($built['context'], ['text' => 'yes']);
        $this->assertSame('missing_requirement', $confirmed['error']);
        $this->assertSame(0, InternshipSubmission::count());
    }

    public function test_pdf_confirmation_creates_one_erp_submission()
    {
        $built = $this->conversationIntern('675510010', 'PDF Task');
        $path = $this->tempFile('report.pdf', "%PDF-1.4\nstage5");
        $service = app(InternshipWhatsAppService::class);
        $prepared = $service->prepare($built['context'], [
            'text' => 'Here is my assignment',
            'local_path' => $path,
            'file_name' => 'report.pdf',
            'mime' => 'application/pdf',
            'provider_message_id' => 'P5PDF1',
        ]);
        $this->assertTrue(! empty($prepared['awaiting_confirm']));
        $this->assertSame(0, InternshipSubmission::count());
        $confirmed = $service->confirm($built['context'], ['text' => 'yes', 'provider_message_id' => 'P5PDF2']);
        $this->assertTrue($confirmed['success']);
        $this->assertSame(1, InternshipSubmission::count());
        $submission = InternshipSubmission::first();
        $this->assertSame('submitted', $submission->status);
        $this->assertSame('whatsapp', $submission->source);
        $this->assertSame($built['conversation']->id, (int) $submission->whatsapp_conversation_id);
        $this->assertSame('submitted', $built['assignment']->fresh()->status);
        $this->assertSame(1, InternshipTaskAssignment::count());
        $this->assertSame(1, InternshipSubmissionFile::count());
        $again = $service->confirm($built['context'], ['text' => 'yes']);
        $this->assertTrue(! empty($again['duplicate']));
        $this->assertSame(1, InternshipSubmission::count());
    }

    public function test_image_doc_github_and_text_submissions()
    {
        $image = $this->conversationIntern('675510011', 'Shot Task', 'PNG screenshot');
        $png = $this->tempFile('shot.png', 'png-bytes');
        $service = app(InternshipWhatsAppService::class);
        $service->prepare($image['context'], [
            'text' => 'See attached',
            'local_path' => $png,
            'file_name' => 'shot.png',
            'mime' => 'image/png',
        ]);
        $imageResult = $service->confirm($image['context'], ['text' => 'submit']);
        $this->assertTrue($imageResult['success']);

        $doc = $this->conversationIntern('675510012', 'Doc Task', 'DOC file');
        $docPath = $this->tempFile('notes.doc', 'doc-bytes');
        $service->prepare($doc['context'], [
            'text' => 'Here is my assignment',
            'local_path' => $docPath,
            'file_name' => 'notes.doc',
            'mime' => 'application/msword',
        ]);
        $this->assertNotEmpty($service->confirm($doc['context'], ['text' => 'yes'])['submission_id']);

        $git = $this->conversationIntern('675510013', 'Git Task', 'GitHub repository URL');
        $service->prepare($git['context'], ['text' => 'Here is my GitHub https://github.com/beyondtech/intern-work']);
        $gitResult = $service->confirm($git['context'], ['text' => 'yes']);
        $this->assertTrue($gitResult['success']);
        $stored = InternshipSubmissionFile::orderByDesc('id')->first();
        $this->assertStringContainsString('github.com/beyondtech/intern-work', file_get_contents(storage_path('app/'.$stored->path)));
        $this->assertFalse(is_dir(storage_path('app/beyondtech')));

        $text = $this->conversationIntern('675510014', 'Text Task', 'text answer');
        $service->prepare($text['context'], ['text' => 'My written answer is complete.']);
        $textResult = $service->confirm($text['context'], ['text' => 'yes']);
        $this->assertTrue($textResult['success']);
    }

    public function test_invalid_github_voice_unsafe_and_oversized_are_rejected()
    {
        $this->assertFalse(InternshipSubmissionFileGuard::isGithubUrl('https://evil.example/github.com/foo/bar'));
        $this->assertFalse(InternshipSubmissionFileGuard::isGithubUrl('https://github.com/../secret'));
        $git = $this->conversationIntern('675510015', 'Git Task', 'GitHub repository URL');
        $service = app(InternshipWhatsAppService::class);
        $service->prepare($git['context'], ['text' => 'https://evil.example/repo']);
        $this->assertSame([], InternshipIntake::first()->links());

        $voice = $this->conversationIntern('675510016', 'Voice Task', 'PDF of the finished work');
        $audio = $this->tempFile('note.mp3', 'audio');
        $voiceResult = $service->prepare($voice['context'], [
            'text' => 'See attached',
            'local_path' => $audio,
            'file_name' => 'note.mp3',
            'mime' => 'audio/mpeg',
        ]);
        $this->assertSame('voice_not_allowed', $voiceResult['error']);
        $this->assertSame(0, InternshipSubmission::count());

        $unsafe = $this->conversationIntern('675510017', 'Unsafe Task', 'PDF');
        $php = $this->tempFile('shell.php', '<?php echo 1;');
        $unsafeResult = $service->prepare($unsafe['context'], [
            'local_path' => $php,
            'file_name' => 'shell.php',
            'mime' => 'text/plain',
        ]);
        $this->assertSame('unsafe_type', $unsafeResult['error']);

        config(['services.whatsapp.internship_max_bytes' => 40]);
        $big = $this->conversationIntern('675510018', 'Big Task', 'PDF');
        $large = $this->tempFile('big.pdf', str_repeat('A', 80));
        $bigResult = $service->prepare($big['context'], [
            'local_path' => $large,
            'file_name' => 'big.pdf',
            'mime' => 'application/pdf',
        ]);
        $this->assertSame('too_large', $bigResult['error']);
        $this->assertSame(0, InternshipSubmission::where('student_user_id', $big['user']->id)->count());
    }

    public function test_media_failure_does_not_submit_and_allows_retry()
    {
        $built = $this->conversationIntern('675510019', 'Media Task');
        $service = app(InternshipWhatsAppService::class);
        $failed = $service->prepare($built['context'], [
            'text' => 'See attached',
            'media' => ['url' => 'http://example.com/file.pdf', 'file_name' => 'file.pdf', 'mimetype' => 'application/pdf'],
            'provider_message_id' => 'P5BAD',
        ]);
        $this->assertSame('media_failed', $failed['error']);
        $this->assertSame(0, InternshipSubmission::count());
        $intake = InternshipIntake::first();
        $this->assertNotSame(InternshipIntake::SUBMITTED, $intake->status);

        $file = InternshipIntakeFile::create([
            'intake_id' => $intake->id,
            'kind' => 'file',
            'url' => 'http://127.0.0.1/secret.pdf',
            'original_name' => 'secret.pdf',
            'status' => 'pending',
        ]);
        $job = new RetrieveInternshipMedia($file->id);
        $job->handle($service);
        $this->assertSame('failed', $file->fresh()->status);
        $this->assertSame(0, InternshipSubmission::count());

        $path = $this->tempFile('retry.pdf', "%PDF-1.4\nretry");
        $service->prepare($built['context'], [
            'text' => 'Here is my assignment',
            'local_path' => $path,
            'file_name' => 'retry.pdf',
            'mime' => 'application/pdf',
        ]);
        $ok = $service->confirm($built['context'], ['text' => 'yes']);
        $this->assertTrue($ok['success']);
    }

    public function test_multiple_files_stay_on_one_submission()
    {
        $built = $this->conversationIntern('675510020', 'Bundle Task', 'PDF and PNG');
        $service = app(InternshipWhatsAppService::class);
        $service->prepare($built['context'], [
            'local_path' => $this->tempFile('a.pdf', "%PDF-1.4\na"),
            'file_name' => 'a.pdf',
            'mime' => 'application/pdf',
            'provider_message_id' => 'P5A',
        ]);
        $service->prepare($built['context'], [
            'local_path' => $this->tempFile('b.png', 'png'),
            'file_name' => 'b.png',
            'mime' => 'image/png',
            'provider_message_id' => 'P5A',
        ]);
        $this->assertSame(1, InternshipIntake::count());
        $this->assertSame(1, InternshipIntakeFile::where('status', 'stored')->count());
        $service->prepare($built['context'], [
            'local_path' => $this->tempFile('c.png', 'png2'),
            'file_name' => 'c.png',
            'mime' => 'image/png',
            'provider_message_id' => 'P5C',
        ]);
        $result = $service->confirm($built['context'], ['text' => 'yes']);
        $this->assertTrue($result['success']);
        $this->assertSame(1, InternshipSubmission::count());
        $this->assertSame(2, InternshipSubmissionFile::count());
    }

    public function test_revision_creates_another_attempt_without_releasing_the_next_task()
    {
        $built = $this->conversationIntern('675510021', 'Revision Task');
        $built['assignment']->status = 'revision_required';
        $built['assignment']->attempt_count = 1;
        $built['assignment']->save();
        $service = app(InternshipWhatsAppService::class);
        $service->prepare($built['context'], [
            'text' => "I've corrected it",
            'local_path' => $this->tempFile('fixed.pdf', "%PDF-1.4\nfixed"),
            'file_name' => 'fixed.pdf',
            'mime' => 'application/pdf',
        ]);
        $result = $service->confirm($built['context'], ['text' => 'yes']);
        $this->assertSame(2, (int) $result['attempt']);
        $this->assertSame(1, InternshipTaskAssignment::count());
        $this->assertSame('submitted', $built['assignment']->fresh()->status);
    }

    public function test_grade_status_is_read_and_ai_cannot_grade()
    {
        $built = $this->intern('675510022', 'Graded Task');
        $submission = InternshipSubmission::create([
            'assignment_id' => $built['assignment']->id,
            'student_user_id' => $built['user']->id,
            'attempt_no' => 1,
            'description' => 'already in',
            'submitted_at' => now(),
            'status' => 'submitted',
            'source' => 'whatsapp',
        ]);
        InternshipGrade::create([
            'submission_id' => $submission->id,
            'score' => 18,
            'decision' => 'pass',
            'feedback' => 'Good work',
            'graded_at' => now(),
        ]);
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510022', 'Did I pass?', 'P5GRADE'))->assertStatus(200);
        $body = $this->latestAssistant();
        $this->assertStringContainsString('18', $body);
        $this->assertStringContainsString('pass', strtolower($body));
        $this->assertSame(1, InternshipGrade::count());

        $blocked = app(AssistantToolExecutor::class)->execute('grade_submission', ['score' => 100], [
            'roles' => ['intern'],
            'intern_user_id' => $built['user']->id,
        ]);
        $this->assertSame('grading_forbidden', $blocked['error']);
        $this->assertSame(1, InternshipGrade::count());
    }

    public function test_supervisor_handover_assigns_supervisor_and_stops_ai()
    {
        $supervisor = User::create([
            'name' => 'Supervisor', 'email' => 'sup@example.test', 'password' => bcrypt('x'),
            'phone' => '675510099', 'role_id' => 1, 'is_active' => true, 'is_deleted' => false,
        ]);
        $built = $this->intern('675510023', 'Help Task');
        $built['enrolment']->supervisor_id = $supervisor->id;
        $built['enrolment']->save();
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $this->postWebhook($this->incomingText('+237675510023', 'I need my supervisor', 'P5HELP'))->assertStatus(200);
        $conversation = WhatsAppConversation::first();
        $this->assertSame(WhatsAppConversation::MODE_HUMAN, $conversation->mode);
        $this->assertSame($supervisor->id, (int) $conversation->assigned_user_id);
        $this->assertSame(0, InternshipGrade::count());
    }

    public function test_customer_cannot_call_internship_tools()
    {
        $result = app(AssistantToolExecutor::class)->execute('get_current_internship_task', [], ['roles' => ['customer']]);
        $this->assertSame('identity_required', $result['error']);
        $result = app(AssistantToolExecutor::class)->execute('submit_internship_work', [], ['roles' => ['user']]);
        $this->assertSame('identity_required', $result['error']);
    }

    public function test_duplicate_webhook_does_not_double_submit()
    {
        $this->intern('675510024', 'Dup Task');
        $this->enableAssistant();
        WhatsAppSetting::putValue('default_conversation_mode', 'AI');
        $payload = $this->incomingText('+237675510024', 'What is my current task?', 'P5DUP');
        $this->postWebhook($payload)->assertStatus(200);
        $this->postWebhook($payload)->assertStatus(200);
        $this->assertSame(1, WhatsAppMessage::where('sender_type', 'ASSISTANT')->count());
        $this->assertSame(0, InternshipSubmission::count());
    }

    public function test_ops_page_diagnostics_and_prune_keep_submission_files()
    {
        $this->assertNotNull(app('router')->getRoutes()->getByName('whatsapp.internship'));
        $this->assertStringContainsString('Internship operations', file_get_contents(resource_path('views/whatsapp_hub/internship.blade.php')));
        $admin = $this->makeUser(1, '675510030');
        $this->grantWhatsApp(Role::find(1));
        $this->actingAs($admin);

        $limitedRole = Role::find(3);
        $dummy = \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'customers-index', 'guard_name' => 'web']);
        try {
            $limitedRole->givePermissionTo($dummy);
        } catch (\Exception $e) {
        }
        $limited = $this->makeUser(3, '675510031');
        $this->actingAs($limited);
        $this->get('/admin/whatsapp/internship')->assertRedirect();
        $this->assertTrue(session()->has('not_permitted'));

        $diag = app(\App\Services\WhatsApp\WhatsAppHubQuery::class)->diagnostics();
        $this->assertArrayHasKey('internship', $diag);
        $stats = app(\App\Services\WhatsApp\WhatsAppHubQuery::class)->commandCenter([
            'from' => Carbon::now()->startOfDay(),
            'to' => Carbon::now()->endOfDay(),
        ]);
        $this->assertArrayHasKey('intern_submissions', $stats);

        $built = $this->conversationIntern('675510032', 'Keep Task');
        $path = $this->tempFile('keep.pdf', "%PDF-1.4\nkeep");
        $service = app(InternshipWhatsAppService::class);
        $service->prepare($built['context'], [
            'local_path' => $path,
            'file_name' => 'keep.pdf',
            'mime' => 'application/pdf',
        ]);
        $service->confirm($built['context'], ['text' => 'yes']);
        $stored = InternshipSubmissionFile::first();
        $full = storage_path('app/'.$stored->path);
        $this->assertFileExists($full);
        WhatsAppWebhookEvent::create([
            'provider' => 'wasender',
            'provider_event_id' => 'old-event',
            'fingerprint' => 'old-event',
            'event_type' => 'messages.received',
            'status' => WhatsAppWebhookEvent::PROCESSED,
            'received_at' => Carbon::now()->subDays(90),
            'payload' => '{}',
        ]);
        $this->artisan('whatsapp:prune-webhooks', ['--days' => 30]);
        $this->assertSame(0, WhatsAppWebhookEvent::count());
        $this->assertFileExists($full);
        $this->assertSame(1, InternshipSubmission::count());
    }

    public function test_intent_router_recognises_internship_phrases()
    {
        $router = app(AssistantIntentRouter::class);
        $this->assertSame('INTERNSHIP_SUBMIT', $router->deterministic('I want to submit my work')['intent']);
        $this->assertSame('INTERNSHIP_MATERIAL', $router->deterministic('Send the instructions')['intent']);
        $this->assertSame('INTERNSHIP_TASK', $router->deterministic("What is tomorrow's task?")['intent']);
        $this->assertSame('INTERNSHIP_STATUS', $router->deterministic('Did I pass?')['intent']);
        $this->assertSame('HUMAN_REQUEST', $router->deterministic("I don't understand this assignment")['intent']);
        $this->assertSame('HUMAN_REQUEST', $router->deterministic('I disagree with my grade')['intent']);
    }

    protected function enableAssistant()
    {
        config(['assistant.enabled' => true, 'assistant.provider' => 'null']);
        WhatsAppSetting::putValue('assistant_enabled', '1');
    }

    protected function intern($phone, $title, $requirements = 'PDF of the finished work')
    {
        $user = $this->user($phone);
        $program = $this->program('Programme '.$phone);
        $task = InternshipProgramTask::create([
            'program_id' => $program->id,
            'day_number' => 1,
            'title' => $title,
            'instructions_json' => json_encode(['Open the brief', 'Save a PDF']),
            'submission_requirements' => $requirements,
        ]);
        $enrolment = InternshipEnrolment::create([
            'student_user_id' => $user->id,
            'program_id' => $program->id,
            'status' => 'active',
            'planned_duration_days' => 10,
            'completed_count' => 0,
            'start_curriculum_day' => 1,
        ]);
        $assignment = InternshipTaskAssignment::create([
            'enrolment_id' => $enrolment->id,
            'program_task_id' => $task->id,
            'progression_day' => 1,
            'status' => 'available',
            'released_at' => now(),
            'attempt_count' => 0,
        ]);

        return compact('user', 'program', 'task', 'enrolment', 'assignment');
    }

    protected function conversationIntern($phone, $title, $requirements = 'PDF of the finished work')
    {
        $built = $this->intern($phone, $title, $requirements);
        $contact = \App\WhatsApp\WhatsAppContact::create([
            'normalized_phone' => '237'.$phone,
            'display_phone' => '+237'.$phone,
        ]);
        $conversation = WhatsAppConversation::create([
            'contact_id' => $contact->id,
            'mode' => WhatsAppConversation::MODE_AI,
            'status' => WhatsAppConversation::STATUS_OPEN,
        ]);
        $built['conversation'] = $conversation;
        $built['context'] = [
            'conversation' => $conversation,
            'conversation_id' => $conversation->id,
            'intern_user_id' => $built['user']->id,
            'roles' => ['intern', 'user'],
        ];

        return $built;
    }

    protected function user($phone)
    {
        return User::create([
            'name' => 'Intern '.$phone,
            'email' => $phone.'@example.test',
            'password' => bcrypt('x'),
            'phone' => $phone,
            'role_id' => 1,
            'is_active' => true,
            'is_deleted' => false,
        ]);
    }

    protected function program($name)
    {
        return InternshipProgram::create([
            'code' => 'STAGE5',
            'name' => $name,
            'status' => 'active',
            'is_active' => true,
        ]);
    }

    protected function tempFile($name, $contents)
    {
        $dir = storage_path('app/testing-intake');
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $path = $dir.'/'.uniqid('', true).'-'.$name;
        file_put_contents($path, $contents);

        return $path;
    }

    protected function latestAssistant()
    {
        $message = WhatsAppMessage::where('sender_type', 'ASSISTANT')->orderByDesc('id')->first();
        $this->assertNotNull($message);

        return (string) $message->body;
    }

    protected function extraTables()
    {
        if (! Schema::hasColumn('internship_enrolments', 'program_id')) {
            Schema::table('internship_enrolments', function (Blueprint $table) {
                $table->unsignedInteger('program_id')->nullable();
                $table->unsignedInteger('supervisor_id')->nullable();
                $table->date('start_date')->nullable();
                $table->unsignedInteger('planned_duration_days')->nullable();
                $table->unsignedInteger('start_curriculum_day')->nullable();
                $table->unsignedInteger('completed_count')->default(0);
            });
        }
        if (! Schema::hasTable('internship_programs')) {
            Schema::create('internship_programs', function (Blueprint $table) {
                $table->increments('id');
                $table->string('code')->nullable();
                $table->string('name')->nullable();
                $table->string('status')->nullable();
                $table->boolean('is_active')->default(true);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_program_tasks')) {
            Schema::create('internship_program_tasks', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('program_id')->nullable();
                $table->unsignedInteger('day_number')->nullable();
                $table->string('title')->nullable();
                $table->text('instructions_json')->nullable();
                $table->text('submission_requirements')->nullable();
                $table->text('evidence_slots_json')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_task_assignments')) {
            Schema::create('internship_task_assignments', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('enrolment_id')->nullable();
                $table->unsignedInteger('program_task_id')->nullable();
                $table->unsignedInteger('progression_day')->nullable();
                $table->date('scheduled_work_date')->nullable();
                $table->timestamp('released_at')->nullable();
                $table->string('status')->nullable();
                $table->unsignedInteger('attempt_count')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_submissions')) {
            Schema::create('internship_submissions', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('assignment_id')->nullable();
                $table->unsignedInteger('student_user_id')->nullable();
                $table->unsignedInteger('attempt_no')->nullable();
                $table->text('description')->nullable();
                $table->timestamp('submitted_at')->nullable();
                $table->string('status')->nullable();
                $table->string('source')->nullable();
                $table->unsignedInteger('whatsapp_conversation_id')->nullable();
                $table->text('whatsapp_message_ids')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_submission_files')) {
            Schema::create('internship_submission_files', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('submission_id')->nullable();
                $table->string('disk')->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->string('checksum')->nullable();
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_draft_files')) {
            Schema::create('internship_draft_files', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('assignment_id')->nullable();
                $table->unsignedInteger('student_user_id')->nullable();
                $table->unsignedInteger('slot_index')->default(0);
                $table->string('disk')->nullable();
                $table->string('path')->nullable();
                $table->string('original_name')->nullable();
                $table->string('mime')->nullable();
                $table->unsignedInteger('size')->default(0);
                $table->timestamps();
            });
        }
        if (! Schema::hasTable('internship_grades')) {
            Schema::create('internship_grades', function (Blueprint $table) {
                $table->increments('id');
                $table->unsignedInteger('submission_id')->nullable();
                $table->unsignedInteger('grader_id')->nullable();
                $table->integer('score')->nullable();
                $table->text('feedback')->nullable();
                $table->string('decision')->nullable();
                $table->timestamp('graded_at')->nullable();
                $table->timestamps();
            });
        }
    }

    protected function incomingText($phone, $body, $id)
    {
        $digits = preg_replace('/\D/', '', $phone);

        return [
            'event' => 'messages.received',
            'timestamp' => time(),
            'data' => [
                'messages' => [
                    'key' => [
                        'id' => $id,
                        'fromMe' => false,
                        'remoteJid' => $digits.'@s.whatsapp.net',
                        'cleanedSenderPn' => $digits,
                    ],
                    'messageBody' => $body,
                    'message' => ['conversation' => $body],
                ],
            ],
        ];
    }

    protected function fakeProvider()
    {
        return new class implements WhatsAppProviderInterface {
            public function sendText($phone, $message)
            {
                return ['success' => true, 'msg_id' => 'P5'];
            }

            public function sendDocument($phone, $localPath, $fileName = null, $caption = null)
            {
                return ['success' => true];
            }

            public function sendImage($phone, $localPath, $caption = null)
            {
                return ['success' => true];
            }

            public function sessionStatus()
            {
                return ['connected' => true, 'status' => 'CONNECTED', 'session_name' => 'test', 'configured' => true];
            }

            public function isConfigured()
            {
                return true;
            }
        };
    }
}
