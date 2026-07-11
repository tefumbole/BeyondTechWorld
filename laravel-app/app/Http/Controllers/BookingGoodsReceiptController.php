<?php

namespace App\Http\Controllers;

use App\Booking;
use App\BookingGoodsReceipt;
use App\BookingProduct;
use App\Biller;
use App\Customer;
use App\GeneralSetting;
use App\Support\WhatsAppMessage;
use App\Warehouse;
use Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use PDF;
use Spatie\Permission\Models\Role;

class BookingGoodsReceiptController extends Controller
{
    public function index()
    {
        $this->authorizeAccess();

        $receipts = BookingGoodsReceipt::with(['booking.customer', 'booking.user', 'user'])
            ->orderByDesc('id')
            ->get();

        return view('booking.goods_received', compact('receipts'));
    }

    public function generate($bookingId)
    {
        $this->authorizeAccess();

        $booking = Booking::with(['bookingProduct.product', 'customer', 'biller', 'warehouse'])->findOrFail($bookingId);
        $receipt = BookingGoodsReceipt::where('booking_id', $booking->id)->first();

        if (!$receipt) {
            $receipt = BookingGoodsReceipt::create([
                'booking_id' => $booking->id,
                'user_id' => Auth::id(),
                'reference_no' => 'gdn-' . date('Ymd') . '-' . date('his'),
                'signature_token' => Str::random(48),
            ]);
        }

        $receipt->delivery_note_pdf_path = $this->buildDeliveryNotePdf($receipt);
        $receipt->save();

        return redirect()
            ->route('booking.goods-received')
            ->with('message', 'Goods delivery note generated for booking ' . $booking->reference_no . '.');
    }

    public function deliveryNote($id)
    {
        $this->authorizeAccess();

        $receipt = BookingGoodsReceipt::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product'])
            ->findOrFail($id);

        if ($receipt->delivery_note_pdf_path && file_exists(public_path($receipt->delivery_note_pdf_path))) {
            return response()->file(public_path($receipt->delivery_note_pdf_path));
        }

        $path = $this->buildDeliveryNotePdf($receipt);
        $receipt->update(['delivery_note_pdf_path' => $path]);

        return response()->file(public_path($path));
    }

    public function sendSignature($id)
    {
        $this->authorizeAccess();

        $receipt = BookingGoodsReceipt::with('booking.customer')->findOrFail($id);

        if ($receipt->signed_at) {
            return back()->with('not_permitted', 'Goods receipt is already signed.');
        }

        $booking = $receipt->booking;
        $customer = $booking->customer;

        if (!$customer || empty(trim((string) $customer->phone_number))) {
            return back()->with('not_permitted', 'Customer phone number is missing. Add a phone number before sending for signature.');
        }

        $items = $this->buildItemList($booking);

        try {
            $link = url('goods-received/' . $receipt->signature_token);
            $msg = WhatsAppMessage::goodsReceivedSignatureRequest(
                $customer->name,
                $booking->reference_no,
                $receipt->reference_no,
                $link,
                $items,
                'received'
            );
            $this->sendWhatsAppToCustomer($customer, $msg);
            $receipt->update(['signature_sent_at' => now()]);
        } catch (\Exception $e) {
            return back()->with('not_permitted', 'Could not send signature link: ' . $e->getMessage());
        }

        $ccCount = $this->sendDeliveredSignatureToCc($receipt, $booking, $items);

        $note = 'Goods received signature link sent to the client via WhatsApp.';
        if ($ccCount > 0) {
            $note .= ' Delivery note also sent to ' . $ccCount . ' CC contact' . ($ccCount === 1 ? '' : 's') . ' to sign as delivered.';
        }

        return back()->with('message', $note);
    }

    private function sendDeliveredSignatureToCc(BookingGoodsReceipt $receipt, Booking $booking, array $items)
    {
        if (empty($booking->cc_customer_ids)) {
            return 0;
        }

        $ccIds = array_filter(explode(',', $booking->cc_customer_ids));
        if (empty($ccIds)) {
            return 0;
        }

        $sent = 0;
        $link = url('goods-received/' . $receipt->signature_token . '?role=delivered');

        foreach ($ccIds as $customerId) {
            $ccCustomer = Customer::find($customerId);
            if (!$ccCustomer || empty(trim((string) $ccCustomer->phone_number))) {
                continue;
            }

            try {
                $msg = WhatsAppMessage::goodsReceivedSignatureRequest(
                    $ccCustomer->name,
                    $booking->reference_no,
                    $receipt->reference_no,
                    $link,
                    $items,
                    'delivered'
                );
                $this->sendWhatsAppToCustomer($ccCustomer, $msg);
                $sent++;
            } catch (\Exception $e) {
            }
        }

        if ($sent > 0) {
            $receipt->update(['delivered_signature_sent_at' => now()]);
        }

        return $sent;
    }

    public function resend($id)
    {
        return $this->sendSignature($id);
    }

    public function show(Request $request, $token)
    {
        $receipt = $this->findReceipt($token);
        $role = $request->query('role') === 'delivered' ? 'delivered' : 'received';

        $alreadySigned = $role === 'delivered' ? $receipt->delivered_signed_at : $receipt->signed_at;
        if ($alreadySigned) {
            return view('booking.goods_received_signed', compact('receipt', 'role'));
        }

        $booking = $receipt->booking;
        $general_setting = GeneralSetting::first();
        $items = $this->buildItemList($booking);

        return view('booking.goods_received_sign', compact('receipt', 'booking', 'general_setting', 'items', 'role'));
    }

