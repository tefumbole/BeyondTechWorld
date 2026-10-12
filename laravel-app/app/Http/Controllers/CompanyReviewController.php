<?php

namespace App\Http\Controllers;

use App\CompanyReview;
use App\CompanyReviewInvite;
use App\DocumentPaymentLink;
use App\Services\ClientNoticeService;
use App\Services\PeopleDirectoryService;
use App\Support\MemorableOtp;
use App\Support\WhatsAppPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\File;
use Intervention\Image\Facades\Image;

class CompanyReviewController extends Controller
{
    public function index()
    {
        $reviews = CompanyReview::where('published', 1)
            ->where('rating', 5)
            ->orderBy('id', 'desc')
            ->limit(48)
            ->get();

        return view('beyond.reviews.index', [
            'reviews' => $reviews,
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

        $text = 'Your Beyond Company Limited review code is '.MemorableOtp::display($code).'. The first pair is repeated. Type it on the review page. It expires in 10 minutes.';
        try {
            app(ClientNoticeService::class)->send($phone, $text);
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

            return $this->backToForm()->withErrors(['otp' => 'That code does not match. The first pair is repeated.']);
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

        $rules = [
            'reviewer_name' => 'required|string|max:191',
            'country' => 'required|string|max:80',
            'comment' => 'required|string|min:20|max:800',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'signature' => 'required|string',
        ];
        foreach (array_keys(CompanyReview::scoreFields()) as $field) {
            $rules[$field] = 'required|integer|min:1|max:5';
        }
        $request->validate($rules);

        $country = CompanyReview::canonicalCountry($request->input('country'));
        if (! $country) {
            return back()->withInput()->withErrors(['country' => 'Choose a country from the list.']);
        }

        $signaturePath = $this->storeSignature($request->input('signature'));
        if (! $signaturePath) {
            return back()->withInput()->withErrors(['signature' => 'Please sign in the box before sending your review.']);
        }

        $scores = [];
        $lowest = 5;
        foreach (array_keys(CompanyReview::scoreFields()) as $field) {
            $scores[$field] = (int) $request->input($field);
            if ($scores[$field] < $lowest) {
                $lowest = $scores[$field];
            }
        }

        $review = CompanyReview::where('phone', $phone)->first();
        if (! $review) {
            $review = new CompanyReview();
            $review->phone = $phone;
        }

        $photoPath = $review->photo_path;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storePhoto($request->file('photo'), $review->photo_path);
        }

        $oldSignature = $review->signature_path;
        $name = trim(strip_tags((string) $request->input('reviewer_name')));
        $review->reviewer_name = mb_substr($name, 0, 191);
        $review->country = $country;
        $review->rating = $lowest;
        foreach ($scores as $field => $value) {
            $review->{$field} = $value;
        }
        $review->comment = trim(strip_tags($request->input('comment')));
        $review->photo_path = $photoPath;
        $review->signature_path = $signaturePath;
        $review->published = $lowest === 5 && ! $review->hidden_by_staff;

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
        session()->forget(['review_verified', 'review_phone', 'review_suggested_name', 'review_skip_otp', 'review_invite_id', 'review_otp_hash']);

        if ($review->published) {
            return redirect()->route('reviews.index')->with('message', 'Thank you. Your review is now on the website.');
        }
        if ($lowest === 5) {
            return redirect()->route('reviews.index')->with('message', 'Thank you. Your review was saved. It stays off the website until a manager shows it.');
        }

        return redirect()->route('reviews.index')->with('message', 'Thank you. We received your review. It is shown on the website only when every score is 5 stars.');
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
        if ($show && ! $review->allFiveStars()) {
            return back()->with('error', 'A review is shown only when every score is 5 stars.');
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
            'scores' => CompanyReview::scoreFields(),
            'invite' => $invite ?: $this->sessionInvite(),
            'skippedOtp' => (bool) session('review_skip_otp'),
        ];
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
}
