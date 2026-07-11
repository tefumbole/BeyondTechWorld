<?php

namespace App\Http\Controllers;

use App\Announcement;
use App\AnnouncementAttachment;
use App\Customer;
use App\Employee;
use App\GeneralSetting;
use App\Jobs\SendAnnouncementJob;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use PDF;
use Illuminate\Support\Facades\Mail;

class AnnouncementController extends Controller
{
    public function index()
    {
        $data = Announcement::where('is_active', true)->orderBy('id', 'desc')->get();
        return view('announcement.index', compact('data'));
    }
    public function create()
    {
        $user = Employee::where('is_active', true)->get();
        $customer = Customer::where('is_active', true)->get();
        return view('announcement.create', compact('user', 'customer'));
    }

    public function store(Request $request)
    {
        $data = $request->all();

        if (isset($data['date_time']) && $data['date_time']) {
            $normalized = str_replace('T', ' ', $data['date_time']);
            if (!preg_match('/:\\d{2}$/', $normalized)) {
                $normalized .= ':00';
            }
            $data['date_time'] = $normalized;
        }


        $image = $request->attachment;
        if (isset($image)) {
            $imageName = date("Ymdhis").$image->getClientOriginalName();
            $image->move('public/announcement/attachment', $imageName);
            $data['attachment'] = $imageName;
        }

        if($data['people_type'] == "customer") {
            $data['to_customer'] = array_unique($data['to_customer']);
            $data['cc_customer'] = isset($data['cc_customer']) ? array_unique($data['cc_customer']) : [];
            $data['to'] = implode(",", $data['to_customer']);
            $data['cc'] = isset($data['cc_customer']) ? implode(",", $data['cc_customer']) : null;
        } else if($data['people_type'] == "user") {
            $data['to'] = array_unique($data['to']);
            $data['cc'] = isset($data['cc']) ? array_unique($data['cc']) : [];
            $data['to'] = implode(",", $data['to']);
            $data['cc'] = isset($data['cc']) ? implode(",", $data['cc']) : null;
        } else if($data['people_type'] == "csv") {
            $to_csv = $request->to_csv;
            if (isset($to_csv)) {
                $imageName = date("Ymdhis").$to_csv->getClientOriginalName();
                $to_csv->move('public/announcement/csv', $imageName);
                $data['to'] = $imageName;
            }
        }


        $image = $request->attachments;
        if (isset($image[0])) {
            $imageName = date("Ymdhis").$image[0]->getClientOriginalName();
            $image[0]->move('public/announcement/attachment', $imageName);
            $data['attachment'] = $imageName;
        }

        $data['created_by'] = Auth::user()->id;


        unset($data['customer_type']);
        unset($data['to_customer_group']);
        unset($data['is_template']);
        unset($data['to_customer']);
        unset($data['cc_customer']);
        unset($data['to_csv']);
        if(isset($data['attachments'])) {
            $data_multiple['attachments'] = $data['attachments'];
        }
        unset($data['attachments']);

        $announcement = Announcement::create($data);

        $attachments = isset($data_multiple['attachments']) ? $data_multiple['attachments'] : [];
        if ($attachments) {
            foreach ($attachments as $key => $attachment) {
                if($key == 0) {
                    AnnouncementAttachment::create(['announcement_id' => $announcement->id, 'attachment' => $imageName]);
                } else {
                    $attachmentName = date("Ymdhis").$attachments[$key]->getClientOriginalName();
                    $attachments[$key]->move('public/announcement/attachment', $attachmentName);
                    AnnouncementAttachment::create(['announcement_id' => $announcement->id, 'attachment' => $attachmentName]);
                }
            }
        }

        return redirect()->route('announcement.create')->with('message', 'Announcement created successfully');
    }

    public function edit($id)
    {
        $data = Announcement::findorfail($id);
        if($data->people_type == "customer") {
            $user = Customer::where('is_active', true)->get();
        } else {
            $user = Employee::where('is_active', true)->get();
        }
        return view('announcement.edit', compact('user', 'data'));
    }

    public function announcementAttachmentDelete($id)
    {
        $attachment = AnnouncementAttachment::where('id', $id)->first();
        @unlink('public/announcement/attachment/'.$attachment->attachment);
        $attachment->delete();
        return back();
    }

    public function letterAttachmentDeleteFirst($id)
    {
        $letter = Announcement::where('id', $id)->first();
        @unlink('public/announcement/attachment/'.$letter->attachment);
        $letter->update(['attachment' => null]);
        return back()->with('not_permitted', 'Announcement attachment deleted successfully');
    }

