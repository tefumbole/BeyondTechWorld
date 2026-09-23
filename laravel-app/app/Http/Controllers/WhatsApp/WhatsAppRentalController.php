<?php

namespace App\Http\Controllers\WhatsApp;

use App\Http\Controllers\Controller;
use App\Quotation;
use App\Services\Rental\RentalQuoteService;
use App\Services\Rental\RentalRequestService;
use App\WhatsApp\RentalRequest;
use App\WhatsApp\WhatsAppConversation;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;

class WhatsAppRentalController extends Controller
{
    public function index()
    {
        if (! Schema::hasTable('whatsapp_rental_requests')) {
            return redirect()->route('whatsapp.index')->with('not_permitted', 'Rental requests are not installed.');
        }
        $rows = RentalRequest::orderByDesc('id')->limit(100)->get();

        return view('whatsapp_hub.rentals', compact('rows'));
    }

    public function approve($id)
    {
        if (! $this->canApprove()) {
            return redirect()->back()->with('not_permitted', 'You cannot approve WhatsApp quotations.');
        }
        $request = RentalRequest::findOrFail($id);
        $quotation = $request->quotation_id ? Quotation::find($request->quotation_id) : null;
        $conversation = WhatsAppConversation::find($request->conversation_id);
        if (! $quotation || ! $conversation) {
            return redirect()->back()->with('not_permitted', 'This request has no draft quotation yet.');
        }
        $result = app(RentalQuoteService::class)->approveAndSend($quotation, $conversation, Auth::id());
        app(RentalRequestService::class)->log($request, 'staff_approval', ! empty($result['success']) ? 'Approved and sent' : (isset($result['error']) ? $result['error'] : 'failed'), $result, Auth::id());
        if (empty($result['success'])) {
            $message = $result['error'] === 'availability_changed'
                ? 'Availability changed. The quotation was not sent.'
                : 'The quotation was kept as a draft. The PDF was not sent.';

            return redirect()->back()->with('not_permitted', $message);
        }

        return redirect()->back()->with('message', 'Quotation '.$result['reference'].' was approved and sent.');
    }

    public function reject($id)
    {
        if (! $this->canApprove()) {
            return redirect()->back()->with('not_permitted', 'You cannot reject WhatsApp quotations.');
        }
        $request = RentalRequest::findOrFail($id);
        $request->status = RentalRequest::CANCELLED;
        $request->save();
        app(RentalRequestService::class)->log($request, 'rejected', 'Staff rejected the rental request.', [], Auth::id());

        return redirect()->back()->with('message', 'Rental request rejected.');
    }

    protected function canApprove()
    {
        $user = Auth::user();
        if (! $user) {
            return false;
        }
        $role = \Spatie\Permission\Models\Role::find($user->role_id);
        if (! $role) {
            return false;
        }
        try {
            return $role->hasPermissionTo('whatsapp.quotation.approve');
        } catch (\Exception $e) {
            return false;
        }
    }
}