    public function sign(Request $request, $token)
    {
        $receipt = $this->findReceipt($token);
        $role = $request->input('role') === 'delivered' ? 'delivered' : 'received';

        $alreadySigned = $role === 'delivered' ? $receipt->delivered_signed_at : $receipt->signed_at;
        if ($alreadySigned) {
            return redirect()->route('goods.received.show', ['token' => $token, 'role' => $role])
                ->with('message', 'You have already signed this goods receipt.');
        }

        $request->validate([
            'receipt_confirmed' => 'required|accepted',
            'signature_image' => 'required|string',
            'signer_name' => 'nullable|string|max:191',
        ]);

        if ($role === 'delivered') {
            $receipt->update([
                'delivered_signed_at' => now(),
                'delivered_signature_image' => $request->signature_image,
                'delivered_by_name' => $request->signer_name,
                'signed_pdf_path' => null,
            ]);
        } else {
            $receipt->update([
                'signed_at' => now(),
                'signature_image' => $request->signature_image,
                'signed_pdf_path' => null,
            ]);
        }

        $receipt->signed_pdf_path = $this->buildSignedReceiptPdf($receipt->fresh());
        $receipt->save();

        $booking = $receipt->booking;
        $customer = $booking->customer;

        if ($role === 'received') {
            try {
                if ($customer && !empty(trim((string) $customer->phone_number))) {
                    $msg = WhatsAppMessage::goodsReceivedSignedClient(
                        $customer->name,
                        $booking->reference_no,
                        $receipt->reference_no
                    );
                    $this->sendWhatsAppToCustomer($customer, $msg);

                    if ($receipt->signed_pdf_path && file_exists(public_path($receipt->signed_pdf_path))) {
                        $this->sendWhatsAppDocumentToCustomer(
                            $customer,
                            public_path($receipt->signed_pdf_path),
                            'goods_received_' . $receipt->reference_no . '.pdf',
                            url($receipt->signed_pdf_path)
                        );
                    }
                }
            } catch (\Exception $e) {
            }

            $confirmation = 'Thank you. Your goods receipt has been signed successfully.';
        } else {
            $confirmation = 'Thank you. You have signed to confirm the goods were delivered.';
        }

        return redirect()->route('goods.received.show', ['token' => $token, 'role' => $role])
            ->with('message', $confirmation);
    }

    public function signedPdf($id)
    {
        $this->authorizeAccess();

        $receipt = BookingGoodsReceipt::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product'])
            ->findOrFail($id);

        if (!$receipt->signed_at) {
            return back()->with('not_permitted', 'Goods receipt has not been signed yet.');
        }

        if ($receipt->signed_pdf_path && file_exists(public_path($receipt->signed_pdf_path))) {
            return response()->file(public_path($receipt->signed_pdf_path));
        }

        $path = $this->buildSignedReceiptPdf($receipt);
        $receipt->update(['signed_pdf_path' => $path]);

        return response()->file(public_path($path));
    }

    private function buildDeliveryNotePdf(BookingGoodsReceipt $receipt)
    {
        $booking = $receipt->booking()->with(['bookingProduct.product', 'customer', 'biller', 'warehouse'])->first();
        $general_setting = GeneralSetting::first();
        $items = $this->buildItemList($booking);

        $directory = public_path('booking_delivery_notes');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $relativePath = 'booking_delivery_notes/delivery_' . $receipt->reference_no . '.pdf';
        $pdf = PDF::loadView('pdf.booking_delivery_note', compact('receipt', 'booking', 'general_setting', 'items'));
        $pdf->save(public_path($relativePath));

        return $relativePath;
    }

    private function buildSignedReceiptPdf(BookingGoodsReceipt $receipt)
    {
        $booking = $receipt->booking()->with(['bookingProduct.product', 'customer', 'biller', 'warehouse'])->first();
        $general_setting = GeneralSetting::first();
        $items = $this->buildItemList($booking);
        $signatureSrc = $this->signatureDisplaySrc($receipt->signature_image);
        $deliveredSignatureSrc = $this->signatureDisplaySrc($receipt->delivered_signature_image);

        $directory = public_path('booking_delivery_notes');
        if (!File::exists($directory)) {
            File::makeDirectory($directory, 0755, true);
        }

        $relativePath = 'booking_delivery_notes/signed_' . $receipt->reference_no . '.pdf';
        $pdf = PDF::loadView('pdf.booking_goods_received_signed', compact('receipt', 'booking', 'general_setting', 'items', 'signatureSrc', 'deliveredSignatureSrc'));
        $pdf->save(public_path($relativePath));

        return $relativePath;
    }

    private function signatureDisplaySrc($data)
    {
        if (empty($data)) {
            return null;
        }

        if (strpos($data, 'data:image') === 0) {
            return $data;
        }

        return 'data:image/png;base64,' . $data;
    }

    private function buildItemList(Booking $booking)
    {
        $items = [];

        foreach ($booking->bookingProduct as $line) {
            $product = $line->product;
            $items[] = [
                'name' => $product ? $product->name : 'Equipment',
                'code' => $product ? $product->code : '',
                'qty' => $line->qty,
                'start' => $line->start,
                'end' => $line->end,
            ];
        }

        return $items;
    }

    private function findReceipt($token)
    {
        return BookingGoodsReceipt::with(['booking.customer', 'booking.biller', 'booking.bookingProduct.product'])
            ->where('signature_token', $token)
            ->firstOrFail();
    }

    private function authorizeAccess()
    {
        $role = Role::find(Auth::user()->role_id);
        if (!$role || !$role->hasPermissionTo('booking_index')) {
            abort(403);
        }
    }
}