    public function update(Request $request, Announcement $announcement, $id)
    {
        $data = $request->all();
        $announcement = $announcement->find($id);

        if (isset($data['date_time']) && $data['date_time']) {
            $normalized = str_replace('T', ' ', $data['date_time']);
            if (!preg_match('/:\\d{2}$/', $normalized)) {
                $normalized .= ':00';
            }
            $data['date_time'] = $normalized;
        }

        $attachments = $request->attachments;
        if ($attachments) {
            foreach ($attachments as $key => $attachment) {
                $attachmentName = date("Ymdhis").$attachments[$key]->getClientOriginalName();
                $attachments[$key]->move('public/announcement/attachment', $attachmentName);
                AnnouncementAttachment::create(['announcement_id' => $id, 'attachment' => $attachmentName]);
            }
        }

        if($announcement->people_type == "csv") {
            $to_csv = $request->to_csv;
            if (isset($to_csv)) {
                $imageName = date("Ymdhis").$to_csv->getClientOriginalName();
                $to_csv->move('public/announcement/csv', $imageName);
                $data['to'] = $imageName;
            }
        } else {
            $data['to'] = implode(",", $data['to']);
            $data['cc'] = isset($data['cc']) ? implode(",", $data['cc']) : null;
        }
        unset($data['attachments']);
        unset($data['to_csv']);
        $announcement->update($data);
        return redirect()->route('announcement.index')->with('message', 'Letter updated successfully');
    }

    public function imageUpload(Request $request)
    {
        $image = $request->file('image')->store('public/images/announcement');
        $url = Storage::url($image);

        return response()->json(['location' => $url]);
    }

    public function show(Announcement $announcement, $id)
    {
        $data = $announcement->with( 'createdBy', 'attachmentLib')->where('id', $id)->first();
        return view('announcement.show', compact('data'));
    }

    public function destroy($id)
    {
        $data = Announcement::find($id);
        $data->is_active = false;
        $data->save();
        return back()->with('not_permitted','Data deleted successfully');
    }

    public function send(Announcement $announcement, $id)
    {
        $announcement = $announcement->find($id);

        if ($announcement->people_type == 'csv') {

            $csv_path = public_path('announcement/csv/');
//            $csv_path = public_path('public/announcement/csv/');
            $csvFilePath = $csv_path.$announcement->to;
            $file = fopen($csvFilePath, 'r');

            if ($file !== false) {
                $firstRow = true;
                while (($data = fgetcsv($file)) !== false) {
                    if ($firstRow) {
                        $firstRow = false; // Set the flag to false after skipping the first row
                        continue; // Skip processing the first row
                    }

                    $recipient = (object)[
                        'name'         => $data[0] ?? '',
                        'phone_number' => $data[1] ?? '',
                        'email'        => $data[2] ?? '',
                        'address'      => $data[3] ?? '',
                        'column1'      => $data[4] ?? '',
                        'column2'      => $data[5] ?? '',
                        'column3'      => $data[6] ?? '',
                        'column4'      => $data[7] ?? '',
                        'column5'      => $data[8] ?? '',
                        'column6'      => $data[9] ?? '',
                        'column7'      => $data[10] ?? '',
                        'column8'      => $data[11] ?? '',
                        'column9'      => $data[12] ?? '',
                        'column10'     => $data[13] ?? '',
                    ];

                    // 🚀 Queue each announcement
                    SendAnnouncementJob::dispatch($announcement, $recipient);
                }
            }
        } else {
            $customerClass = $announcement->people_type == 'customer' ? Customer::class : Employee::class;

            foreach (explode(",", $announcement->to) as $to) {
                $recipient = $customerClass::find($to);
                SendAnnouncementJob::dispatch($announcement, $recipient);
            }

            if ($announcement->cc != null) {
                foreach (explode(",", $announcement->cc) as $cc) {
                    $recipient = $customerClass::find($cc);
                    SendAnnouncementJob::dispatch($announcement, $recipient);
                }
            }
        }
        return redirect()->back()->with('message', 'Announcement queued for sending');
    }


    public function download(Announcement $announcement, $id)
    {
        $announcement = $announcement->find($id);
        $customer = $announcement->people_type == 'customer' ? Customer::class : Employee::class;

        $data = [
            'data' => $announcement,
            'user' => $customer,
            'people_type' => $announcement->people_type
        ];

        $pdf = PDF::loadView('pdf.announcement_download_pdf', $data)->setPaper('A4', 'portrait');
        return $pdf->download('announcement.pdf');

    }

