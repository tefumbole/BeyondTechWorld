<?php

namespace App\Http\Controllers;

use App\EventAssignment;
use App\Services\CampayPayoutService;
use App\Services\EventCrewInviteService;
use App\Services\MobileMoneyHolderService;
use Illuminate\Http\Request;

class EventCrewController extends Controller
{
    public function show($token)
    {
        $assignment = $this->assignment($token);

        return view('events.crew.invite', $this->inviteView($assignment, 'accept'));
    }

    public function accept(Request $request, $token, EventCrewInviteService $invites)
    {
        $assignment = $this->assignment($token);
        $sent = $invites->sendLoginCode($assignment);
        if (empty($sent['ok'])) {
            return back()->withErrors(['otp' => $sent['message']]);
        }
        $request->session()->put($this->otpKey($token), [
            'code' => $sent['code'],
            'until' => time() + 600,
        ]);

        return view('events.crew.invite', $this->inviteView($assignment, 'otp'))
            ->with('message', 'A code is on its way to your WhatsApp.');
    }

    public function verify(Request $request, $token, EventCrewInviteService $invites)
    {
        $assignment = $this->assignment($token);
        $request->validate(['otp' => 'required|string|min:6|max:8']);
        $stored = $request->session()->get($this->otpKey($token));
        $given = preg_replace('/\D+/', '', (string) $request->input('otp'));
        $expected = is_array($stored) ? preg_replace('/\D+/', '', (string) $stored['code']) : '';
        $fresh = is_array($stored) && (int) $stored['until'] >= time();
        if (! $fresh || $expected === '' || ! hash_equals($expected, $given)) {
            return view('events.crew.invite', $this->inviteView($assignment, 'otp'))
                ->withErrors(['otp' => 'That code is not valid. Accept again to receive a new one.']);
        }
        $invites->markAccepted($assignment);
        $request->session()->forget($this->otpKey($token));
        $request->session()->put('event_crew_assignment', $assignment->id);

        return redirect()->route('events.crew.home');
    }

    public function home(EventCrewInviteService $invites)
    {
        $assignment = $this->current();
        $assignment->load('timesheets.entries', 'payments', 'event', 'workerProfile.customer');

        return view('events.crew.home', [
            'assignment' => $assignment,
            'event' => $assignment->event,
            'phone' => $invites->phoneFor($assignment),
            'dailyRate' => $invites->dailyRate($assignment),
            'foodAmount' => $invites->foodAmount($assignment),
            'dayAmount' => $invites->dayAmount($assignment),
            'entries' => $assignment->timesheets->flatMap(function ($sheet) {
                return $sheet->entries;
            }),
        ]);
    }

    public function submitDay(Request $request, EventCrewInviteService $invites, CampayPayoutService $campay, MobileMoneyHolderService $holders)
    {
        $assignment = $this->current();
        $data = $request->validate([
            'work_date' => 'required|date',
            'activity' => 'required|string|max:2000',
            'hours' => 'nullable|numeric|min:0.5|max:24',
            'phone' => 'required|string|max:32',
        ]);
        $phone = $campay->momoNumber($data['phone']);
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Enter an MTN or Orange Cameroon number.']);
        }
        $current = $invites->phoneFor($assignment);
        $same = substr(preg_replace('/\D+/', '', (string) $current), -9) === substr($phone, -9);
        if (! $same) {
            $hit = $holders->lookup($phone, false, false);
            $source = isset($hit['source']) ? $hit['source'] : '';
            if (empty($hit['name']) || ! in_array($source, ['campay', 'pawapay'], true)) {
                return back()->withInput()->withErrors(['phone' => 'Campay has no registered name for that number.']);
            }
        }
        try {
            $payment = $invites->submitDay(
                $assignment,
                $data['work_date'],
                trim($data['activity']),
                isset($data['hours']) ? $data['hours'] : 8,
                $phone
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['activity' => $e->getMessage()]);
        } catch (\Exception $e) {
            return back()->withInput()->withErrors(['activity' => 'The timesheet was not submitted.']);
        }

        return redirect()->route('events.crew.home')->with('message', 'Submitted for '.$payment->amount.' XAF. It is with the payment requests.');
    }

    protected function inviteView(EventAssignment $assignment, $step)
    {
        $invites = app(EventCrewInviteService::class);

        return [
            'assignment' => $assignment,
            'event' => $assignment->event,
            'step' => $step,
            'masked' => $this->mask($assignment),
            'dailyRate' => $invites->dailyRate($assignment),
            'foodAmount' => $invites->foodAmount($assignment),
        ];
    }

    protected function assignment($token)
    {
        return EventAssignment::with('event', 'workerProfile.customer', 'workerProfile.user')
            ->where('invite_token', $token)
            ->firstOrFail();
    }

    protected function current()
    {
        $id = session('event_crew_assignment');
        $assignment = $id ? EventAssignment::with('event', 'workerProfile')->find($id) : null;
        if (! $assignment || $assignment->acceptance_status !== 'accepted') {
            abort(403, 'Accept the event and enter the WhatsApp code first.');
        }

        return $assignment;
    }

    protected function otpKey($token)
    {
        return 'event_invite_otp.'.sha1($token);
    }

    protected function mask(EventAssignment $assignment)
    {
        $phone = app(EventCrewInviteService::class)->phoneFor($assignment);
        if (strlen($phone) < 4) {
            return 'your WhatsApp';
        }

        return '•••• '.substr($phone, -4);
    }
}
