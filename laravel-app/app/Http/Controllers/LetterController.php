<?php

namespace App\Http\Controllers;

use App\Customer;
use App\CustomerGroup;
use App\Department;
use App\Employee;
use App\GeneralSetting;
use App\Jobs\ProcessQueue;
use App\LetterAttachment;
use App\Mail\UserNotification;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Letter;
use App\LetterCategory;
use App\LetterTemplate;
use App\Support\LetterRecipients;
use App\Support\LetterSignature;
use App\User;
use PDF;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Spatie\Permission\Models\Role;
use Twilio\Rest\Client;

class LetterController extends Controller
{
    private $user;

    public function __construct() {


        $this->middleware(function ($request, $next) {
            $this->user = Auth::user();
            $role = Role::find($this->user->role_id);
            $permissions = Role::findByName($role->name)->permissions;

            foreach ($permissions as $permission) {
                $all_permission[] = $permission->name;
            }
            View::share ( 'all_permission', $all_permission);

            return $next($request);
        });
    }

    public function checkOtp($request, $letter) {
        if ($this->user->otp_verify == 1) {
            return true;
        }
        if($request->otp == $letter->otp && $letter->otp_time > date('Y-m-d H:i:s', strtotime('-3 minutes'))) {
            return true;
        }
        return false;
    }

    public function next($id) {
        $letter = Letter::find($id);
        $data = Letter::where('is_edit',  $letter->is_edit)
            ->where('is_approve',  $letter->is_approve)
            ->where('is_sign',  $letter->is_sign)
            ->where('is_sent',  $letter->is_sent)
            ->where('is_rejected', $letter->is_rejected)
            ->where('is_active', true)
            ->where('id', '<', $id)
            ->orderBy('id', 'desc')
            ->first();
        if ($data == null) {
            return back()->with('not_permitted', 'No more letter found');
        }

        return view('letter.show', compact('data'));

    }

