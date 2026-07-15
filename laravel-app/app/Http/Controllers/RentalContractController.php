<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingContract;
use App\BookingProduct;
use App\Customer;
use App\GeneralSetting;
use App\Notifications\ContractWorkflowNotification;
use App\Payment;
use App\Support\BookingCategoryHelper;
use App\Support\WhatsAppMessage;
use App\User;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Intervention\Image\Facades\Image;
use PDF;
use SimpleSoftwareIO\QrCode\Facades\QrCode;
use Spatie\Permission\Models\Role;

class RentalContractController extends Controller
{
    public function show($token)
    {
        $contract = $this->findContract($token);

        if ($contract->signed_at) {
            return $this->portal($token);
        }

        $booking = $contract->booking;
        $general_setting = GeneralSetting::first();
        $items = $this->buildEquipmentList($booking);
        $payments = Payment::where('booking_id', $booking->id)->get();

        $view = $this->agreementViewForContract($contract, $booking);

        return view($view, compact('contract', 'booking', 'general_setting', 'items', 'payments'));
    }

    public function sign(Request $request, $token)
    {
        $contract = $this->findContract($token);

        if ($contract->signed_at) {
            return redirect()->route('rental.portal', $token)
                ->with('message', 'This agreement has already been signed.');
        }

        $request->validate([
            'agreement_accepted' => 'required|accepted',
            'signature_image' => 'required|string|max:500000',
            'id_card' => 'required|file|mimes:jpeg,jpg,png|max:5120',
        ]);

        $booking = $contract->booking;
        $customer = $booking->customer;
        $phone = $this->normalizePhone($customer->phone_number);

        if (!$request->filled('agreement_read_confirmed')) {
            return back()->withErrors(['agreement' => 'Please read the full rental agreement before signing.'])->withInput();
        }

        $idPath = null;
        if ($request->hasFile('id_card')) {
            try {
                $idPath = $this->storeCompressedIdCard($request->file('id_card'), $contract->id);
            } catch (\Exception $e) {
                return back()->withErrors(['id_card' => 'Could not process ID image. Please upload a JPG or PNG under 5MB.'])->withInput();
            }
        }

        $user = $this->ensureClientUser($customer, $phone);
        $password = $contract->generated_password ?: Str::random(8);

        if (!$contract->client_user_id) {
            $user->password = bcrypt($password);
            $user->save();
        }

        $contract->update([
            'agreement_read_at' => now(),
            'signed_at' => now(),
            'review_status' => BookingContract::STATUS_PENDING_REVIEW,
            'signature_image' => $request->signature_image,
            'id_card_path' => $idPath,
            'client_user_id' => $user->id,
            'client_username' => $phone,
            'generated_password' => $password,
            'qr_token' => $contract->qr_token ?: Str::random(48),
        ]);

        Auth::login($user);

        $this->notifyPendingReview($contract->fresh());

        return redirect()->route('rental.portal', $token)
            ->with('message', 'Agreement signed successfully. Our team will review and countersign shortly. You will receive the final PDF and QR code via WhatsApp once approved.');
    }

    public function portal($token)
    {
        $contract = $this->findContract($token);
        $booking = $contract->booking;
        $customer = $booking->customer;
        $general_setting = GeneralSetting::first();

        if ($contract->client_user_id && !Auth::check()) {
            Auth::loginUsingId($contract->client_user_id);
        }

        if ($contract->signed_at && Auth::check()) {
            $bookings = Booking::with('bookingProduct.product')
                ->where('customer_id', $customer->id)
                ->orderByDesc('id')
                ->get();
        } else {
            $bookings = collect([$booking->load('bookingProduct.product')]);
        }

        return view('booking.client_portal', compact('contract', 'booking', 'customer', 'general_setting', 'bookings', 'token'));
    }

