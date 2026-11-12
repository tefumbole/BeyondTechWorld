<?php

namespace App\Http\Controllers;

use App\Application;
use App\Services\CampayPayoutService;
use App\Support\PaidInternship;
use Illuminate\Http\Request;

class InternshipFeeController extends Controller
{
    public function show($reference)
    {
        $application = $this->application($reference);
        if ($application->internship_fee_status === 'paid') {
            return redirect()->route('apply.confirmation', $application->reference_number);
        }

        return view('beyond.apply.internship_pay', [
            'application' => $application,
            'fee' => (int) $application->internship_fee_amount,
            'phone' => $application->whatsapp_number ?: $application->phone,
        ]);
    }

    public function start(Request $request, $reference, CampayPayoutService $campay)
    {
        $application = $this->application($reference);
        if ($application->internship_fee_status === 'paid') {
            return redirect()->route('apply.confirmation', $application->reference_number)
                ->with('message', 'This paid internship is already paid.');
        }
        $amount = (int) $application->internship_fee_amount;
        if ($amount < 100) {
            $amount = PaidInternship::price($application->internship_duration_days);
            $application->internship_fee_amount = $amount;
        }
        $phone = $campay->momoNumber($request->input('momo_phone') ?: ($application->whatsapp_number ?: $application->phone));
        if (! $phone) {
            return back()->with('not_permitted', 'Enter an MTN or Orange Cameroon number. Nothing was charged.');
        }
        $external = 'intern-'.substr(str_replace('-', '', $application->id), 0, 16).'-'.date('His');
        $body = $campay->collect($phone, $amount, $external, 'Paid internship '.$application->reference_number);
        $campayStatus = strtoupper((string) (is_array($body) && isset($body['status']) ? $body['status'] : ''));
        $campayReference = is_array($body) && ! empty($body['reference']) ? (string) $body['reference'] : '';
        if ($campayReference === '' || $campayStatus === 'FAILED') {
            $application->internship_fee_status = 'pending';
            $application->save();

            return back()->with('not_permitted', 'Mobile Money did not send a prompt. Nothing was charged. You can try again.');
        }
        $application->internship_fee_amount = $amount;
        $application->internship_fee_status = 'pending';
        $application->internship_fee_reference = $campayReference;
        $application->internship_fee_external = $external;
        $application->save();
        \Illuminate\Support\Facades\Cache::put($this->cacheKey($application), [
            'amount' => $amount,
            'phone' => $phone,
            'reference' => $campayReference,
            'external' => $external,
        ], now()->addMinutes(30));

        return redirect()->route('apply.internship.pay', $application->reference_number)
            ->with('message', 'A MoMo prompt was sent for '.PaidInternship::money($amount).'. Approve it on the phone.');
    }

    public function status($reference, CampayPayoutService $campay)
    {
        $application = $this->application($reference);
        if ($application->internship_fee_status === 'paid') {
            return response()->json(['status' => 'paid', 'url' => route('apply.confirmation', $application->reference_number)]);
        }
        $pending = \Illuminate\Support\Facades\Cache::get($this->cacheKey($application));
        if (! is_array($pending) && $application->internship_fee_reference) {
            $pending = [
                'amount' => (int) $application->internship_fee_amount,
                'phone' => null,
                'reference' => $application->internship_fee_reference,
                'external' => $application->internship_fee_external,
            ];
        }
        if (! is_array($pending) || empty($pending['reference'])) {
            return response()->json(['status' => 'waiting']);
        }
        $pattern = '/^'.preg_quote((string) $pending['external'], '/').'$/';
        $outcome = $campay->collectionOutcome($pending['reference'], $pending['amount'], isset($pending['phone']) ? $pending['phone'] : null, $pattern);
        if ($outcome['state'] === 'paid' && ! empty($outcome['confirmed'])) {
            $application->internship_fee_status = 'paid';
            $application->save();
            \Illuminate\Support\Facades\Cache::forget($this->cacheKey($application));

            return response()->json(['status' => 'paid', 'url' => route('apply.confirmation', $application->reference_number)]);
        }
        if ($outcome['state'] === 'failed') {
            $application->internship_fee_status = 'pending';
            $application->save();

            return response()->json([
                'status' => 'failed',
                'message' => 'The phone did not approve the payment. Nothing was marked paid. You can send the prompt again.',
            ]);
        }

        return response()->json(['status' => 'pending']);
    }

    protected function application($reference)
    {
        $application = Application::where('reference_number', $reference)->first();
        if (! $application || ! $application->job || ! $application->job->isInternship()) {
            abort(404);
        }

        return $application;
    }

    protected function cacheKey(Application $application)
    {
        return 'internship-fee:'.$application->id;
    }
}
