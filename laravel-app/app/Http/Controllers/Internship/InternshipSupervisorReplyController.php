<?php

namespace App\Http\Controllers\Internship;

use App\Http\Controllers\Controller;
use App\InternshipSupervisorMessage;
use App\Services\Internship\InternSupervisorChat;
use Illuminate\Http\Request;

class InternshipSupervisorReplyController extends Controller
{
    public function show($token)
    {
        $message = InternshipSupervisorMessage::where('reply_token', $token)->firstOrFail();
        $student = $message->student;

        return view('internship.public.supervisor_reply', compact('message', 'student'));
    }

    public function store(Request $request, $token, InternSupervisorChat $chat)
    {
        $message = InternshipSupervisorMessage::where('reply_token', $token)->firstOrFail();
        $data = $request->validate([
            'reply' => 'required|string|min:2|max:2000',
        ]);

        try {
            $chat->replyFromSupervisor($message, $data['reply']);
        } catch (\Throwable $e) {
            return back()->withInput()->with('not_permitted', $e->getMessage());
        }

        return redirect()->route('internship.supervisor.reply', $token)
            ->with('message', 'Reply sent. The intern received a WhatsApp copy, and you received a copy too.');
    }
}