    public function updateCredentials(Request $request, $token)
    {
        $contract = $this->findContract($token);

        if (!$contract->signed_at || !$contract->client_user_id) {
            return back()->with('not_permitted', 'Please sign the agreement first.');
        }

        $request->validate([
            'username' => 'required|string|max:255|unique:users,name,' . $contract->client_user_id,
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = User::findOrFail($contract->client_user_id);
        $user->name = $request->username;
        $user->password = bcrypt($request->password);
        $user->save();

        $contract->update(['client_username' => $request->username]);

        Auth::login($user);

        return back()->with('message', 'Login credentials updated successfully.');
    }

    public function awaitingIndex()
    {
        $this->authorizeBookingAccess();

        $contracts = BookingContract::with(['booking.customer', 'booking.user', 'booking.biller'])
            ->whereNull('signed_at')
            ->whereHas('booking')
            ->orderByDesc('id')
            ->get();

        return view('booking.awaiting_signature', compact('contracts'));
    }

    public function pendingReviewIndex()
    {
        $this->authorizeBookingAccess();

        $contracts = BookingContract::with(['booking.customer', 'booking.user', 'booking.biller'])
            ->where('review_status', BookingContract::STATUS_PENDING_REVIEW)
            ->orderByDesc('signed_at')
            ->get();

        return view('booking.pending_review', compact('contracts'));
    }

    public function signedIndex()
    {
        $this->authorizeBookingAccess();

        $contracts = BookingContract::with(['booking.customer', 'booking.user', 'booking.biller', 'adminSigner'])
            ->where(function ($query) {
                $query->where('review_status', BookingContract::STATUS_APPROVED)
                    ->orWhere(function ($legacy) {
                        $legacy->whereNotNull('signed_at')
                            ->where(function ($inner) {
                                $inner->whereNull('review_status')
                                    ->orWhere('review_status', '');
                            });
                    });
            })
            ->orderByDesc('approved_at')
            ->orderByDesc('signed_at')
            ->get();

        return view('booking.signed_contracts', compact('contracts'));
    }

    public function viewContract($id)
    {
        $this->authorizeBookingAccess();

        $contract = BookingContract::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product', 'adminSigner'])
            ->findOrFail($id);

        if (!$contract->signed_at) {
            return redirect()->route('booking.awaiting-signature')
                ->with('not_permitted', 'Contract has not been signed by the client yet.');
        }

        $booking = $contract->booking;
        $general_setting = GeneralSetting::first();
        $items = $this->buildEquipmentList($booking);
        $header = ($general_setting->invoice_format ?? '') === 'beyond_a4' ? url('public/logo/' . $general_setting->email_header) : null;
        $footer = ($general_setting->invoice_format ?? '') === 'beyond_a4' ? url('public/logo/' . $general_setting->email_footer) : null;
        $clientSignatureSrc = $this->signatureDisplaySrc($contract, 'client');
        $adminSignatureSrc = $this->signatureDisplaySrc($contract, 'admin');

        return view('booking.contract_view', compact(
            'contract',
            'booking',
            'general_setting',
            'items',
            'header',
            'footer',
            'clientSignatureSrc',
            'adminSignatureSrc'
        ));
    }

    public function viewIdCard($id)
    {
        $this->authorizeContractApproval();

        $contract = BookingContract::findOrFail($id);

        if (!$contract->id_card_path) {
            abort(404);
        }

        $path = public_path($contract->id_card_path);
        if (!is_file($path)) {
            abort(404);
        }

        return response()->file($path);
    }

    public function reviewShow($id)
    {
        $this->authorizeContractApproval();

        $contract = BookingContract::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product'])
            ->findOrFail($id);

        if (!$contract->isPendingReview()) {
            return redirect()->route('booking.pending-review')
                ->with('not_permitted', 'This contract is not awaiting review.');
        }

        $booking = $contract->booking;
        $general_setting = GeneralSetting::first();
        $items = $this->buildEquipmentList($booking);
        $header = ($general_setting->invoice_format ?? '') === 'beyond_a4' ? url('public/logo/' . $general_setting->email_header) : null;
        $footer = ($general_setting->invoice_format ?? '') === 'beyond_a4' ? url('public/logo/' . $general_setting->email_footer) : null;
        $clientSignatureSrc = $this->signatureDisplaySrc($contract, 'client');

        return view('booking.contract_review', compact(
            'contract',
            'booking',
            'general_setting',
            'items',
            'header',
            'footer',
            'clientSignatureSrc'
        ));
    }

    public function approveReviewRedirect($id)
    {
        return redirect()->route('booking.contract.review', $id);
    }

    public function approveContract(Request $request, $id)
    {
        $this->authorizeContractApproval();

        $contract = BookingContract::with(['booking.customer', 'booking.user'])->findOrFail($id);

        if (!$contract->isPendingReview()) {
            return redirect()->route('booking.contract.review', $id)
                ->with('not_permitted', 'This contract is not awaiting review.');
        }

        $validator = Validator::make($request->all(), [
            'admin_signature_image' => 'required|string|max:500000',
        ]);

        if ($validator->fails()) {
            return redirect()->route('booking.contract.review', $id)
                ->with('not_permitted', 'Please provide your signature before approving.');
        }

        $contract->update([
            'admin_signature_image' => $request->admin_signature_image,
            'admin_signed_at' => now(),
            'admin_signed_by' => Auth::id(),
            'review_status' => BookingContract::STATUS_APPROVED,
            'approved_at' => now(),
        ]);

        try {
            $this->finalizeSignedContract($contract->fresh());
        } catch (\Exception $e) {
            return redirect()->route('booking.contract.review', $id)
                ->with('not_permitted', 'Contract approved but delivery failed: ' . $e->getMessage());
        }

        return redirect()->route('booking.signed-contracts')
            ->with('message', 'Contract reviewed, countersigned, and sent to client and creator via WhatsApp.');
    }

    public function resend($id)
    {
        $this->authorizeBookingAccess();

        $contract = BookingContract::with(['booking.customer', 'booking.bookingProduct.product'])->findOrFail($id);

        if ($contract->signed_at) {
            return back()->with('not_permitted', 'This agreement is already signed.');
        }

        try {
            self::sendSignatureLink($contract->booking, $contract);

            $booking = $contract->booking;
            $mail_data = ['products' => [], 'qty' => [], 'start' => [], 'end' => []];
            foreach ($booking->bookingProduct as $key => $line) {
                $mail_data['products'][$key] = $line->product ? $line->product->name : 'Product';
                $mail_data['qty'][$key] = $line->qty;
                $mail_data['start'][$key] = $line->start;
                $mail_data['end'][$key] = $line->end;
            }

            app(BookingController::class)->sendBookingCcNotifications(
                $booking,
                $mail_data,
                $booking->booking_note ?? '',
                optional($booking->customer)->name ?? ''
            );

            return back()->with('message', 'Signature link resent via WhatsApp. CC contacts received the updated quotation copy.');
        } catch (\Exception $e) {
            $phone = optional(optional($contract->booking)->customer)->phone_number ?? 'unknown';
            return back()->with('not_permitted', 'Could not resend to ' . $phone . ': ' . $e->getMessage());
        }
    }

    public function destroyContract($id)
    {
        $this->authorizeBookingAccess();

        $contract = BookingContract::with('booking')->findOrFail($id);

        if ($contract->signed_at) {
            return back()->with('not_permitted', 'Signed contracts cannot be deleted from this list.');
        }

        $booking = $contract->booking;
        $contract->delete();

        if ($booking) {
            BookingProduct::where('booking_id', $booking->id)->delete();
            Payment::where('booking_id', $booking->id)->delete();
            Payment::where('debit_booking_id', $booking->id)->delete();
            $booking->delete();
        }

        return redirect()->route('booking.awaiting-signature')
            ->with('message', 'Awaiting-signature booking deleted successfully.');
    }

    public function rentalScan($token)
    {
        $contract = BookingContract::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product'])
            ->where('qr_token', $token)
            ->firstOrFail();

        if (!$contract->isApproved()) {
            abort(404, 'Rental details are available after the agreement is fully approved.');
        }

        $booking = $contract->booking;
        $general_setting = GeneralSetting::first();
        $items = $this->buildEquipmentList($booking);

        return view('booking.rental_qr_scan', compact('contract', 'booking', 'general_setting', 'items'));
    }

    public static function createForBooking(Booking $booking, $contractType = null)
    {
        $type = in_array($contractType, ['equipment', 'accommodation', 'software_license'], true)
            ? $contractType
            : BookingCategoryHelper::contractTypeForBooking($booking);

        return BookingContract::create([
            'booking_id' => $booking->id,
            'contract_type' => $type,
            'signature_token' => Str::random(48),
            'qr_token' => Str::random(48),
            'review_status' => BookingContract::STATUS_PENDING_CLIENT,
        ]);
    }

    public static function sendSignatureLink(Booking $booking, BookingContract $contract)
    {
        $booking = Booking::with(['customer', 'user'])->findOrFail($booking->id);
        $customer = $booking->customer;

        if (!$customer) {
            throw new \Exception('Customer not found for this booking.');
        }

        if (empty(trim((string) $customer->phone_number))) {
            throw new \Exception('Customer phone number is missing. Add a phone number before sending for signature.');
        }

        $controller = app(self::class);
        $link = url('rental-agreement/' . $contract->signature_token);
        $msg = WhatsAppMessage::signatureRequest(
            $customer->name,
            $booking->reference_no,
            $link,
            null,
            $contract->contract_type
        );

        $controller->sendWhatsAppToCustomer($customer, $msg);
        $controller->notifyAwaitingSignature($booking);
    }

    private function notifyAwaitingSignature(Booking $booking)
    {
        $creator = User::find($booking->user_id);
        if (!$creator) {
            return;
        }

        $message = 'Contract awaiting client signature for booking ' . $booking->reference_no . '.';
        $link = route('booking.awaiting-signature');
        $creator->notify(new ContractWorkflowNotification($message, $link, 'awaiting_signature'));

        if (!empty($creator->phone)) {
            try {
                $this->sendWhatsAppToPhone(
                    $creator->phone,
                    WhatsAppMessage::awaitingSignatureNotice(
                        $creator->name,
                        optional($booking->customer)->name ?? 'Client',
                        $booking->reference_no,
                        $link
                    )
                );
            } catch (\Exception $e) {
                Log::warning('Awaiting signature WhatsApp failed for booking ' . $booking->reference_no . ': ' . $e->getMessage());
            }
        }
    }

    private function notifyPendingReview(BookingContract $contract)
    {
        $contract->load(['booking.customer', 'booking.user', 'booking.biller', 'booking.bookingProduct.product']);
        $booking = $contract->booking;
        $creator = User::find($booking->user_id);
        $reviewUrl = route('booking.contract.review', $contract->id);
        $customer = $booking->customer;
        $customerName = optional($customer)->name ?? 'Client';
        $general_setting = GeneralSetting::first();
        $items = $this->buildEquipmentList($booking);

        $signedPdfRelative = $this->generateSignedContractPdf($contract, $booking, $general_setting, $items);
        $contract->update(['signed_pdf_path' => $signedPdfRelative]);
        $signedPdfPath = public_path($signedPdfRelative);
        $signedPdfUrl = url($signedPdfRelative);

        if ($customer && !empty(trim((string) $customer->phone_number))) {
            try {
                $this->sendWhatsAppToCustomer(
                    $customer,
                    WhatsAppMessage::clientSignedPendingReview($customerName, $booking->reference_no, $reviewUrl)
                );
                $this->sendWhatsAppDocumentToCustomer(
                    $customer,
                    $signedPdfPath,
                    'signed_rental_agreement.pdf',
                    $signedPdfUrl
                );
            } catch (\Exception $e) {
                Log::warning('Pending review client WhatsApp failed for booking ' . $booking->reference_no . ': ' . $e->getMessage());
            }
        }

        if ($creator) {
            $message = $customerName . ' signed booking ' . $booking->reference_no . '. Review and countersign required.';
            $creator->notify(new ContractWorkflowNotification($message, $reviewUrl, 'pending_review'));

            if (!empty($creator->phone)) {
                try {
                    $this->sendWhatsAppToPhone(
                        $creator->phone,
                        WhatsAppMessage::pendingReviewNotice(
                            $creator->name,
                            $customerName,
                            $booking->reference_no,
                            $reviewUrl
                        )
                    );
                    $this->sendWhatsAppDocumentToPhone(
                        $creator->phone,
                        $signedPdfPath,
                        'signed_rental_agreement.pdf',
                        $signedPdfUrl
                    );
                } catch (\Exception $e) {
                    Log::warning('Pending review creator WhatsApp failed for booking ' . $booking->reference_no . ': ' . $e->getMessage());
                }
            }
        }

        foreach (User::where('is_active', true)->whereIn('role_id', [1, 2])->get() as $admin) {
            $admin->notify(new ContractWorkflowNotification(
                'Contract pending review: ' . $booking->reference_no,
                $reviewUrl,
                'pending_review'
            ));

            if ($creator && $admin->id === $creator->id) {
                continue;
            }

            if (!empty($admin->phone)) {
                try {
                    $this->sendWhatsAppToPhone(
                        $admin->phone,
                        WhatsAppMessage::pendingReviewNotice(
                            $admin->name,
                            $customerName,
                            $booking->reference_no,
                            $reviewUrl
                        )
                    );
                    $this->sendWhatsAppDocumentToPhone(
                        $admin->phone,
                        $signedPdfPath,
                        'signed_rental_agreement.pdf',
                        $signedPdfUrl
                    );
                } catch (\Exception $e) {
                    Log::warning('Pending review admin WhatsApp failed for booking ' . $booking->reference_no . ': ' . $e->getMessage());
                }
            }
        }
    }

    private function finalizeSignedContract(BookingContract $contract)
    {
        $contract->load(['booking.customer', 'booking.biller', 'booking.user', 'booking.bookingProduct.product', 'adminSigner']);
        $booking = $contract->booking;
        $customer = $booking->customer;
        $general_setting = GeneralSetting::first();
        $items = $this->buildEquipmentList($booking);

        $signedPdfRelative = $this->generateSignedContractPdf($contract, $booking, $general_setting, $items);
        $contract->update(['signed_pdf_path' => $signedPdfRelative]);

        $qrRelative = $this->generateRentalQrImage($contract);
        $signedPdfPath = public_path($signedPdfRelative);
        $qrPath = public_path($qrRelative);
        $signedPdfUrl = url($signedPdfRelative);
        $qrUrl = url($qrRelative);
        $scanUrl = url('rental/scan/' . $contract->qr_token);
        $portalUrl = url('rental-portal/' . $contract->signature_token);

        $clientMsg = WhatsAppMessage::contractApprovedClient(
            $customer->name,
            $booking->reference_no,
            $portalUrl,
            $contract->client_username,
            $contract->generated_password
        );

        $this->sendWhatsAppToCustomer($customer, $clientMsg);
        $this->sendWhatsAppDocumentToCustomer($customer, $signedPdfPath, 'signed_rental_agreement.pdf', $signedPdfUrl);
        $this->sendWhatsAppDocumentToCustomer($customer, $qrPath, 'rental_qr.png', $qrUrl);

        $creator = User::find($booking->user_id);
        if ($creator && !empty($creator->phone)) {
            $staffMsg = WhatsAppMessage::contractApprovedStaff(
                $creator->name,
                $customer->name,
                $booking->reference_no,
                $scanUrl
            );
            $this->sendWhatsAppToPhone($creator->phone, $staffMsg);
            $this->sendWhatsAppDocumentToPhone($creator->phone, $signedPdfPath, 'signed_rental_agreement.pdf', $signedPdfUrl);
            $this->sendWhatsAppDocumentToPhone($creator->phone, $qrPath, 'rental_qr.png', $qrUrl);
        }

        app(BookingController::class)->deliverPostSignatureReceipt($booking->id);
    }

    private function generateSignedContractPdf(BookingContract $contract, Booking $booking, $general_setting, array $items)
    {
        $directory = public_path('booking_contracts/signed');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $signatureFilePath = $this->persistSignatureImage($contract, 'client');
        $adminSignatureFilePath = $this->persistSignatureImage($contract, 'admin');
        $headerPath = ($general_setting->invoice_format ?? '') === 'beyond_a4' && $general_setting->email_header
            ? public_path('logo/' . $general_setting->email_header)
            : null;
        $footerPath = ($general_setting->invoice_format ?? '') === 'beyond_a4' && $general_setting->email_footer
            ? public_path('logo/' . $general_setting->email_footer)
            : null;
        $watermarkPath = !empty($general_setting->email_water_mark)
            ? public_path('logo/' . $general_setting->email_water_mark)
            : (!empty($general_setting->site_logo) ? public_path('logo/' . $general_setting->site_logo) : null);

        $filename = 'signed_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $booking->reference_no) . '_' . time() . '.pdf';
        $relativePath = 'booking_contracts/signed/' . $filename;

        $pdfView = $this->signedPdfViewForContract($contract, $booking);

        $pdf = PDF::loadView($pdfView, compact(
            'contract',
            'booking',
            'general_setting',
            'items',
            'signatureFilePath',
            'adminSignatureFilePath',
            'headerPath',
            'footerPath',
            'watermarkPath'
        ));
        $pdf->save(public_path($relativePath));

        return $relativePath;
    }

    private function agreementViewForContract(BookingContract $contract, Booking $booking)
    {
        $type = $contract->contract_type ?: BookingCategoryHelper::contractTypeForBooking($booking);

        if ($type === 'accommodation') {
            return 'booking.accommodation_agreement';
        }
        if ($type === 'software_license') {
            return 'booking.software_license_agreement';
        }

        return 'booking.rental_agreement';
    }

    private function signedPdfViewForContract(BookingContract $contract, Booking $booking)
    {
        $type = $contract->contract_type ?: BookingCategoryHelper::contractTypeForBooking($booking);

        if ($type === 'accommodation') {
            return 'pdf.signed_accommodation_contract';
        }
        if ($type === 'software_license') {
            return 'pdf.signed_software_license_contract';
        }

        return 'pdf.signed_rental_contract';
    }

    private function persistSignatureImage(BookingContract $contract, $type = 'client')
    {
        $data = $type === 'admin' ? $contract->admin_signature_image : $contract->signature_image;
        if (empty($data)) {
            return null;
        }

        if (preg_match('/^data:image\/(\w+);base64,/', $data, $matches)) {
            $extension = strtolower($matches[1]);
            $data = substr($data, strpos($data, ',') + 1);
        } else {
            $extension = 'png';
        }

        if (!in_array($extension, ['png', 'jpg', 'jpeg'], true)) {
            return null;
        }

        $decoded = base64_decode($data);
        if ($decoded === false || strlen($decoded) > 500000) {
            return null;
        }

        $directory = public_path('booking_contracts/signatures');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $relativePath = 'booking_contracts/signatures/' . $type . '_' . $contract->id . '.' . $extension;
        File::put(public_path($relativePath), $decoded);

        return public_path($relativePath);
    }

    private function signatureDisplaySrc(BookingContract $contract, $type = 'client')
    {
        $data = $type === 'admin' ? $contract->admin_signature_image : $contract->signature_image;
        if (empty($data)) {
            $path = public_path('booking_contracts/signatures/' . $type . '_' . $contract->id . '.png');
            return file_exists($path) ? url('booking_contracts/signatures/' . $type . '_' . $contract->id . '.png') : null;
        }

        if (strpos($data, 'data:image') === 0) {
            return $data;
        }

        return 'data:image/png;base64,' . $data;
    }

    private function generateRentalQrImage(BookingContract $contract)
    {
        $directory = public_path('booking_contracts/qr');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = 'qr_' . $contract->booking_id . '.png';
        $relativePath = 'booking_contracts/qr/' . $filename;
        $scanUrl = url('rental/scan/' . $contract->qr_token);

        QrCode::format('png')->size(320)->margin(1)->generate($scanUrl, public_path($relativePath));

        return $relativePath;
    }

    private function storeCompressedIdCard($uploadedFile, $contractId)
    {
        $directory = public_path('booking_contracts/id_cards');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $filename = 'id_' . $contractId . '_' . time() . '.jpg';
        $fullPath = $directory . DIRECTORY_SEPARATOR . $filename;

        Image::make($uploadedFile)
            ->orientate()
            ->resize(1200, 1200, function ($constraint) {
                $constraint->aspectRatio();
                $constraint->upsize();
            })
            ->encode('jpg', 72)
            ->save($fullPath);

        return 'booking_contracts/id_cards/' . $filename;
    }

    private function authorizeBookingAccess()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('booking_index')) {
            abort(403);
        }
    }