    public function prev($id) {
        $letter = Letter::find($id);
        $data = Letter::where('is_edit',  $letter->is_edit)
            ->where('is_approve',  $letter->is_approve)
            ->where('is_sign',  $letter->is_sign)
            ->where('is_sent',  $letter->is_sent)
            ->where('is_rejected', $letter->is_rejected)
            ->where('is_active', true)
            ->where('id', '>', $id)
            ->first();
        if ($data == null) {
            return back()->with('not_permitted', 'No more letter found');
        }

        return view('letter.show', compact('data'));

    }
    public function index()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_edit', 0)
            ->where('is_approve', 0)
            ->where('is_sign', 0)
            ->where('is_sent', 0)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.index', compact('data'));
    }

    public function all()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.all', compact('data'));
    }

    public function rejected()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_rejected', 1)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.rejected', compact('data'));
    }

    public function approved()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_approve', 1)
            ->where('is_sign', 0)
            ->where('is_sent', 0)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.approved', compact('data'));
    }

    public function edited()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_edit', 1)
            ->where('is_approve', 0)
            ->where('is_sign', 0)
            ->where('is_sent', 0)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.edited', compact('data'));
    }

    public function signed()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_approve', 1)
            ->where('is_sign', 1)
            ->where('is_sent', 0)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.signed', compact('data'));
    }

    public function sent()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_approve', 1)
            ->where('is_sign', 1)
            ->where('is_sent', 1)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.sent', compact('data'));
    }

    public function sentPrint()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_approve', 1)
            ->where('is_sign', 1)
            ->where('is_sent', 1)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.sent_print', compact('data'));
    }

    public function sentDownload()
    {
        $data = Letter::with('category')
            ->where('is_active', true)
            ->where('is_approve', 1)
            ->where('is_sign', 1)
            ->where('is_sent', 1)
            ->where('is_rejected', 0)
            ->orderBy('id', 'desc')
            ->get();
        return view('letter.sent_download', compact('data'));
    }


    public function create()
    {
        $category = LetterCategory::where('is_active', true)->get();
        $template = LetterTemplate::where('is_active', true)->get();
        $user = LetterRecipients::employees();
        $customer = LetterRecipients::customers();
        $departments = Department::where('is_active', true)->get();
        $customerGroups = CustomerGroup::where('is_active', true)->get();
        $clone = null;
        $cloneToIds = [];
        $cloneCcIds = [];
        $clonePeopleType = '';

        return view('letter.create', compact('category', 'template', 'user', 'customer', 'customerGroups', 'departments', 'clone', 'cloneToIds', 'cloneCcIds', 'clonePeopleType'));
    }

    public function cloneLetter($id)
    {
        $category = LetterCategory::where('is_active', true)->get();
        $template = LetterTemplate::where('is_active', true)->get();
        $user = LetterRecipients::employees();
        $customer = LetterRecipients::customers();
        $departments = Department::where('is_active', true)->get();
        $customerGroups = CustomerGroup::where('is_active', true)->get();
        $clone = Letter::findOrFail($id);
        $resolved = $this->resolveCloneRecipients($clone);
        $clonePeopleType = $resolved['peopleType'];
        $cloneToIds = $resolved['toIds'];
        $cloneCcIds = $resolved['ccIds'];

        return view('letter.create', compact('category', 'template', 'user', 'customer', 'customerGroups', 'departments', 'clone', 'cloneToIds', 'cloneCcIds', 'clonePeopleType'));
    }

    /**
     * Store a newly created resource in storage.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\Response
     */
    public function store(Request $request)
    {
        try {
            $data = $request->all();

            if (empty($data['people_type'])) {
                return $this->letterStoreResponse($request, false, 'Please select a people type.', 422);
            }

            if($data['people_type'] == "customer") {
                if ($data['customer_type'] == "customer_group") {
                    $customer_group = Customer::whereIn('customer_group_id', $data['to_customer_group'] ?? [])->where('is_active', 1)->pluck('id')->toArray();
                    $data['to'] = implode(",", $customer_group);
                } elseif (!empty($data['people_type_mode']) && $data['people_type_mode'] === 'all_customers') {
                    $data['to'] = implode(",", LetterRecipients::allCustomerIds());
                } else {
                    $toCustomer = $data['to_customer'] ?? [];
                    if (!is_array($toCustomer)) {
                        $toCustomer = [$toCustomer];
                    }
                    $toCustomer = array_values(array_filter($toCustomer));
                    if (empty($toCustomer)) {
                        return $this->letterStoreResponse($request, false, 'Please select at least one customer recipient.', 422);
                    }
                    $data['to'] = implode(",", $toCustomer);
                }
                $ccCustomer = $data['cc_customer'] ?? [];
                $data['cc'] = !empty($ccCustomer) ? implode(",", (array) $ccCustomer) : null;
            } else if($data['people_type'] == "user") {
                if (!empty($data['people_type_mode']) && $data['people_type_mode'] === 'all_employees') {
                    $data['to'] = implode(",", LetterRecipients::allEmployeeIds());
                } else {
                    $toUsers = $data['to'] ?? [];
                    if (!is_array($toUsers)) {
                        $toUsers = [$toUsers];
                    }
                    $toUsers = array_values(array_filter($toUsers));
                    if (empty($toUsers)) {
                        return $this->letterStoreResponse($request, false, 'Please select at least one employee recipient.', 422);
                    }
                    $data['to'] = implode(",", $toUsers);
                }
                $ccUsers = $data['cc'] ?? [];
                $data['cc'] = !empty($ccUsers) ? implode(",", (array) $ccUsers) : null;
            } else if($data['people_type'] == "all") {
                $data['to'] = LetterRecipients::encodeAllRecipients();
                $data['cc'] = null;
            } else if($data['people_type'] == "csv") {
                $to_csv = $request->to_csv;
                if (isset($to_csv)) {
                    $imageName = date("Ymdhis").$to_csv->getClientOriginalName();
                    $to_csv->move('public/letter/csv', $imageName);
                    $data['to'] = $imageName;
                } else {
                    return $this->letterStoreResponse($request, false, 'Please upload a CSV file for recipients.', 422);
                }
            }

            if (empty($data['to'])) {
                return $this->letterStoreResponse($request, false, 'Please choose who should receive this letter.', 422);
            }

            $image = $request->attachments;
            if (isset($image[0])) {
                $imageName = date("Ymdhis").$image[0]->getClientOriginalName();
                $image[0]->move('public/letter/attachment', $imageName);
                $data['attachment'] = $imageName;
            }

            $is_template = false;
            $data['created_by'] = Auth::user()->id;
            $data['is_edit'] = 0;
            $data['is_approve'] = 0;
            $data['is_sign'] = 0;
            $data['is_sent'] = 0;
            $data['is_rejected'] = 0;
            $data['edit_by'] = null;
            $data['approved_by'] = null;
            $data['signed_by'] = null;
            $data['reject_by'] = null;
            $data['sent_by'] = null;

            $letter_id = Letter::count('id');
            if(!$letter_id) {
                $letter_id = 0;
            }
            $zero = substr('0000000', strlen($letter_id));
            $letter_id++;
            $data['reference'] = GeneralSetting::first()->letter_serial_no . '/' . date('y') . '/' . $zero . $letter_id;

            if(isset($data['is_template'])) {
                $is_template = true;
            }

            if(isset($data['forward_letter'])) {
                if($data['forward_letter'] == 'editor') {
                    $data['is_rejected'] = 0;
                    $data['reject_by'] = null;
                    $data['is_edit'] = 0;
                    $data['edit_by'] = null;
                    $data['is_approve'] = 0;
                    $data['approved_by'] = null;
                    $data['is_sign'] = 0;
                    $data['signed_by'] = null;
                }
                if($data['forward_letter'] == 'approver') {
                    $data['is_rejected'] = 0;
                    $data['reject_by'] = null;
                    $data['is_edit'] = 1;
                    $data['edit_by'] = $data['created_by'];
                }
                if($data['forward_letter'] == 'signer') {
                    $data['is_rejected'] = 0;
                    $data['reject_by'] = null;
                    $data['is_edit'] = 1;
                    $data['edit_by'] = $data['created_by'];
                    $data['is_approve'] = 1;
                    $data['approved_by'] = $data['created_by'];
                }
                if($data['forward_letter'] == 'sender') {
                    $data['is_rejected'] = 0;
                    $data['reject_by'] = null;
                    $data['is_edit'] = 1;
                    $data['edit_by'] = $data['created_by'];
                    $data['is_approve'] = 1;
                    $data['approved_by'] = $data['created_by'];
                    $data['is_sign'] = 1;
                    $data['signed_by'] = $data['created_by'];
                }
            }

            unset($data['customer_type']);
            unset($data['people_type_mode']);
            unset($data['to_customer_group']);
            unset($data['is_template']);
            unset($data['to_customer']);
            unset($data['cc_customer']);
            unset($data['to_csv']);
            if(isset($data['attachments'])) {
                $data_multiple['attachments'] = $data['attachments'];
            }
            unset($data['attachments']);

            if(isset($data['forward_letter'])) {
                unset($data['forward_letter']);
            }
            $letter = Letter::create($data);
            $letterId = $letter->id;
            $controller = $this;
            register_shutdown_function(function () use ($controller, $letterId) {
                try {
                    $savedLetter = Letter::find($letterId);
                    if ($savedLetter) {
                        $controller->sendMsgToConcernPerson($savedLetter);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('Letter notification failed: ' . $e->getMessage());
                }
            });

            if($is_template == true) {
                if(!isset($data['footer'])) {
                    $data['footer'] = $data['name'];
                }
                unset($data['people_type']);
                unset($data['attachment']);
                unset($data['template_id']);
                unset($data['reference']);
                unset($data['is_approve']);
                unset($data['is_rejected']);
                unset($data['reject_by']);
                unset($data['is_edit']);
                unset($data['edit_by']);
                unset($data['approved_by']);
                unset($data['is_sign']);
                unset($data['to']);
                unset($data['cc']);
                unset($data['comment']);
                LetterTemplate::create($data);
            }

            $attachments = isset($data_multiple['attachments']) ? $data_multiple['attachments'] : [];
            if ($attachments) {
                foreach ($attachments as $key => $attachment) {
                    if($key == 0) {
                        LetterAttachment::create(['letter_id' => $letter->id, 'attachment' => $imageName]);
                    } else {
                        $attachmentName = date("Ymdhis").$attachments[$key]->getClientOriginalName();
                        $attachments[$key]->move('public/letter/attachment', $attachmentName);
                        LetterAttachment::create(['letter_id' => $letter->id, 'attachment' => $attachmentName]);
                    }
                }
            }

            return $this->letterStoreResponse($request, true, 'Letter created successfully', 200, $letter->id);
        } catch (\Throwable $e) {
            \Log::error('Letter store failed: ' . $e->getMessage());

            return $this->letterStoreResponse($request, false, 'Failed to save letter. Please check all fields and try again.', 500);
        }
    }

    protected function letterStoreResponse(Request $request, bool $success, string $message, int $status = 200, $letterId = null)
    {
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'success' => $success,
                'message' => $message,
                'redirect' => route('letter.index'),
                'letter_id' => $letterId,
            ], $status);
        }

        if ($success) {
            return redirect()->route('letter.index')->with('message', $message);
        }

        return redirect()->back()->withInput()->with('not_permitted', $message);
    }

    /**
     * Display the specified resource.
     *
     * @param  \App\Letter  $letter
     * @return \Illuminate\Http\Response
     */
    public function show(Letter $letter, $id)
    {
        $data = Letter::with('category', 'createdBy', 'approvedBy', 'attachmentLib')->where('id', $id)->first();
        return view('letter.show', compact('data'));
    }

    /**
     * Show the form for editing the specified resource.
     *
     * @param  \App\Letter  $letter
     * @return \Illuminate\Http\Response
     */
    public function edit(Letter $letter, $id)
    {
        $category = LetterCategory::where('is_active', true)->get();
        $template = LetterTemplate::where('is_active', true)->get();
        $data = $letter->findorfail($id);
        if ($data->people_type == 'user') {
            $user = Employee::where('is_active', true)->get();
        } else if ($data->people_type == 'customer') {
            $user = Customer::where('is_active', true)->get();
        } else {
            $user = null;
        }

        return view('letter.edit', compact('category', 'template', 'user', 'data'));
    }

    public function letterAttachmentDelete($id)
    {
        $attachment = LetterAttachment::where('id', $id)->first();
        @unlink('public/letter/attachment/'.$attachment->attachment);
        $attachment->delete();
        return back();
    }

    public function letterAttachmentDeleteFirst($id)
    {
        $letter = Letter::where('id', $id)->first();
        @unlink('public/letter/attachment/'.$letter->attachment);
        $letter->update(['attachment' => null]);
        return back()->with('not_permitted', 'Letter attachment deleted successfully');
    }

    public function editLast(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);
        if ($data->people_type == 'user') {
            $user = Employee::where('is_active', true)->get();
        } else if ($data->people_type == 'customer') {
            $user = Customer::where('is_active', true)->get();
        } else {
            $user = null;
        }
        return view('letter.edit_last', compact( 'user', 'data'));
    }

    public function updateLast(Request $request, Letter $letter, $id)
    {
        $data = $request->all();
        $letter = $letter->find($id);

        $attachments = $request->attachments;
        if ($attachments) {
            foreach ($attachments as $key => $attachment) {
                $attachmentName = date("Ymdhis").$attachments[$key]->getClientOriginalName();
                $attachments[$key]->move('public/letter/attachment', $attachmentName);
                LetterAttachment::create(['letter_id' => $id, 'attachment' => $attachmentName]);
            }
        }
        if($letter->people_type == "csv") {
            $to_csv = $request->to_csv;
            if (isset($to_csv)) {
                $imageName = date("Ymdhis").$to_csv->getClientOriginalName();
                $to_csv->move('public/letter/csv', $imageName);
                $data['to'] = $imageName;
            }
        } else {
            $data['to'] = implode(",", $data['to']);
            $data['cc'] = isset($data['cc']) ? implode(",", $data['cc']) : null;
        }
        unset($data['attachments']);
        unset($data['to_csv']);
        $data['edit_by'] = Auth::user()->id;
        $letter->update($data);

        return redirect()->route('letter.index.signed')->with('message', 'Letter updated successfully');
    }
    public function update(Request $request, Letter $letter, $id)
    {
        $data = $request->all();
        $letter = $letter->find($id);
        $attachments = $request->attachments;
        if ($attachments) {
            foreach ($attachments as $key => $attachment) {
                $attachmentName = date("Ymdhis").$attachments[$key]->getClientOriginalName();
                $attachments[$key]->move('public/letter/attachment', $attachmentName);
                LetterAttachment::create(['letter_id' => $id, 'attachment' => $attachmentName]);
            }
        }

        if($letter->people_type == "csv") {
            $to_csv = $request->to_csv;
            if (isset($to_csv)) {
                $imageName = date("Ymdhis").$to_csv->getClientOriginalName();
                $to_csv->move('public/letter/csv', $imageName);
                $data['to'] = $imageName;
            }
        } else {
            $data['to'] = implode(",", $data['to']);
            $data['cc'] = isset($data['cc']) ? implode(",", $data['cc']) : null;
        }
        unset($data['attachments']);
        unset($data['to_csv']);
        unset($data['signature_image']);
        $data['is_rejected'] = 0;
        $data['reject_by'] = null;
        $data['is_edit'] = 1;
        $data['edit_by'] = Auth::user()->id;
        $signature = $this->saveSignatureFromRequest($request, 'edit');
        if (!$signature) {
            return back()->with('not_permitted', 'Please provide your editor signature.');
        }
        $data['edit_signature'] = $signature;
        $data['edit_signed_at'] = now();
        $letter->update($data);
        $this->sendMsgToConcernPerson($letter);

        return redirect()->route('letter.index.edited')->with('message', 'Letter updated successfully. It is now awaiting approval.');
    }

    public function editOk(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);
        return view('letter.edit_ok', compact('data'));
    }

    public function editOkStore(Request $request, Letter $letter, $id)
    {
        $signature = $this->saveSignatureFromRequest($request, 'edit');
        if (!$signature) {
            return back()->with('not_permitted', 'Please provide your editor signature.');
        }

        $letter = $letter->find($id);
        $letter->update([
            'is_rejected' => 0,
            'reject_by' => null,
            'is_edit' => 1,
            'edit_by' => Auth::user()->id,
            'edit_signature' => $signature,
            'edit_signed_at' => now(),
            'otp' => null,
        ]);
        $this->sendMsgToConcernPerson($letter);

        return redirect()->route('letter.index.edited')->with('message', 'Letter updated successfully. It is now awaiting approval.');
    }

    public function sendOTP($data) {
        if ($this->user->otp_verify == 1) {
            return true;
        }
        if ($data->otp_time == null || $data->otp_time < date('Y-m-d H:i:s', strtotime('-1 minutes'))) {
            $otp = rand(1, 999999);
            $msg = "Your OTP is: " . $otp . "\n That will be expired after 2 minutes";
            try {
                $this->wpMessage(Auth::user()->phone, $msg);
                $data->update(['otp'=>$otp, 'otp_time'=>date('Y-m-d H:i:s')]);
            } catch (\Exception $e) {
                return $otp;
            }
            return $otp;
        }
    }

    public function approve(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);
        return view('letter.approve', compact('data'));
    }

    public function approveStore(Request $request, Letter $letter, $id)
    {
        $signature = $this->saveSignatureFromRequest($request, 'approve');
        if (!$signature) {
            return back()->with('not_permitted', 'Please provide your approver signature.');
        }

        $letter = $letter->find($id);
        $letter->update([
            'is_approve' => 1,
            'approved_by' => Auth::user()->id,
            'approve_signature' => $signature,
            'approve_signed_at' => now(),
            'otp' => null,
        ]);
        $this->sendMsgToConcernPerson($letter);

        return redirect()->route('letter.index.approved')->with('message', 'Letter Approved successfully. It is now awaiting signature.');
    }

    public function sendMsgToConcernPerson($letter) {
        $role_id = 9;
        $role_name = 'Editor';
        $action = 'editing';
        if ($letter->is_approve == 0) {
            $role_id = 10;
            $role_name = 'Approver';
            $action = 'approve';
        } elseif ($letter->is_sign == 0) {
            $role_id = 11;
            $role_name = 'Signer';
            $action = 'signing';
        }


        $msg = 'Dear '.$role_name.', A new letter from '.@$letter->createdBy->name.', with the subject ('.$letter->subject.') is available for '.$action.'. Here is the comment('.$letter->comment.') attached to the letter.';
        $msg .= "\n\nPlease click the link below to " . $action . ": ".request()->getSchemeAndHttpHost()."/letters/show/".$letter->id."\n\n";
        $msg .= request()->getSchemeAndHttpHost();

        $users = User::where('role_id', $role_id)->where('is_active', true)->get()->toArray();

        if (empty($users)) {
            return true;
        }
        foreach ($users as $user) {
            try {
                $this->wpMessage($user['phone'], $msg);
            } catch (\Exception $e) {
                return false;
            }
        }
    }

    public function reject(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);

        if (Auth::user()->otp_verify == 1) {
            $data->update(['is_rejected'=>true, 'is_edit' => 0, 'edit_by' => null, 'reject_by'=>Auth::user()->id, 'otp' => null]);
            $this->sendMsgToConcernPerson($data);
            return redirect()->back()->with('message', 'Letter Rejected successfully');
        }
        $this->sendOTP($data);
        return view('letter.reject', compact('data'));
    }

    public function rejectStore(Request $request, Letter $letter, $id)
    {
        $letter = $letter->find($id);

        if ($this->checkOtp($request, $letter) == true) {
            $letter->update(['is_rejected'=>true, 'is_edit' => 0, 'edit_by' => null, 'reject_by'=>Auth::user()->id, 'otp' => null]);
            $this->sendMsgToConcernPerson($letter);
            return redirect()->back()->with('message', 'Letter Rejected successfully');
        }
        $letter->find($id)->update(['otp' => null]);
        return back()->with('not_permitted', 'OTP is wrong or Expired');

    }

    public function send(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);
        if (Auth::user()->otp_verify == 1) {
            if ($data->people_type == 'customer') {
                $customer = Customer::class;
            } elseif ($data->people_type == 'all') {
                $customer = Customer::class;
            } else {
                $customer = Employee::class;
            }

            ProcessQueue::dispatch($data, $id, $customer);

            $data->update(['is_sent' => true, 'sent_by' => Auth::user()->id, 'otp' => null]);
            return redirect()->back()->with('message', 'Letter will sent soon');
        }
        $this->sendOTP($data);
        return view('letter.send', compact('data'));
    }

    public function sendStore(Request $request, Letter $letter, $id)
    {
        $letter = $letter->find($id);
        if($letter->people_type == 'customer') {
            $customer = Customer::class;
        } elseif ($letter->people_type == 'all') {
            $customer = Customer::class;
        } else {
            $customer = Employee::class;
        }

        if ($this->checkOtp($request, $letter) == true) {

            ProcessQueue::dispatch($letter, $id, $customer);

//            foreach (explode(",", $letter->to) as $to) {
//                $lims_customer_data = $customer::find($to);
//                $message = $this->sendPDF($letter, $lims_customer_data, $to);
//                $message = $this->sendMail($letter, $lims_customer_data, $to);
//            }
//            if ($letter->cc != null) {
//                foreach (explode(",", $letter->cc) as $cc) {
//                    $lims_customer_data = $customer::find($cc);
//                    $this->sendPDFToCC($letter, $lims_customer_data, $letter->to);
//                }
//            }

            $letter->update(['is_sent'=>true, 'sent_by'=>Auth::user()->id, 'otp' => null]);
            return redirect()->back()->with('message', 'Letter will sent soon');
        }
        $letter->update(['otp' => null]);
        return back()->with('not_permitted', 'OTP is wrong or Expired');

    }

    public function download(Letter $letter, $id)
    {
        $letter = $letter->find($id);
        if($letter->people_type == 'customer') {
            $customer = Customer::class;
        } elseif ($letter->people_type == 'all') {
            $customer = Customer::class;
        } else {
            $customer = Employee::class;
        }
        $data = [
            'data' => $letter,
            'user' => $customer,
            'people_type' => $letter->people_type
        ];

        if ($letter->people_type === 'csv') {
            $recipients = [];
            $csv_path = public_path(env('LETTER_CSV_PATH'));
            $csvFilePath = $csv_path.$letter->to;
            $file = fopen($csvFilePath, 'r');

            if ($file !== false) {
                $firstRow = true;
                while (($row = fgetcsv($file)) !== false) {
                    if ($firstRow) { $firstRow = false; continue;}
                    $r = (object) [];
                    $r->name = $row[0] ?? '';
                    $r->phone_number = $row[1] ?? '';
                    $r->email = $row[2] ?? '';
                    $r->address = $row[3] ?? '';
                    $r->column1 = $row[4] ?? '';
                    $r->column2 = $row[5] ?? '';
                    $r->column3 = $row[6] ?? '';
                    $r->column4 = $row[7] ?? '';
                    $r->column5 = $row[8] ?? '';
                    $r->column6 = $row[9] ?? '';
                    $r->column7 = $row[10] ?? '';
                    $r->column8 = $row[11] ?? '';
                    $r->column9 = $row[12] ?? '';
                    $r->column10 = $row[13] ?? '';
                    $recipients[] = $r;
                }
                fclose($file);
            }
            $data['recipients'] = $recipients;
        }

//        return view('pdf.letter_download_pdf', $data);

        $pdf = PDF::loadView('pdf.letter_download_pdf', $data)->setPaper('A4', 'portrait');
        return $pdf->download('letter.pdf');

    }

    public function print(Letter $letter, $id)
    {
        $letter = $letter->find($id);
        if($letter->people_type == 'customer') {
            $customer = Customer::class;
        } elseif ($letter->people_type == 'all') {
            $customer = Customer::class;
        } else {
            $customer = Employee::class;
        }
        $data = [
            'data' => $letter,
            'user' => $customer,
            'people_type' => $letter->people_type
        ];

        if ($letter->people_type === 'csv') {
            $recipients = [];
            $csv_path = public_path(env('LETTER_CSV_PATH'));
            $csvFilePath = $csv_path.$letter->to;
            $file = fopen($csvFilePath, 'r');

            if ($file !== false) {
                $firstRow = true;
                while (($row = fgetcsv($file)) !== false) {
                    if ($firstRow) { $firstRow = false; continue;}
                    $r = (object) [];
                    $r->name = $row[0] ?? '';
                    $r->phone_number = $row[1] ?? '';
                    $r->email = $row[2] ?? '';
                    $r->address = $row[3] ?? '';
                    $r->column1 = $row[4] ?? '';
                    $r->column2 = $row[5] ?? '';
                    $r->column3 = $row[6] ?? '';
                    $r->column4 = $row[7] ?? '';
                    $r->column5 = $row[8] ?? '';
                    $r->column6 = $row[9] ?? '';
                    $r->column7 = $row[10] ?? '';
                    $r->column8 = $row[11] ?? '';
                    $r->column8 = $row[11] ?? '';
                    $r->column9 = $row[12] ?? '';
                    $r->column10 = $row[13] ?? '';
                    $recipients[] = $r;
                }
                fclose($file);
            }
            $data['recipients'] = $recipients;
        }

        $pdf = PDF::loadView('pdf.letter_download_pdf', $data)->setPaper('A4', 'portrait');
        return $pdf->stream('letter.pdf');

    }

    public function sendWhatsapp(Letter $letter, $id)
    {
        $letter = $letter->find($id);
        if($letter->people_type == 'customer') {
            $customer = Customer::class;
        } elseif ($letter->people_type == 'all') {
            $customer = Customer::class;
        } else {
            $customer = Employee::class;
        }

        ProcessQueue::dispatch($letter, $id, $customer);

        $letter->find($id)->update(['is_sent'=>true, 'sent_by'=>Auth::user()->id, 'otp' => null]);
        return redirect()->back()->with('message', 'Letter will send soon');
    }

    public function sendEmail(Letter $letter, $id)
    {
        $letter = $letter->find($id);

        LetterRecipients::eachRecipient($letter->people_type, $letter->to, function ($recipient) use ($letter) {
            $this->sendMail($letter, $recipient, $recipient->email ?: $recipient->id);
        });

        $letter->find($id)->update(['is_sent'=>true, 'sent_by'=>Auth::user()->id, 'otp' => null]);
        return redirect()->back()->with('message', 'Letter Sent successfully');
    }

    public function sendMail($letter, $lims_customer_data, $to) {
        $cc_emails = [];
        $attachments = [];
        if($letter->people_type == 'customer') {
            $customer = Customer::class;
        } elseif ($letter->people_type == 'all') {
            $customer = Customer::class;
        } else {
            $customer = Employee::class;
        }
        if ($letter->cc != null) {
            foreach (explode(",", $letter->cc) as $cc) {
                $lims_customer_data_cc = $customer::find($cc);
                if($lims_customer_data_cc->email) {
                    $cc_emails []= $lims_customer_data_cc->email;
                }
            }
        }
        if($letter->attachment) {
            $attachment_path = public_path('letter/attachment/');
            $attachments[] = $attachment_path.$letter->attachment;
        }
        if(isset($letter->attachmentlib[0])) {
            foreach ($letter->attachmentlib as $key => $attachment) {
                if ($key == 0) {
                    continue;
                }
                $attachments[] = $attachment_path.$attachment->attachment;
            }
        }
        if ($lims_customer_data == null) {
            return true;
        }
        $data = [
            'to' => $to,
            'data' => $letter,
            'mail' => $lims_customer_data->email,
            'subject' => $letter->subject,
            'cc_emails' => $cc_emails,
            'attachments' => $attachments
        ];

        $message = 'Letter notification sent successfully';
        try{
            Mail::send( 'mail.letter_details', $data, function( $message ) use ($data)
            {
                $message->to($data['mail'])->subject($data['subject'])->cc($data['cc_emails']);

                foreach ($data['attachments'] as $attachment) {
                    $message->attach($attachment);
                }
            });
        }
        catch(\Exception $e){
            $message = 'Letter is not sent. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }
        return $message;
    }

    private function replacePlaceholders($text, $recipient)
    {
        if ($text === null) { return ''; }
        $replacements = [
            '[name]' => $recipient->name ?? '',
            '[phone_number]' => $recipient->phone_number ?? '',
            '[email]' => $recipient->email ?? '',
            '[address]' => $recipient->address ?? '',
            // Support both [Column1] and [column1]
            '[Column1]' => $recipient->column1 ?? '',
            '[Column2]' => $recipient->column2 ?? '',
            '[Column3]' => $recipient->column3 ?? '',
            '[Column4]' => $recipient->column4 ?? '',
            '[Column5]' => $recipient->column5 ?? '',
            '[Column6]' => $recipient->column6 ?? '',
            '[Column7]' => $recipient->column7 ?? '',
            '[Column8]' => $recipient->column8 ?? '',
            '[Column9]' => $recipient->column9 ?? '',
            '[Column10]' => $recipient->column10 ?? '',
            '[column1]' => $recipient->column1 ?? '',
            '[column2]' => $recipient->column2 ?? '',
            '[column3]' => $recipient->column3 ?? '',
            '[column4]' => $recipient->column4 ?? '',
            '[column5]' => $recipient->column5 ?? '',
            '[column6]' => $recipient->column6 ?? '',
            '[column7]' => $recipient->column7 ?? '',
            '[column8]' => $recipient->column8 ?? '',
            '[column9]' => $recipient->column9 ?? '',
            '[column10]' => $recipient->column10 ?? '',
        ];

        return strtr($text, $replacements);
    }

    public function sendPDF($letter, $lims_customer_data, $to) {
        // Clone and render placeholders for PDF content
        $rendered = clone $letter;
        $rendered->header = $this->replacePlaceholders($letter->header, $lims_customer_data);
        $rendered->body = $this->replacePlaceholders($letter->body, $lims_customer_data);
        $rendered->footer = $this->replacePlaceholders($letter->footer, $lims_customer_data);

        $data = [
            'to' => $to,
            'data' => $rendered
        ];
//         return view('pdf.letter_pdf', $data);
        $pdf = PDF::loadView('pdf.letter_pdf', $data)->setPaper('A4', 'portrait');

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/letter/letter.pdf',$content);
        $path = storage_path('app/public/letter/letter.pdf');
        $attachment_path = public_path('letter/attachment/');
        $message = 'Letter notification sent successfully';
        try{
            $this->wpPDFMessage($path, $lims_customer_data, 'letter.pdf');
        }
        catch(\Exception $e){
            $message = 'Letter not sent. Please setup your whatsapp setting.';
        }


        if($letter->attachment) {
            $attachment_name = 'attachment-'.$letter->attachment;
            try{
                $this->wpPDFMessage($attachment_path . $letter->attachment, $lims_customer_data, $attachment_name);
            }
            catch(\Exception $e){
                $message = 'Letter not sent. Please setup your whatsapp setting.';
            }
        }
        if(isset($letter->attachmentlib[0])) {
            foreach ($letter->attachmentlib as $key => $attachment) {
                if($key == 0) {
                    continue;
                }
                $attachment_name = 'attachment-'.$attachment->attachment;
                try{
                    $this->wpPDFMessage($attachment_path . $attachment->attachment, $lims_customer_data, $attachment_name);
                }
                catch(\Exception $e){
                    $message = 'Letter not sent. Please setup your whatsapp setting.';
                }
            }

        }
        return $message;
    }

    public function imageUpload(Request $request)
    {
        $image = $request->file('image')->store('public/images/letters');
        $url = Storage::url($image);

        return response()->json(['location' => $url]);
    }

    public function sendSMS($letter, $lims_customer_data)
    {
        $message = 'Letter notification sent successfully';
        $account_sid = env('ACCOUNT_SID');
        $auth_token = env('AUTH_TOKEN');
        $twilio_phone_number = env('TWILIO_NUMBER');

        $data['message'] = $letter->subject . "<br><br>";
        $data['message'] .= $letter->header . "<br><br>";
        $data['message'] .= $letter->body . "<br><br>";
        $data['message'] .= $letter->footer . "<br><br><br>";
        $data['message'] .= request()->getSchemeAndHttpHost;
        try{
            $client = new Client($account_sid, $auth_token);
            $client->messages->create(
                $lims_customer_data->phone_number,
                array(
                    "from" => $twilio_phone_number,
                    "body" => $data['message']
                )
            );
        }
        catch(\Exception $e){
            $message = 'Letter is not sent. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }

        return $message;
    }

    public function sendPDFToCC($letter, $lims_customer_data, $to) {
        $data = [
            'to' => $to,
            'data' => $letter
        ];
        // return view('pdf.letter_pdf', $data);
        $pdf = PDF::loadView('pdf.cc_letter_pdf', $data)->setPaper('A4', 'portrait');

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/letter/letter.pdf',$content);
        $path = storage_path('app/public/letter/letter.pdf');
        $attachment_path = public_path('letter/attachment/');
        $message = 'Letter notification sent successfully';
        try{
            $this->wpPDFMessage($path, $lims_customer_data, 'letter.pdf');
        }
        catch(\Exception $e){
            $message = 'Letter not sent. Please setup your whatsapp setting.';
        }


        if($letter->attachment) {
            $attachment_name = 'attachment-'.$letter->attachment;
            try{
                $this->wpPDFMessage($attachment_path . $letter->attachment, $lims_customer_data, $attachment_name);
            }
            catch(\Exception $e){
                $message = 'Letter not sent. Please setup your whatsapp setting.';
            }
        }
        if(isset($letter->attachmentlib[0])) {
            foreach ($letter->attachmentlib as $key => $attachment) {
                if($key == 0) {
                    continue;
                }
                $attachment_name = 'attachment-'.$attachment->attachment;
                try{
                    $this->wpPDFMessage($attachment_path . $attachment->attachment, $lims_customer_data, $attachment_name);
                }
                catch(\Exception $e){
                    $message = 'Letter not sent. Please setup your whatsapp setting.';
                }
            }

        }
        return $message;
    }

    public function sign(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);
        return view('letter.sign', compact('data'));
    }

    public function signStore(Request $request, Letter $letter, $id)
    {
        $signature = $this->saveSignatureFromRequest($request, 'sign');
        if (!$signature) {
            return back()->with('not_permitted', 'Please provide your signature.');
        }

        $letter = $letter->find($id);
        $letter->update([
            'is_sign' => true,
            'signed_by' => Auth::user()->id,
            'sign_signature' => $signature,
            'sign_signed_at' => now(),
            'otp' => null,
        ]);

        return redirect()->route('letter.index.signed')->with('message', 'Letter Signed successfully. It is now ready to send.');
    }


    public function signSend(Letter $letter, $id)
    {
        $data = $letter->findorfail($id);
        $this->sendOTP($data);
        return view('letter.signSend', compact('data'));
    }
    public function signSendStore(Request $request, Letter $letter, $id)
    {
        $letter = $letter->find($id);

        if ($this->checkOtp($request, $letter) == true) {
            $letter->find($id)->update(['is_sign'=>true, 'signed_by'=>Auth::user()->id, 'otp' => null]);

            if($letter->people_type == 'customer' || $letter->people_type == 'all') {
                $customer = Customer::class;
            } else {
                $customer = Employee::class;
            }
//            foreach (explode(",", $letter->to) as $to) {
//                $lims_customer_data = $customer::find($to);
//                $message = $this->sendPDF($letter, $lims_customer_data, $to);
//                $message = $this->sendMail($letter, $lims_customer_data, $to);
//            }
//            if ($letter->cc != null) {
//                foreach (explode(",", $letter->cc) as $cc) {
//                    $lims_customer_data = $customer::find($cc);
//                    $this->sendPDFToCC($letter, $lims_customer_data, $letter->to);
//                }
//            }
            ProcessQueue::dispatch($letter, $id, $customer);

            $letter->find($id)->update(['is_sent'=>true, 'sent_by'=>Auth::user()->id, 'otp' => null]);
            return redirect()->back()->with('message', 'Letter Signed & Sent Successfully');
        }

        $letter->find($id)->update(['otp' => null]);
        return back()->with('not_permitted', 'OTP is wrong or Expired');
    }


    /**
     * Remove the specified resource from storage.
     *
     * @param  \App\Letter  $letter
     * @return \Illuminate\Http\Response
     */
    public function destroy($id)
    {
        $data = Letter::find($id);
        $data->is_active = false;
        $data->save();
        return back()->with('not_permitted','Data deleted successfully');
    }

    public function templateInfo ($id) {
        return LetterTemplate::find($id);
    }


    private function sendMultiOTP($id_array) {
        if (Auth::user()->otp_verify == 1) {
            return true;
        }
        $data = Letter::find($id_array[0]);

        if ($data->otp_time == null || $data->otp_time < date('Y-m-d H:i:s', strtotime('-30 seconds'))) {
            $otp = rand(1, 999999);
            $msg = "Your OTP is: " . $otp . "\n That will be expired after 2 minutes";
            foreach ($id_array as $id) {
                $letter = Letter::find($id);
                $letter->update(['otp'=>$otp, 'otp_time'=>date('Y-m-d H:i:s')]);
            }
            try {
                $this->wpMessage(Auth::user()->phone, $msg);
            } catch (\Exception $e) {
                return $otp;
            }
            return $otp;
        }
    }


    public function multipleApprove(Letter $letter, Request $request)
    {
        $id_array = [];
        $ids = $request->ids;
        if($ids == null) {
            return redirect()->back()->with('not_permitted', 'No letter is selected');
        }

        foreach ($ids as $key => $option) {
            $id_array[] = $key;
        }

        return view('letter.multiApprove', compact('id_array'));
    }


    public function multipleApproveStore(Request $request, Letter $letter)
    {
        $signature = $this->saveSignatureFromRequest($request, 'approve');
        if (!$signature) {
            return redirect()->route('letter.index.edited')->with('not_permitted', 'Please provide your approver signature.');
        }

        foreach ($request->ids as $id) {
            Letter::find($id)->update([
                'is_approve' => true,
                'approved_by' => Auth::user()->id,
                'approve_signature' => $signature,
                'approve_signed_at' => now(),
                'otp' => null,
            ]);
        }
        $letter = $letter->find($request->ids[0]);
        $this->sendMsgToConcernPerson($letter);

        return redirect()->route('letter.index.approved')->with('message', 'Letter Approved successfully. They are now awaiting signature.');
    }


    public function multipleOk(Letter $letter, Request $request)
    {
        $id_array = [];
        $ids = $request->ids;
        if($ids == null) {
            return redirect()->back()->with('not_permitted', 'No letter is selected');
        }

        foreach ($ids as $key => $option) {
            $id_array[] = $key;
        }

        return view('letter.multiOk', compact('id_array'));
    }


    public function multipleOkStore(Request $request, Letter $letter)
    {
        $signature = $this->saveSignatureFromRequest($request, 'edit');
        if (!$signature) {
            return redirect()->route('letter.index')->with('not_permitted', 'Please provide your editor signature.');
        }

        foreach ($request->ids as $id) {
            Letter::find($id)->update([
                'is_edit' => true,
                'edit_by' => Auth::user()->id,
                'edit_signature' => $signature,
                'edit_signed_at' => now(),
                'otp' => null,
            ]);
        }
        $letter = $letter->find($request->ids[0]);
        $this->sendMsgToConcernPerson($letter);

        return redirect()->route('letter.index.edited')->with('message', 'Letter Ok successfully. They are now awaiting approval.');
    }

    public function multipleSign(Letter $letter, Request $request)
    {
        $id_array = [];
        $ids = $request->ids;
        if($ids == null) {
            return redirect()->back()->with('not_permitted', 'No letter is selected');
        }

        foreach ($ids as $key => $option) {
            $id_array[] = $key;
        }

        return view('letter.multiSign', compact('id_array'));
    }


    public function multipleSignStore(Request $request, Letter $letter)
    {
        $signature = $this->saveSignatureFromRequest($request, 'sign');
        if (!$signature) {
            return redirect()->route('letter.index.approved')->with('not_permitted', 'Please provide your signature.');
        }

        foreach ($request->ids as $id) {
            Letter::find($id)->update([
                'is_sign' => true,
                'signed_by' => Auth::user()->id,
                'sign_signature' => $signature,
                'sign_signed_at' => now(),
                'otp' => null,
            ]);
        }

        return redirect()->route('letter.index.signed')->with('message', 'Letter Signed successfully. They are now ready to send.');
    }

    public function multipleSend(Letter $letter, Request $request)
    {
        $id_array = [];
        $ids = $request->ids;
        if($ids == null) {
            return redirect()->back()->with('not_permitted', 'No letter is selected');
        }
        foreach ($ids as $key => $option) {
            $id_array[] = $key;
        }

        $this->sendMultiOTP($id_array);
        return view('letter.multiSend', compact('id_array'));
    }


    public function multipleSendStore(Request $request, Letter $letter)
    {
        $letter = $letter->find($request->ids[0]);
        if ($this->checkOtp($request, $letter) == true) {
            foreach ($request->ids as $id) {
                $letter = Letter::find($id);
                if ($letter->people_type == 'customer' || $letter->people_type == 'all') {
                    $customer = Customer::class;
                } else {
                    $customer = Employee::class;
                }
//                foreach (explode(",", $letter->to) as $to) {
//                    $lims_customer_data = $customer::find($to);
//                    $message = $this->sendPDF($letter, $lims_customer_data, $to);
//                    $message = $this->sendMail($letter, $lims_customer_data, $to);
//                }
//                if ($letter->cc != null) {
//                    foreach (explode(",", $letter->cc) as $cc) {
//                        $lims_customer_data = $customer::find($cc);
//                        $this->sendPDFToCC($letter, $lims_customer_data, $letter->to);
//                    }
//                }
                ProcessQueue::dispatch($letter, $id, $customer);
                $letter->find($id)->update(['is_sent' => true, 'sent_by' => Auth::user()->id, 'otp' => null]);

            }
            return redirect()->route('letter.index.sent')->with('message', 'Letters will sent soon');
        }

        $letter->update(['otp' => null]);
        return redirect()->route('letter.index.sent')->with('not_permitted', 'OTP is wrong or Expired');
    }

    protected function saveSignatureFromRequest(Request $request, string $prefix)
    {
        $request->validate([
            'signature_image' => 'required|string',
        ]);

        $filename = LetterSignature::storeFromDataUrl($request->signature_image, $prefix);

        if ($filename) {
            $this->replaceAccountSignature($prefix, $filename);
        }

        return $filename;
    }

    private function replaceAccountSignature(string $prefix, string $filename)
    {
        $columnMap = [
            'edit' => 'stemp',
            'approve' => 'approve',
            'sign' => 'sign',
        ];

        if (!isset($columnMap[$prefix])) {
            return;
        }

        $source = public_path('letter/signatures/' . $filename);
        if (!is_file($source)) {
            return;
        }

        $destinationDir = public_path('images/user');
        if (!is_dir($destinationDir)) {
            mkdir($destinationDir, 0755, true);
        }

        $accountFile = 'acct_' . $prefix . '_' . Auth::id() . '_' . date('YmdHis') . '.png';
        if (!@copy($source, $destinationDir . '/' . $accountFile)) {
            return;
        }

        User::where('id', Auth::id())->update([$columnMap[$prefix] => $accountFile]);
    }


    public function multipleDownloadStore(Request $request, Letter $letter)
    {
        if($request->ids == null) {
            return redirect()->back()->with('not_permitted', 'No letter is selected');
        }

        if (isset($request->ids[0]) == false ) {
            foreach ($request->ids as $key => $id) {
                $ids[] = $key;
            }
        } else {
            $ids = $request->ids;
        }


//        if ($this->checkOtp($request, $letter) == true) {
        $data = [
            'ids' => $ids
        ];
        $pdf = PDF::loadView('pdf.multiple_letter_download_pdf', $data)->setPaper('A4', 'portrait');
        return $pdf->download('letter.pdf');
//        }
//
//        $letter->update(['otp' => null]);
//        return redirect()->route('letter.index.signed')->with('not_permitted', 'OTP is wrong or Expired');
    }

    public function multiplePrintStore(Request $request, Letter $letter)
    {
        if($request->ids == null) {
            return redirect()->back()->with('not_permitted', 'No letter is selected');
        }

        if (isset($request->ids[0]) == false ) {
            foreach ($request->ids as $key => $id) {
                $ids[] = $key;
            }
        } else {
            $ids = $request->ids;
        }

//        $letter = $letter->find($ids[0]);
//        if ($this->checkOtp($request, $letter) == true) {
        $data = [
            'ids' => $ids
        ];
        $pdf = PDF::loadView('pdf.multiple_letter_download_pdf', $data)->setPaper('A4', 'portrait');
        return $pdf->stream('letter.pdf');
//        }

//        $letter->update(['otp' => null]);
//        return redirect()->route('letter.index.signed')->with('not_permitted', 'OTP is wrong or Expired');
    }

    protected function resolveCloneRecipients(Letter $clone): array
    {
        $peopleType = trim((string) ($clone->people_type ?? ''));
        $toIds = [];
        $ccIds = [];

        if (in_array($peopleType, ['customer', 'user', 'all', 'csv'], true)) {
            if (in_array($peopleType, ['customer', 'user'], true)) {
                $toIds = array_values(array_filter(explode(',', (string) $clone->to)));
                $ccIds = $clone->cc
                    ? array_values(array_filter(explode(',', (string) $clone->cc)))
                    : [];
            }

            return [
                'peopleType' => $peopleType,
                'toIds' => $toIds,
                'ccIds' => $ccIds,
            ];
        }

        $to = (string) ($clone->to ?? '');

        if (strpos($to, 'c:') !== false || strpos($to, 'e:') !== false) {
            $peopleType = 'all';
        } elseif (preg_match('/\.csv$/i', $to)) {
            $peopleType = 'csv';
        } elseif ($to !== '') {
            $customerIds = array_values(array_filter(explode(',', $to)));
            $hasCustomer = Customer::whereIn('id', $customerIds)->exists();
            $peopleType = $hasCustomer ? 'customer' : 'user';
            $toIds = $customerIds;
            $ccIds = $clone->cc
                ? array_values(array_filter(explode(',', (string) $clone->cc)))
                : [];
        }

        return [
            'peopleType' => $peopleType,
            'toIds' => $toIds,
            'ccIds' => $ccIds,
        ];
    }

}
