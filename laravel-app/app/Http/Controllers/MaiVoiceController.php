<?php

namespace App\Http\Controllers;

use App\Services\Voice\MaiVoiceStatus;
use App\Services\Voice\MaiVoiceSwitch;
use App\Voice\VoiceCall;
use Illuminate\Http\Request;

class MaiVoiceController extends Controller
{
    public function index()
    {
        if (! $this->allowed()) {
            return response('Forbidden', 403);
        }

        return view('mai.voice', [
            'status' => app(MaiVoiceStatus::class)->snapshot(),
        ]);
    }

    public function show($id)
    {
        if (! $this->allowed()) {
            return response('Forbidden', 403);
        }
        $call = VoiceCall::find($id);
        if (! $call) {
            return response('Not found', 404);
        }

        return view('mai.voice_call', [
            'call' => $call,
            'transcript' => $call->transcriptArray(),
        ]);
    }

    public function switchVoice(Request $request)
    {
        if (! $this->allowed()) {
            return response('Forbidden', 403);
        }
        MaiVoiceSwitch::set($request->input('enabled') === '1');

        return redirect()->route('mai.voice');
    }

    protected function allowed()
    {
        $user = auth()->user();
        if (! $user) {
            return false;
        }

        return in_array((int) $user->role_id, [1, 2], true);
    }
}