    private function authorizeContractApproval()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role) {
            abort(403);
        }

        if (in_array((int) Auth::user()->role_id, [1, 2], true)) {
            return;
        }

        if ($role->hasPermissionTo('booking_contract_approve')) {
            return;
        }

        abort(403, 'You are not allowed to review or approve rental contracts.');
    }

    private function findContract($token)
    {
        return BookingContract::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product'])
            ->where('signature_token', $token)
            ->firstOrFail();
    }

    private function buildEquipmentList(Booking $booking)
    {
        $items = [];

        foreach ($booking->bookingProduct as $line) {
            $product = $line->product;
            $items[] = [
                'name' => $product ? $product->name : 'Equipment',
                'code' => $product ? $product->code : '',
                'qty' => $line->qty,
                'unit_price' => $line->net_unit_price,
                'total' => $line->total,
                'start' => $line->start,
                'end' => $line->end,
            ];
        }

        return $items;
    }

    private function ensureClientUser(Customer $customer, $phone)
    {
        if ($customer->user_id) {
            $user = User::find($customer->user_id);
            if ($user) {
                return $user;
            }
        }

        $existing = User::where('phone', $phone)->where('role_id', 5)->first();
        if ($existing) {
            $customer->user_id = $existing->id;
            $customer->save();
            return $existing;
        }

        $password = Str::random(8);
        $user = User::create([
            'name' => $phone,
            'phone' => $phone,
            'email' => $customer->email ?: ($phone . '@rental.local'),
            'password' => bcrypt($password),
            'role_id' => 5,
            'is_active' => true,
            'is_deleted' => false,
        ]);

        $customer->user_id = $user->id;
        $customer->save();

        return $user;
    }

    private function normalizePhone($phone)
    {
        return $this->normalizeWhatsAppPhone($phone);
    }
}