    public function print(Announcement $announcement, $id)
    {
        $announcement = $announcement->find($id);
        $customer = $announcement->people_type == 'customer' ? Customer::class : Employee::class;

        $data = [
            'data' => $announcement,
            'user' => $customer,
            'people_type' => $announcement->people_type
        ];

        $pdf = PDF::loadView('pdf.announcement_download_pdf', $data)->setPaper('A4', 'portrait');
        return $pdf->stream('announcement.pdf');

    }

    public function sendWhatsapp(Announcement $announcement, $id)
    {
        $announcement = $announcement->find($id);
        $customer = $announcement->people_type == 'customer' ? Customer::class : Employee::class;


        ProcessQueue::dispatch($announcement, $id, $customer);

        $announcement->find($id)->update(['is_sent'=>true, 'sent_by'=>Auth::user()->id, 'otp' => null]);
        return redirect()->back()->with('message', 'Announcement will send soon');
    }

    public function sendEmail(Announcement $announcement, $id)
    {
        $announcement = $announcement->find($id);
        $customer = $announcement->people_type == 'customer' ? Customer::class : Employee::class;

        foreach (explode(",", $announcement->to) as $to) {
            $lims_customer_data = $customer::find($to);
            $message = $this->sendMail($announcement, $lims_customer_data, $to);

        }

        $announcement->find($id)->update(['is_sent'=>true, 'sent_by'=>Auth::user()->id, 'otp' => null]);
        return redirect()->back()->with('message', 'Announcement Sent successfully');
    }

