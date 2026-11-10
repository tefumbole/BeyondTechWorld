<?php

namespace App\Http\Controllers;

use App\EventAssignment;
use App\Services\CampayPayoutService;
use App\Services\EventCrewInviteService;
use App\Services\MobileMoneyHolderService;
use Illuminate\Http\Request;

class EventCrewController extends Controller
{
    public function show(Request $request, $token)
    {
        $assignment = $this->assignment($token);
        $step = 'accept';
        if ($assignment->acceptance_status === 'rejected') {
            $step = 'declined';
        } elseif ($assignment->acceptance_status === 'accepted') {
            $step = 'expired';
        } elseif ($request->query('choose') === '1') {
            $request->session()->forget('event_otp_for');
            $step = 'accept';
        } elseif ($request->session()->get('event_otp_for') === $token) {
            $step = 'otp';
        }

        return view('events.crew.invite', $this->inviteView($assignment, $step));
    }

    public function reject($token, EventCrewInviteService $invites)
    {
        $assignment = $this->assignment($token);
        $invites->rejectJob($assignment);

        return view('events.crew.invite', $this->inviteView($assignment->fresh(), 'declined'))
            ->with('message', 'You have rejected this job. Your name is off the event and unpaid amounts for you are cancelled.');
    }

    public function rejectCurrent(EventCrewInviteService $invites)
    {
        $id = session('event_crew_assignment');
        $assignment = $id ? EventAssignment::with('event', 'workerProfile')->find($id) : null;
        if (! $assignment) {
            abort(403, 'Accept the event and enter the WhatsApp code first.');
        }
        $invites->rejectJob($assignment);
        session()->forget('event_crew_assignment');

        return view('events.crew.invite', $this->inviteView($assignment->fresh(), 'declined'))
            ->with('message', 'You have rejected this job. Your name is off the event and unpaid amounts for you are cancelled.');
    }

    public function accept(Request $request, $token, EventCrewInviteService $invites)
    {
        $assignment = $this->assignment($token);
        if ($assignment->acceptance_status === 'accepted') {
            return view('events.crew.invite', $this->inviteView($assignment, 'expired'));
        }
        $offeredEntries = $invites->offeredDayEntries($assignment);
        $offered = array();
        foreach ($offeredEntries as $day) {
            $offered[] = $day['date'];
        }
        $contract = $assignment->contract()->whereNotIn('status', [\App\EventContract::STATUS_CANCELLED])->first();
        if ($contract && ! in_array($contract->status, [\App\EventContract::STATUS_WORKER_SIGNED, \App\EventContract::STATUS_APPROVED], true)) {
            $signature = (string) $request->input('signature_image');
            if (strpos($signature, 'data:image/png') !== 0) {
                return back()->withErrors(['signature' => 'Sign the contract before you accept.']);
            }
            $request->session()->put($this->signatureKey($token), $signature);
        }
        $acceptAll = $request->input('accept_all') === '1' || ! count($offered);
        if ($acceptAll) {
            $chosen = $offered;
        } else {
            $posted = is_array($request->input('days')) ? $request->input('days') : array();
            $chosen = array_values(array_intersect($offered, $posted));
            if (! count($chosen)) {
                return back()->withErrors(['days' => 'Choose at least one day, or reject the job.']);
            }
        }
        $request->session()->put($this->daysKey($token), $chosen);
        $sent = $invites->sendLoginCode($assignment);
        if (empty($sent['ok'])) {
            return back()->withErrors(['otp' => $sent['message']]);
        }
        $request->session()->put($this->otpKey($token), [
            'code' => $sent['code'],
            'until' => time() + 600,
        ]);
        $request->session()->put('event_otp_for', $token);

        return redirect()->route('events.crew.invite', $token)
            ->with('message', 'A code is on its way to your WhatsApp.');
    }

    public function verify(Request $request, $token, EventCrewInviteService $invites)
    {
        $assignment = $this->assignment($token);
        if ($assignment->acceptance_status === 'accepted') {
            return view('events.crew.invite', $this->inviteView($assignment, 'expired'));
        }
        $request->validate(['otp' => 'required|string|min:6|max:12']);
        $stored = $request->session()->get($this->otpKey($token));
        $given = preg_replace('/\D+/', '', (string) $request->input('otp'));
        $expected = is_array($stored) ? preg_replace('/\D+/', '', (string) $stored['code']) : '';
        $fresh = is_array($stored) && (int) $stored['until'] >= time();
        if (! $fresh || $expected === '' || strlen($expected) !== strlen($given) || ! hash_equals($expected, $given)) {
            return redirect()->route('events.crew.invite', $token)
                ->withErrors(['otp' => 'That code is not valid. Enter it again.']);
        }
        $chosen = $request->session()->get($this->daysKey($token));
        if (is_array($chosen) && count($chosen)) {
            $invites->keepDays($assignment, $chosen);
            $assignment = $assignment->fresh();
        }
        $invites->markAccepted($assignment);
        $this->signAttachedContract($assignment->fresh(), $request->session()->pull($this->signatureKey($token)));
        $request->session()->forget($this->otpKey($token));
        $request->session()->forget($this->daysKey($token));
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
            'workDates' => $invites->workDates($assignment),
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
            'setupText' => $invites->setupText($assignment->event),
            'workDates' => $invites->workDates($assignment),
            'dayChoices' => $this->dayChoices($assignment, $invites),
            'engineerName' => $assignment->workerProfile ? $assignment->workerProfile->displayName() : 'Engineer',
            'contract' => $assignment->contract()->whereNotIn('status', [\App\EventContract::STATUS_CANCELLED])->first(),
        ];
    }

    protected function signatureKey($token)
    {
        return 'event_accept_signature.'.sha1($token);
    }

    protected function signAttachedContract(EventAssignment $assignment, $signature)
    {
        if (! is_string($signature) || strpos($signature, 'data:image/png') !== 0) {
            return;
        }
        $contract = $assignment->contract()->whereNotIn('status', [\App\EventContract::STATUS_CANCELLED])->first();
        if (! $contract || in_array($contract->status, [\App\EventContract::STATUS_WORKER_SIGNED, \App\EventContract::STATUS_APPROVED], true)) {
            return;
        }
        $contracts = app(\App\Services\EventContractService::class);
        $contracts->workerSign($contract, $signature);
        $contracts->finishSigned($contract->fresh());
    }

    protected function dayChoices(EventAssignment $assignment, EventCrewInviteService $invites)
    {
        $daily = $invites->dayAmount($assignment);
        $rows = array();
        foreach ($invites->offeredDayEntries($assignment) as $day) {
            $amount = $day['pay_amount'] !== null ? (int) $day['pay_amount'] : $daily;
            $rows[] = array(
                'date' => $day['date'],
                'label' => date('d M Y', strtotime($day['date'])),
                'amount' => $amount,
            );
        }

        return $rows;
    }

    protected function daysKey($token)
    {
        return 'event_accept_days.'.sha1($token);
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
