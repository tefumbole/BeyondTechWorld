<?php

namespace App\Services\Internship;

use App\InternshipEnrolment;
use App\InternshipSupervisorMessage;
use App\Services\BeyondWasenderService;
use App\User;
use Illuminate\Support\Str;
use RuntimeException;

class InternSupervisorChat
{
    protected $whatsapp;

    public function __construct(BeyondWasenderService $whatsapp)
    {
        $this->whatsapp = $whatsapp;
    }

    public function sendFromStudent(User $student, InternshipEnrolment $enrolment, array $supervisor, $body)
    {
        $body = trim((string) $body);
        if ($body === '') {
            throw new RuntimeException('Write a message before sending.');
        }

        $supPhone = $this->digits($supervisor['phone'] ?? '');
        $studentPhone = $this->digits($student->phone ?: ($student->additional_phone ?? ''));
        if (strlen($supPhone) < 8) {
            throw new RuntimeException('Your supervisor has no WhatsApp number on file.');
        }
        if (strlen($studentPhone) < 8) {
            throw new RuntimeException('Your account has no WhatsApp number, so a copy cannot be sent to you.');
        }

        $row = InternshipSupervisorMessage::create([
            'enrolment_id' => $enrolment->id,
            'student_user_id' => $student->id,
            'supervisor_name' => $supervisor['name'] ?? 'Supervisor',
            'supervisor_phone' => $supervisor['phone'] ?? '',
            'body' => $body,
            'reply_token' => Str::random(48),
        ]);

        $program = optional($enrolment->program)->displayName() ?: 'Internship';
        $replyUrl = url('/internship/supervisor-reply/'.$row->reply_token);

        $toSupervisor = "Beyond Enterprise — Internship message\n\n"
            ."From: {$student->name} (Intern)\n"
            ."Program: {$program}\n\n"
            ."{$body}\n\n"
            ."Reply using this link (do not reply in this chat):\n{$replyUrl}\n\n"
            .'A copy of this message was also sent to the intern on WhatsApp.';

        $toStudent = "Copy of your message to ".($supervisor['name'] ?: 'your supervisor').":\n\n"
            ."{$body}\n\n"
            .'They will reply from the link we sent them. You will receive their reply here on WhatsApp.';

        $supSend = $this->whatsapp->sendText($supPhone, $toSupervisor);
        $stuSend = $this->whatsapp->sendText($studentPhone, $toStudent);

        if (empty($supSend['success'])) {
            throw new RuntimeException($supSend['error'] ?? 'Could not send WhatsApp to the supervisor.');
        }
        if (empty($stuSend['success'])) {
            throw new RuntimeException($stuSend['error'] ?? 'Sent to supervisor, but the copy to you failed.');
        }

        return $row;
    }

    public function replyFromSupervisor(InternshipSupervisorMessage $row, $reply)
    {
        $reply = trim((string) $reply);
        if ($reply === '') {
            throw new RuntimeException('Write a reply before sending.');
        }
        if ($row->isReplied()) {
            throw new RuntimeException('This message already has a reply.');
        }

        $student = $row->student ?: User::find($row->student_user_id);
        if (! $student) {
            throw new RuntimeException('Intern account was not found.');
        }
        $studentPhone = $this->digits($student->phone ?: ($student->additional_phone ?? ''));
        $supPhone = $this->digits($row->supervisor_phone);
        if (strlen($studentPhone) < 8) {
            throw new RuntimeException('The intern has no WhatsApp number on file.');
        }

        $toStudent = 'Reply from your supervisor '.($row->supervisor_name ?: 'Supervisor').":\n\n"
            ."{$reply}\n\n"
            .'You can write them again from Internships → Message supervisor.';

        $toSupervisor = "Copy of your reply to {$student->name}:\n\n{$reply}";

        $stuSend = $this->whatsapp->sendText($studentPhone, $toStudent);
        if (empty($stuSend['success'])) {
            throw new RuntimeException($stuSend['error'] ?? 'Could not send the reply to the intern.');
        }

        $row->reply_body = $reply;
        $row->replied_at = now();
        $row->save();

        if (strlen($supPhone) >= 8) {
            $this->whatsapp->sendText($supPhone, $toSupervisor);
        }

        return $row;
    }

    protected function digits($phone)
    {
        return preg_replace('/\D+/', '', (string) $phone);
    }
}
