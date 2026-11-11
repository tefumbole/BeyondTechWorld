<?php

namespace App\Http\Controllers;

use App\CompanyReview;
use App\CompanyReviewInvite;
use App\DocumentPaymentLink;
use App\Services\PeopleDirectoryService;
use App\Support\MemorableOtp;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class CompanyReviewController extends Controller
{
    public function index()
    {
        $reviews = CompanyReview::where('published', 1)
            ->orderBy('id', 'desc')
            ->get();

        return view('beyond.reviews.index', [
            'reviews' => $reviews,
            'summary' => CompanyReview::publicSummary($reviews),
        ]);
    }

    public function create($token = null)
    {
        $invite = null;
        if ($token) {
            $invite = CompanyReviewInvite::where('token', $token)->first();
            if (! $invite) {
                abort(404);
            }
            $phone = $this->normalizePhone($invite->phone);
            $known = $phone ? $this->databaseName($phone) : '';
            session([
                'review_invite_id' => $invite->id,
                'review_skip_otp' => 1,
                'review_verified' => 1,
                'review_phone' => $phone ?: $invite->phone,
                'review_suggested_name' => $known !== '' ? $known : trim((string) $invite->person_name),
            ]);
        } elseif (session('review_skip_otp')) {
            session()->forget(['review_invite_id', 'review_skip_otp', 'review_verified', 'review_phone', 'review_suggested_name']);
        }

        return view('beyond.reviews.create', $this->formState($invite));
    }

    public function sendCode(Request $request)
    {
        if (session('review_skip_otp')) {
            return $this->backToForm();
        }

        $phone = $this->normalizePhone($request->input('phone'));
        if (! $phone) {
            return back()->withInput()->withErrors(['phone' => 'Enter a valid phone number.']);
        }

        $known = $this->databaseName($phone);
        $code = MemorableOtp::code();
        session([
            'review_phone' => $phone,
            'review_verified' => 0,
            'review_suggested_name' => $known,
            'review_otp_hash' => hash('sha256', $code),
            'review_otp_expires' => time() + 600,
            'review_otp_tries' => 0,
        ]);

        try {
            $sent = app(\App\Services\Messaging\NotificationRouter::class)->sendWhatsAppOtp($phone, $code, 'review', 10);
            if (empty($sent['success'])) {
                throw new \RuntimeException($sent['error'] ?? 'The code could not be sent.');
            }
        } catch (\Throwable $e) {
            return back()->withInput()->withErrors(['phone' => 'The code could not be sent. Check the number and try again.']);
        }

        return $this->backToForm()->with('message', 'A code was sent to your phone. Type it here.');
    }

    public function confirmCode(Request $request)
    {
        if (session('review_skip_otp')) {
            return $this->backToForm();
        }

        $hash = (string) session('review_otp_hash', '');
        $expires = (int) session('review_otp_expires', 0);
        $tries = (int) session('review_otp_tries', 0);
        if ($hash === '' || $expires < time()) {
            return $this->backToForm()->withErrors(['otp' => 'That code has expired. Request a new one.']);
        }
        if ($tries >= 5) {
            session()->forget(['review_otp_hash', 'review_otp_expires', 'review_otp_tries']);

            return $this->backToForm()->withErrors(['otp' => 'Too many tries. Request a new code.']);
        }

        $typed = preg_replace('/\D+/', '', (string) $request->input('otp'));
        if (strlen($typed) !== 6 || ! hash_equals($hash, hash('sha256', $typed))) {
            session(['review_otp_tries' => $tries + 1]);

            return $this->backToForm()->withErrors(['otp' => 'That code does not match.']);
        }

        session([
            'review_verified' => 1,
            'review_otp_hash' => null,
            'review_otp_expires' => null,
            'review_otp_tries' => 0,
        ]);

        return $this->backToForm();
    }

    public function store(Request $request)
    {
        $phone = (string) session('review_phone', '');
        if ($phone === '' || ! session('review_verified')) {
            return redirect()->route('reviews.create')->with('error', 'Confirm your phone number before sending a review.');
        }

        $request->validate([
            'reviewer_name' => 'required|string|max:191',
            'country' => 'required|string|max:80',
            'sector' => 'required|in:'.implode(',', array_keys(CompanyReview::sectors())),
            'sector_other' => 'nullable|string|max:191',
            'stars' => 'required|integer|min:1|max:5',
            'recommend' => 'required|integer|min:1|max:10',
            'comment' => 'required|string|min:20|max:800',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'signature' => 'nullable|string',
        ]);
        if ($request->input('sector') === 'others' && trim((string) $request->input('sector_other')) === '') {
            return back()->withInput()->withErrors(['sector_other' => 'Say which sector this is.']);
        }

        $country = CompanyReview::canonicalCountry($request->input('country'));
        if (! $country) {
            return back()->withInput()->withErrors(['country' => 'Choose a country from the list.']);
        }

        $handoff = $this->currentHandoff();
        $signaturePath = $this->storeSignature($request->input('signature'));
        if (! $signaturePath && $handoff && ! empty($handoff['signature'])) {
            $signaturePath = $handoff['signature'];
        }
        if (! $signaturePath) {
            return back()->withInput()->withErrors(['signature' => 'Please sign in the box before sending your review.']);
        }

        $score = (int) $request->input('recommend');

        $review = CompanyReview::where('phone', $phone)->first();
        if (! $review) {
            $review = new CompanyReview();
            $review->phone = $phone;
        }

        $photoPath = $review->photo_path;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storePhoto($request->file('photo'), $review->photo_path);
        } elseif (! $photoPath && $handoff && ! empty($handoff['photo'])) {
            $photoPath = $handoff['photo'];
        }

        $oldSignature = $review->signature_path;
        $name = trim(strip_tags((string) $request->input('reviewer_name')));
        $review->reviewer_name = mb_substr($name, 0, 191);
        $review->country = $country;
        $review->sector = (string) $request->input('sector');
        $review->sector_other = $review->sector === 'others'
            ? mb_substr(trim(strip_tags((string) $request->input('sector_other'))), 0, 191)
            : null;
        $review->rating = $score;
        $review->rating_value = (int) $request->input('stars');
        $review->rating_quality = null;
        $review->rating_esthetics = null;
        $review->rating_professionalism = null;
        $review->rating_time = null;
        $review->comment = trim(strip_tags($request->input('comment')));
        $review->photo_path = $photoPath;
        $review->signature_path = $signaturePath;
        $review->published = $score >= 6 && ! $review->hidden_by_staff;

        $invite = $this->sessionInvite();
        if ($invite && session('review_skip_otp') && ! $review->invite_id) {
            $taken = CompanyReview::where('invite_id', $invite->id)->where('id', '!=', (int) $review->id)->exists();
            if (! $taken) {
                $review->invite_id = $invite->id;
            }
        }

        $review->save();
        if ($oldSignature && $oldSignature !== $signaturePath) {
            $this->deleteLocal($oldSignature);
        }
        if ($handoff) {
            if (! empty($handoff['signature']) && $handoff['signature'] !== $signaturePath) {
                $this->deleteLocal($handoff['signature']);
            }
            if (! empty($handoff['photo']) && $handoff['photo'] !== $photoPath) {
                $this->deleteLocal($handoff['photo']);
            }
        }
        $this->forgetHandoff();
        session()->forget(['review_verified', 'review_phone', 'review_suggested_name', 'review_skip_otp', 'review_invite_id', 'review_otp_hash', 'review_handoff_focus']);

        if ($review->published) {
            return redirect()->route('reviews.index')->with('message', 'Thank you. Your review is now on the website.');
        }
        if ($score >= 6) {
            return redirect()->route('reviews.index')->with('message', 'Thank you. Your review was saved. It stays off the website until a manager shows it.');
        }

        return redirect()->route('reviews.index')->with('message', 'Thank you. We received your review. It is shown on the website from 6 out of 10 upward.');
    }

    public function admin()
    {
        $this->authorizeAdmin();

        return view('company_reviews.index', [
            'reviews' => CompanyReview::orderBy('id', 'desc')->paginate(30),
        ]);
    }

    public function visibility(Request $request, $id)
    {
        $this->authorizeAdmin();
        $review = CompanyReview::findOrFail($id);
        $show = (string) $request->input('published') === '1';
        if ($show && ! $review->meetsPublishRule()) {
            return back()->with('error', 'A review is shown only from 6 out of 10 upward.');
        }

        $review->published = $show;
        $review->hidden_by_staff = ! $show;
        $review->save();

        return back()->with('message', $show ? 'The review is on the website.' : 'The review is hidden.');
    }

    protected function authorizeAdmin()
    {
        if (! Auth::check() || ! in_array((int) Auth::user()->role_id, [1, 2], true)) {
            abort(403);
        }
    }

    protected function formState($invite = null)
    {
        $verified = (bool) session('review_verified') || (bool) session('review_skip_otp');
        $waitingCode = ! $verified && session('review_otp_hash');

        return [
            'step' => $verified ? 'review' : ($waitingCode ? 'otp' : 'phone'),
            'phone' => (string) session('review_phone', ''),
            'suggestedName' => (string) session('review_suggested_name', ''),
            'countries' => CompanyReview::countryOptions(),
            'sectors' => CompanyReview::sectors(),
            'invite' => $invite ?: $this->sessionInvite(),
            'skippedOtp' => (bool) session('review_skip_otp'),
            'capture' => $this->captureView(),
        ];
    }

    public function handoff(Request $request)
    {
        $phone = $this->verifiedPhone();
        if ($phone === null) {
            return redirect()->route('reviews.create')->with('error', 'Confirm your phone number before continuing on another device.');
        }
        $focus = $request->input('focus') === 'sign' ? 'sign' : 'selfie';
        $this->forgetHandoff();
        $token = bin2hex(random_bytes(16));
        Cache::put($this->handoffKey($token), [
            'phone' => $phone,
            'photo' => null,
            'signature' => null,
        ], now()->addMinutes(20));
        session([
            'review_handoff_token' => $token,
            'review_handoff_expires' => time() + 1200,
            'review_handoff_focus' => $focus,
        ]);

        if ($request->expectsJson()) {
            $view = $this->captureView();

            return response()->json([
                'qr' => $view['qr'] ? (string) $view['qr'] : '',
                'status_url' => route('reviews.capture-status'),
            ]);
        }

        return $this->backToForm();
    }

    public function captureStatus()
    {
        $row = $this->currentHandoff();

        return response()->json([
            'photo' => $row && ! empty($row['photo']),
            'signature' => $row && ! empty($row['signature']),
            'photo_url' => ($row && ! empty($row['photo'])) ? CompanyReview::publicUrl($row['photo']) : '',
            'signature_url' => ($row && ! empty($row['signature'])) ? CompanyReview::publicUrl($row['signature']) : '',
        ]);
    }

    public function capture($token)
    {
        $row = $this->openHandoff($token);
        if (! $row) {
            return response()->view('beyond.reviews.message', [
                'title' => 'This phone link has expired',
                'message' => 'Go back to the review and generate a new QR code.',
            ], 410);
        }

        return view('beyond.reviews.capture', [
            'token' => $token,
            'photoUrl' => ! empty($row['photo']) ? CompanyReview::publicUrl($row['photo']) : '',
            'signatureUrl' => ! empty($row['signature']) ? CompanyReview::publicUrl($row['signature']) : '',
        ]);
    }

    public function captureStore(Request $request, $token)
    {
        $row = $this->openHandoff($token);
        if (! $row) {
            return back()->withErrors(['link' => 'This phone link has expired. Generate a new QR code.']);
        }
        $request->validate([
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'signature' => 'nullable|string',
        ]);
        if ($request->hasFile('photo')) {
            $row['photo'] = $this->storePhoto($request->file('photo'), isset($row['photo']) ? $row['photo'] : null);
        }
        $signature = $this->storeSignature($request->input('signature'));
        if ($signature) {
            if (! empty($row['signature']) && $row['signature'] !== $signature) {
                $this->deleteLocal($row['signature']);
            }
            $row['signature'] = $signature;
        }
        if (empty($row['photo']) && ! $signature && empty($row['signature'])) {
            return back()->withErrors(['selfie' => 'Take a selfie or draw your signature.']);
        }
        Cache::put($this->handoffKey($token), $row, now()->addMinutes(20));

        return back()->with('message', 'Saved. You can return to the first screen.');
    }

    protected function backToForm()
    {
        $invite = $this->sessionInvite();
        if ($invite && session('review_skip_otp')) {
            return redirect()->route('reviews.invite', ['token' => $invite->token]);
        }

        return redirect()->route('reviews.create');
    }

    protected function normalizePhone($raw)
    {
        $raw = trim((string) $raw);
        try {
            $phone = WhatsAppPhone::sanitizeForStorage($raw);
        } catch (\Throwable $e) {
            $phone = preg_replace('/\D/', '', $raw);
        }
        $digits = preg_replace('/\D/', '', (string) $phone);
        if (strlen($digits) < 8 || strlen($digits) > 15) {
            return null;
        }

        return $digits;
    }

    protected function databaseName($phone)
    {
        $name = '';
        try {
            $lookup = app(PeopleDirectoryService::class)->lookupPhoneForForm($phone);
            $name = trim((string) (isset($lookup['system_name']) ? $lookup['system_name'] : ''));
        } catch (\Throwable $e) {
            $name = '';
        }
        if ($name !== '') {
            return $name;
        }

        $tail = substr($phone, -9);
        if (strlen($tail) < 8) {
            return '';
        }
        $paid = DocumentPaymentLink::where('status', 'paid')
            ->where('phone', 'like', '%'.$tail)
            ->orderBy('id', 'desc')
            ->first();

        return $paid ? trim((string) $paid->person_name) : '';
    }

    protected function sessionInvite()
    {
        $id = session('review_invite_id');
        if (! $id) {
            return null;
        }

        return CompanyReviewInvite::find($id);
    }

    protected function storePhoto($file, $oldPath = null)
    {
        if (! $file || ! $file->isValid()) {
            return $oldPath;
        }

        $dir = public_path('images/reviews');
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        $name = 'review_'.time().'_'.bin2hex(random_bytes(4)).'.jpg';
        $full = $dir.'/'.$name;

        try {
            $img = Image::make($file->getRealPath());
            $img->fit(800, 800, function ($constraint) {
                $constraint->upsize();
            });
            $img->encode('jpg', 82)->save($full);
        } catch (\Throwable $e) {
            $file->move($dir, $name);
        }

        $this->deleteLocal($oldPath);

        return 'images/reviews/'.$name;
    }

    protected function storeSignature($dataUrl)
    {
        $dataUrl = trim((string) $dataUrl);
        if (! preg_match('#^data:image/png;base64,#', $dataUrl)) {
            return null;
        }

        $raw = base64_decode(substr($dataUrl, 22), true);
        if ($raw === false || strlen($raw) < 500 || strlen($raw) > 500000) {
            return null;
        }
        $info = @getimagesizefromstring($raw);
        if (! $info || (int) $info[0] < 20 || (int) $info[1] < 20) {
            return null;
        }

        $dir = public_path('images/reviews');
        if (! is_dir($dir)) {
            File::makeDirectory($dir, 0775, true);
        }

        $name = 'sign_'.time().'_'.bin2hex(random_bytes(4)).'.png';
        if (file_put_contents($dir.'/'.$name, $raw) === false) {
            return null;
        }

        return 'images/reviews/'.$name;
    }

    protected function deleteLocal($path)
    {
        $path = trim((string) $path);
        if ($path === '' || preg_match('#^https?://#i', $path) || strpos($path, 'data:') === 0) {
            return;
        }
        if (strpos($path, 'images/reviews/') !== 0) {
            return;
        }
        $full = public_path($path);
        if (is_file($full)) {
            @unlink($full);
        }
    }

    protected function verifiedPhone()
    {
        $phone = (string) session('review_phone', '');
        if ($phone === '' || (! session('review_verified') && ! session('review_skip_otp'))) {
            return null;
        }

        return $phone;
    }

    protected function handoffKey($token)
    {
        return 'review-handoff:'.hash('sha256', (string) $token);
    }

    protected function currentHandoff()
    {
        $token = (string) session('review_handoff_token', '');
        if ($token === '' || (int) session('review_handoff_expires', 0) < time()) {
            return null;
        }
        $row = Cache::get($this->handoffKey($token));
        if (! is_array($row) || (string) (isset($row['phone']) ? $row['phone'] : '') !== (string) session('review_phone', '')) {
            return null;
        }

        return $row;
    }

    protected function openHandoff($token)
    {
        $token = strtolower(preg_replace('/[^a-f0-9]/', '', (string) $token));
        if (strlen($token) !== 32) {
            return null;
        }
        $row = Cache::get($this->handoffKey($token));

        return is_array($row) ? $row : null;
    }

    protected function forgetHandoff()
    {
        $token = (string) session('review_handoff_token', '');
        if ($token !== '') {
            Cache::forget($this->handoffKey($token));
        }
        session()->forget(['review_handoff_token', 'review_handoff_expires']);
    }

    protected function captureView()
    {
        $token = (string) session('review_handoff_token', '');
        $row = $this->currentHandoff();
        $qr = null;
        if ($row && $token !== '') {
            try {
                $qr = \QrCode::size(168)->margin(1)->generate(route('reviews.capture', ['token' => $token]));
            } catch (\Throwable $e) {
                $qr = null;
            }
        }

        return [
            'qr' => $qr,
            'expires' => $row ? (int) session('review_handoff_expires', 0) : 0,
            'photo_url' => ($row && ! empty($row['photo'])) ? CompanyReview::publicUrl($row['photo']) : '',
            'signature_url' => ($row && ! empty($row['signature'])) ? CompanyReview::publicUrl($row['signature']) : '',
            'focus' => (string) session('review_handoff_focus', ''),
        ];
    }
}
