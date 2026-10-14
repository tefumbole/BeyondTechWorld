<?php

namespace App\Http\Controllers;

use App\CaroInvitation;
use App\Services\CaroInvitationService;
use App\Support\CaroSeats;
use App\Support\CountryDialCodes;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CaroInvitationController extends Controller
{
    protected $invitations;

    public function __construct(CaroInvitationService $invitations)
    {
        $this->invitations = $invitations;
    }

    public function index()
    {
        return view('beyond.caro.index', $this->formData());
    }

    public function start(Request $request)
    {
        $guest = $this->guestFrom($request);
        if ($guest instanceof \Illuminate\Http\RedirectResponse) {
            return $guest;
        }
        $existing = $this->invitations->findByPhone($guest['phone']);
        if ($existing && is_file($existing->imagePath())) {
            return redirect($this->invitations->pageUrl('card/'.$existing->id).'?existing=1');
        }

        return $this->seats($guest, null);
    }

    protected function seats(array $guest, $error)
    {
        return view('beyond.caro.seats', array_merge($this->formData(), [
            'guest' => $guest,
            'taken' => $this->invitations->takenCodes(),
            'rows' => range(1, CaroSeats::ROWS),
            'columns' => CaroSeats::COLUMNS,
            'error' => $error,
        ]));
    }

    public function claim(Request $request)
    {
        $guest = $this->guestFrom($request);
        if ($guest instanceof \Illuminate\Http\RedirectResponse) {
            return $guest;
        }
        $seat = strtoupper(trim((string) $request->input('seat_code', '')));
        if ($seat === '') {
            return $this->seats($guest, 'Choose a seat.');
        }

        try {
            $row = $this->invitations->claim($guest['phone'], $guest['name'], $guest['role'], $seat);
        } catch (\InvalidArgumentException $e) {
            return $this->seats($guest, $e->getMessage());
        } catch (\Throwable $e) {
            Log::warning('caro invitation failed: '.$e->getMessage());

            return $this->seats($guest, 'Could not build the invitation. Please try again.');
        }

        return redirect($this->invitations->pageUrl('card/'.$row->id));
    }

    public function card($id)
    {
        $row = CaroInvitation::find($id);
        if (! $row || ! is_file($row->imagePath())) {
            abort(404);
        }

        return view('beyond.caro.card', [
            'invitation' => $row,
            'existing' => request('existing') === '1',
            'downloadUrl' => $this->invitations->pageUrl('card/'.$row->id.'/download'),
            'homeUrl' => $this->invitations->pageUrl(),
        ]);
    }

    public function download($id)
    {
        $row = CaroInvitation::find($id);
        if (! $row || ! is_file($row->imagePath())) {
            abort(404);
        }
        $safe = trim(preg_replace('/[^A-Za-z0-9]+/', '-', $row->name), '-');
        if ($safe === '') {
            $safe = 'invitation';
        }

        return response()->download($row->imagePath(), $safe.'-'.$row->seat_code.'.jpg', [
            'Content-Type' => 'image/jpeg',
        ]);
    }

    public function pass($id)
    {
        $row = CaroInvitation::find($id);
        $error = $row ? null : 'This invitation could not be verified.';

        return view('beyond.caro.pass', [
            'invitation' => $row,
            'error' => $error,
        ]);
    }

    protected function formData()
    {
        return [
            'countries' => CountryDialCodes::list(),
            'roles' => CaroSeats::roles(),
            'homeUrl' => $this->invitations->pageUrl(),
            'startUrl' => $this->invitations->pageUrl('start'),
            'claimUrl' => $this->invitations->pageUrl('seat'),
            'lookupUrl' => $this->invitations->pageUrl('lookup'),
            'serviceAt' => '2026-11-08T13:00:00+01:00',
        ];
    }

    protected function guestFrom(Request $request)
    {
        $data = $request->validate([
            'country_code' => 'required|string|max:10',
            'phone' => 'required|string|max:40',
            'name' => 'required|string|max:80',
            'role' => 'required|in:guest,pastor,guest_minister,gospel_minister',
        ]);
        try {
            $phone = WhatsAppPhone::combine($data['country_code'], $data['phone']);
        } catch (\Throwable $e) {
            return redirect($this->invitations->pageUrl())->withInput()->with('not_permitted', 'Enter a valid WhatsApp number.');
        }
        if (strlen(preg_replace('/\D/', '', $phone)) < 8) {
            return redirect($this->invitations->pageUrl())->withInput()->with('not_permitted', 'Enter a valid WhatsApp number.');
        }

        return [
            'phone' => $phone,
            'name' => trim($data['name']),
            'role' => $data['role'],
            'country_code' => $data['country_code'],
            'local_phone' => trim($data['phone']),
        ];
    }

}
