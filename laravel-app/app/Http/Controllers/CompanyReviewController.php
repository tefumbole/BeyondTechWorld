<?php

namespace App\Http\Controllers;

use App\CompanyReview;
use App\CompanyReviewInvite;
use App\Support\EnvFile;
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
            session(['review_invite_id' => $invite->id]);
        }

        return view('beyond.reviews.create', [
            'google' => session('review_google'),
            'googleClientId' => $this->googleClientId(),
            'countries' => CompanyReview::countryOptions(),
            'invite' => $invite,
        ]);
    }

    public function google(Request $request)
    {
        $profile = $this->verifyGoogle(trim((string) $request->input('credential', '')));
        if (! $profile) {
            return redirect()->route('reviews.create')->with('error', 'Google could not confirm this account. Please try again.');
        }

        session(['review_google' => $profile]);
        $invite = $this->sessionInvite();
        if ($invite) {
            return redirect()->route('reviews.invite', ['token' => $invite->token]);
        }

        return redirect()->route('reviews.create');
    }

    public function store(Request $request)
    {
        $profile = session('review_google');
        if (! is_array($profile) || empty($profile['sub']) || empty($profile['email'])) {
            return redirect()->route('reviews.create')->with('error', 'Confirm your Google account before sending a review.');
        }

        $request->validate([
            'country' => 'required|string|max:80',
            'rating' => 'required|integer|min:1|max:5',
            'comment' => 'required|string|min:20|max:800',
            'photo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:4096',
            'signature' => 'required|string',
        ]);

        $country = CompanyReview::canonicalCountry($request->input('country'));
        if (! $country) {
            return back()->withInput()->withErrors(['country' => 'Choose a country from the list.']);
        }

        $signaturePath = $this->storeSignature($request->input('signature'));
        if (! $signaturePath) {
            return back()->withInput()->withErrors(['signature' => 'Please sign in the box before sending your review.']);
        }

        $review = CompanyReview::where('google_sub', $profile['sub'])->first();
        if (! $review) {
            $review = new CompanyReview();
            $review->google_sub = $profile['sub'];
        }

        $photoPath = $review->photo_path;
        if ($request->hasFile('photo')) {
            $photoPath = $this->storePhoto($request->file('photo'), $review->photo_path);
        }

        $oldSignature = $review->signature_path;
        $rating = (int) $request->input('rating');
        $name = trim(strip_tags((string) ($profile['name'] ?? '')));
        if ($name === '') {
            $name = 'Client';
        }

        $review->google_email = $profile['email'];
        $review->reviewer_name = mb_substr($name, 0, 191);
        $review->google_photo = CompanyReview::safeGooglePhoto(isset($profile['picture']) ? $profile['picture'] : '');
        $review->country = $country;
        $review->rating = $rating;
        $review->comment = trim(strip_tags($request->input('comment')));
        $review->photo_path = $photoPath;
        $review->signature_path = $signaturePath;
        $review->published = $rating === 5 && ! $review->hidden_by_staff;

        $invite = $this->sessionInvite();
        if ($invite && ! $review->invite_id) {
            $taken = CompanyReview::where('invite_id', $invite->id)->exists();
            if (! $taken) {
                $review->invite_id = $invite->id;
            }
        }

        $review->save();
        if ($oldSignature && $oldSignature !== $signaturePath) {
            $this->deleteLocal($oldSignature);
        }

        if ($rating === 5 && $review->published) {
            return redirect()->route('reviews.index')->with('message', 'Thank you. Your review is now on the website.');
        }
        if ($rating === 5) {
            return redirect()->route('reviews.index')->with('message', 'Thank you. Your review was saved. It stays off the website until a manager shows it.');
        }

        return redirect()->route('reviews.index')->with('message', 'Thank you. We received your review. Only 5-star reviews are shown on the website.');
    }

    public function admin()
    {
        $this->authorizeAdmin();

        return view('company_reviews.index', [
            'reviews' => CompanyReview::orderBy('id', 'desc')->paginate(30),
            'googleReady' => $this->googleClientId() !== '',
        ]);
    }

    public function saveGoogle(Request $request)
    {
        $this->authorizeAdmin();
        $id = trim((string) $request->input('google_client_id', ''));
        if ((string) $request->input('clear_google') === '1') {
            if (! EnvFile::upsert(['GOOGLE_REVIEW_CLIENT_ID' => ''])) {
                return back()->with('error', 'The server could not save the Google client ID.');
            }

            return back()->with('message', 'Google sign-in was cleared.');
        }
        if ($id === '') {
            return back()->withErrors(['google_client_id' => 'Paste the Google client ID.']);
        }
        if (! preg_match('/^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/', $id)) {
            return back()->withErrors([
                'google_client_id' => 'Paste the Web client ID from Google Cloud. It ends with .apps.googleusercontent.com.',
            ]);
        }

        if (! EnvFile::upsert(['GOOGLE_REVIEW_CLIENT_ID' => $id])) {
            return back()->with('error', 'The server could not save the Google client ID.');
        }

        return back()->with('message', 'Google sign-in is saved. In Google Cloud, add https://beyondtechworld.com as an authorized JavaScript origin.');
    }

    public function visibility(Request $request, $id)
    {
        $this->authorizeAdmin();
        $review = CompanyReview::findOrFail($id);
        $show = (string) $request->input('published') === '1';
        if ($show && (int) $review->rating < 5) {
            return back()->with('error', 'Reviews below 5 stars stay off the website.');
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

    protected function googleClientId()
    {
        return trim((string) EnvFile::get('GOOGLE_REVIEW_CLIENT_ID', ''));
    }

    protected function sessionInvite()
    {
        $id = session('review_invite_id');
        if (! $id) {
            return null;
        }

        return CompanyReviewInvite::find($id);
    }

    protected function verifyGoogle($credential)
    {
        $clientId = $this->googleClientId();
        if ($clientId === '' || $credential === '' || strlen($credential) > 4000) {
            return null;
        }

        $ch = curl_init('https://oauth2.googleapis.com/tokeninfo?id_token='.urlencode($credential));
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 8,
            CURLOPT_CONNECTTIMEOUT => 4,
        ]);
        $body = curl_exec($ch);
        $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        if ($code !== 200 || ! is_string($body) || $body === '') {
            return null;
        }

        $data = json_decode($body, true);
        if (! is_array($data)) {
            return null;
        }
        if (! hash_equals($clientId, (string) (isset($data['aud']) ? $data['aud'] : ''))) {
            return null;
        }
        $verified = (string) (isset($data['email_verified']) ? $data['email_verified'] : '');
        if ($verified !== 'true' && $verified !== '1') {
            return null;
        }

        $sub = trim((string) (isset($data['sub']) ? $data['sub'] : ''));
        $email = trim((string) (isset($data['email']) ? $data['email'] : ''));
        if ($sub === '' || $email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return [
            'sub' => $sub,
            'email' => $email,
            'name' => trim((string) (isset($data['name']) ? $data['name'] : '')),
            'picture' => CompanyReview::safeGooglePhoto(isset($data['picture']) ? $data['picture'] : ''),
        ];
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