    public function sendMail($announcement, $lims_customer_data, $to) {
        $cc_emails = [];
        $attachments = [];
        $customer = $announcement->people_type == 'customer' ? Customer::class : Employee::class;

        if ($announcement->cc != null) {
            foreach (explode(",", $announcement->cc) as $cc) {
                $lims_customer_data_cc = $customer::find($cc);
                if($lims_customer_data_cc->email) {
                    $cc_emails []= $lims_customer_data_cc->email;
                }
            }
        }
        if($announcement->attachment) {
            $attachment_path = public_path('announcement/attachment/');
            $attachments[] = $attachment_path.$announcement->attachment;
        }
        if(isset($announcement->attachmentlib[0])) {
            foreach ($announcement->attachmentlib as $key => $attachment) {
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
            'data' => $announcement,
            'mail' => $lims_customer_data->email,
            'subject' => $announcement->subject,
            'cc_emails' => $cc_emails,
            'attachments' => $attachments
        ];

        $message = 'Announcement notification sent successfully';
        try{
            Mail::send( 'mail.announcement_details', $data, function( $message ) use ($data)
            {
                $message->to($data['mail'])->subject($data['subject'])->cc($data['cc_emails']);

                foreach ($data['attachments'] as $attachment) {
                    $message->attach($attachment);
                }
            });
        }
        catch(\Exception $e){
            $message = 'Announcement is not sent. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }
        return $message;
    }

    public function sendAnnouncementMsg($announcement, $lims_customer_data)
    {
        $msg = strip_tags(html_entity_decode($announcement->header)) . "\r\n\n";
        $msg .= "Ref: " . $announcement->id . "\r\n";
        $msg .= "Date: " . $announcement->created_at . "\r\n\n";
        $msg .= "Subject: " . $announcement->subject . "\r\n\n";
        $msg .= "Dear: " . $lims_customer_data->name . "\r\n\n";
        $bodyHtml = html_entity_decode($announcement->body);
        $bodyHtml = $this->replacePlaceholders($bodyHtml, $lims_customer_data);
        $msg .= strip_tags($bodyHtml) . "\r\n\n";
        $msg .= strip_tags(html_entity_decode($announcement->footer)) . "\r\n";

        try{
            $this->wpMessage($lims_customer_data->phone_number, $msg);
        }
        catch(\Exception $e){
        }

//        $attachment_path = public_path('public/announcement/attachment/'); // for local
        $attachment_path = public_path('announcement/attachment/');


        if(isset($announcement->attachmentlib[0])) {
            foreach ($announcement->attachmentlib as $key => $attachment) {
                $attachment_name = 'attachment-'.$attachment->attachment;
//                dd($attachment_path . $attachment->attachment);
                try{
                    $this->wpPDFAnnouncement($attachment_path . $attachment->attachment, $lims_customer_data, $attachment_name);
                }
                catch(\Exception $e){
                    $message = 'Announcement not sent. Please setup your whatsapp setting.';
                }
            }

        }
        $announcement->update(['is_sent' => true]);
        return true;
    }

    private function replacePlaceholders($text, $recipient)
    {
        $replacements = [
            '[name]' => $recipient->name ?? '',
            '[phone_number]' => $recipient->phone_number ?? '',
            '[email]' => $recipient->email ?? '',
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

    public function sendPDF($announcement, $lims_customer_data, $to) {
        $data = [
            'to' => $to,
            'data' => $announcement
        ];
        // return view('pdf.announcement_pdf', $data);
        $pdf = PDF::loadView('pdf.announcement_pdf', $data)->setPaper('A4', 'portrait');

        $content = $pdf->download()->getOriginalContent();

        Storage::put('public/announcement/announcement.pdf',$content);
        $path = storage_path('app/public/announcement/announcement.pdf');
        $attachment_path = public_path('announcement/attachment/');
        $message = 'Announcement notification sent successfully';
        try{
            $this->wpPDFAnnouncement($path, $lims_customer_data, $lims_customer_data->name.'_announcement.pdf');
        }
        catch(\Exception $e){
            $message = 'Announcement not sent. Please setup your whatsapp setting.';
        }


        if($announcement->attachment) {
            $attachment_name = 'attachment-'.$announcement->attachment;
            try{
                $this->wpPDFAnnouncement($attachment_path . $announcement->attachment, $lims_customer_data, $attachment_name);
            }
            catch(\Exception $e){
                $message = 'Announcement not sent. Please setup your whatsapp setting.';
            }
        }
        if(isset($announcement->attachmentlib[0])) {
            foreach ($announcement->attachmentlib as $key => $attachment) {
                if($key == 0) {
                    continue;
                }
                $attachment_name = 'attachment-'.$attachment->attachment;
                try{
                    $this->wpPDFAnnouncement($attachment_path . $attachment->attachment, $lims_customer_data, $attachment_name);
                }
                catch(\Exception $e){
                    $message = 'Announcement not sent. Please setup your whatsapp setting.';
                }
            }

        }
        return $message;
    }

    public function sendSMS($announcement, $lims_customer_data)
    {
        $message = 'Announcement notification sent successfully';
        $account_sid = env('ACCOUNT_SID');
        $auth_token = env('AUTH_TOKEN');
        $twilio_phone_number = env('TWILIO_NUMBER');

        $data['message'] = $announcement->subject . "<br><br>";
        $data['message'] .= $announcement->header . "<br><br>";
        $data['message'] .= $announcement->body . "<br><br>";
        $data['message'] .= $announcement->footer . "<br><br><br>";
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
            $message = 'Announcement is not sent. Please setup your <a href="setting/mail_setting">mail setting</a> to send mail.';
        }

        return $message;
    }

    public function sendPDFToCC($announcement, $lims_customer_data, $to) {
        $data = [
            'to' => $to,
            'data' => $announcement
        ];
        // return view('pdf.announcement_pdf', $data);
        $pdf = PDF::loadView('pdf.cc_announcement_pdf', $data)->setPaper('A4', 'portrait');

        $content = $pdf->download()->getOriginalContent();


        Storage::put('public/announcement/announcement.pdf',$content);
        $path = storage_path('app/public/announcement/announcement.pdf');
        $attachment_path = public_path('announcement/attachment/');
        $message = 'Announcement notification sent successfully';
        try{
            $this->wpPDFAnnouncement($path, $lims_customer_data, $lims_customer_data->name.'-announcement.pdf');
        }
        catch(\Exception $e){
            $message = 'Announcement not sent. Please setup your whatsapp setting.';
        }


        if($announcement->attachment) {
            $attachment_name = 'attachment-'.$announcement->attachment;
            try{
                $this->wpPDFAnnouncement($attachment_path . $announcement->attachment, $lims_customer_data, $attachment_name);
            }
            catch(\Exception $e){
                $message = 'Announcement not sent. Please setup your whatsapp setting.';
            }
        }
        if(isset($announcement->attachmentlib[0])) {
            foreach ($announcement->attachmentlib as $key => $attachment) {
                if($key == 0) {
                    continue;
                }
                $attachment_name = 'attachment-'.$attachment->attachment;
                try{
                    $this->wpPDFAnnouncement($attachment_path . $attachment->attachment, $lims_customer_data, $attachment_name);
                }
                catch(\Exception $e){
                    $message = 'Announcement not sent. Please setup your whatsapp setting.';
                }
            }

        }
        return $message;
    }

    public function announcementAttachmentDeleteFirst($id)
    {
        $letter = Announcement::where('id', $id)->first();
        @unlink('public/announcement/attachment/'.$letter->attachment);
        $letter->update(['attachment' => null]);
        return back()->with('not_permitted', 'Announcement attachment deleted successfully');
    }
}
