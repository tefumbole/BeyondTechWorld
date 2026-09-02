<style>
    .align-items-center-logo {
        text-align: center;
        display: inline;
        margin-left: 26%;
    }
    .card{
        width: 60vw;
        margin-left: 15%;
    }
    .letter-preview-top-right {
        text-align: right;
        margin: 0 0 8px;
    }
    .letter-preview-corner-header {
        font-size: 12px;
        font-weight: 700;
        line-height: 1.2;
        margin-bottom: 4px;
    }
    .letter-preview-stamp {
        display: inline-block;
        margin-left: 4px;
        vertical-align: top;
        max-width: 48px;
    }
    .letter-preview-stamp img {
        /* Comment / Approver — very small on letter preview */
        height: 14px;
        max-height: 14px;
        max-width: 48px;
        width: auto;
        display: block;
        background: transparent;
    }
    .letter-preview-stamp-date {
        display: block;
        font-size: 7px;
        line-height: 1.1;
        color: #555;
        margin-top: 1px;
    }
    .letter-preview-sign {
        display: block;
        max-height: 56px;
        width: auto;
        margin: 0 0 2px;
        background: transparent;
    }
    .letter-preview-closing {
        line-height: 1.15;
        margin: 0;
        font-size: 14px;
    }
    .letter-preview-closing p,
    .letter-preview-closing div,
    .letter-preview-closing h1,
    .letter-preview-closing h2,
    .letter-preview-closing h3,
    .letter-preview-closing h4,
    .letter-preview-closing h5 {
        margin: 0;
        padding: 0;
        line-height: 1.15;
        font-size: 14px;
        font-weight: normal;
    }
    .letter-preview-codes {
        text-align: center;
        margin: 12px 0;
    }
    .letter-preview-codes img {
        display: block;
        margin: 0 auto 4px;
    }
</style>
@if($data->attachment)
        <a href="{{url('public/letter/attachment',$data->attachment)}}" target="_blank"><span class="fa fa-eye"></span> View Attachment</a>
        <a href="{{route('letter.attachment.delete.first', ['id' => $data->id])}}" class="text-danger" onclick="return confirmDelete()">X</a><br>
@endif

@if($data->attachmentLib)
    @foreach($data->attachmentLib as $key => $attachment)
        @if($key == 0)
            @continue
        @endif
        <a href="{{url('public/letter/attachment',$attachment->attachment)}}" target="_blank"><span class="fa fa-eye"></span> View Attachment</a>
        <a href="{{route('letter.attachment.delete', ['id' => $attachment->id])}}" class="text-danger" onclick="return confirmDelete()">X</a>
        <br>
    @endforeach
@endif
{{--@if($general_setting->invoice_format != 'beyond_a4')--}}
    <div class="align-items-center-logo">
        @if($general_setting->site_logo)
            <img src="{{url('public/logo/', $general_setting->site_logo)}}" height="150" width="150" style="margin:10px 0;">
        @endif
    </div>
{{--@endif--}}

@php
    use App\Support\LetterSignature;
    if ($data->people_type == "customer") {
        $user = \App\Customer::class;
    } elseif ($data->people_type == "all") {
        $user = \App\Customer::class;
    } else {
        $user = \App\Employee::class;
    }
    $dearNames = [];
    if ($data->people_type === 'directory') {
        foreach (\App\Support\LetterRecipients::decodePeopleJson($data->recipients_json) as $person) {
            if (! empty($person['name'])) {
                $dearNames[] = $person['name'];
            }
        }
    } elseif ($data->people_type == 'all') {
        if (preg_match('/c:([^|]*)/', $data->to, $customerMatch)) {
            foreach (array_filter(explode(',', $customerMatch[1])) as $to) {
                $person = \App\Customer::find($to);
                if ($person) {
                    $dearNames[] = $person->name;
                }
            }
        }
        if (preg_match('/e:([^|]*)/', $data->to, $employeeMatch)) {
            foreach (array_filter(explode(',', $employeeMatch[1])) as $to) {
                $person = \App\Employee::find($to);
                if ($person) {
                    $dearNames[] = $person->name;
                }
            }
        }
    } elseif ($data->people_type != 'csv') {
        foreach (explode(',', (string) $data->to) as $to) {
            $person = $user::find(trim($to));
            if ($person) {
                $dearNames[] = $person->name;
            }
        }
    }

    $editUser = $data->edit_by ? \App\User::find($data->edit_by) : null;
    $approveUser = $data->approved_by ? \App\User::find($data->approved_by) : null;
    $signUser = $data->signed_by ? \App\User::find($data->signed_by) : null;
    $editSrc = LetterSignature::resolveEditSrc($data, $editUser);
    $approveSrc = LetterSignature::resolveApproveSrc($data, $approveUser);
    $signSrc = LetterSignature::resolveSignSrc($data, $signUser);
    $fmtPreviewStamp = function ($d) {
        if (! $d) {
            return '';
        }
        try {
            return \Carbon\Carbon::parse($d)->format('M d, Y H:i');
        } catch (\Throwable $e) {
            return '';
        }
    };
@endphp

<div class="letter-preview-top-right">
    @if(trim(strip_tags((string) $data->header)) !== '')
        <div class="letter-preview-corner-header">{!! $data->header !!}</div>
    @endif
    @if($data->is_edit == 1 && $editSrc)
        <div class="letter-preview-stamp">
            <img src="{{ $editSrc }}" alt="Comment" height="14" style="height:14px;max-height:14px;max-width:48px;width:auto;">
            @if($fmtPreviewStamp($data->edit_signed_at ?? null) !== '')
                <span class="letter-preview-stamp-date">{{ $fmtPreviewStamp($data->edit_signed_at) }}</span>
            @endif
        </div>
    @endif
    @if($data->is_approve == 1 && $approveSrc)
        <div class="letter-preview-stamp">
            <img src="{{ $approveSrc }}" alt="Approve" height="14" style="height:14px;max-height:14px;max-width:48px;width:auto;">
            @if($fmtPreviewStamp($data->approve_signed_at ?? null) !== '')
                <span class="letter-preview-stamp-date">{{ $fmtPreviewStamp($data->approve_signed_at) }}</span>
            @endif
        </div>
    @endif
</div>

<div>Ref: {{ $data->reference }} <br>
    {{ date('M d, Y') }}
    @if(!empty($data->date_time))
        <br>
        Schedule: {{ \Carbon\Carbon::parse($data->date_time)->format('M d, Y h:i A') }}
    @endif
</div><br>
@if($data->people_type === 'directory')
    @foreach(\App\Support\LetterRecipients::decodePeopleJson($data->recipients_json) as $person)
        <div>
            <strong>{{ $person['name'] ?? '' }}</strong>
            @if(!empty($person['address']))<br>{{ $person['address'] }}@endif
            @if(!empty($person['phone']))<br>{{ $person['phone'] }}@endif
        </div>
        <br>
    @endforeach
@endif
<div>Dear{{ count($dearNames) ? ' '.implode(', ', $dearNames) : '' }},
    @if($data->people_type == 'csv')
        <a href="{{url('public/letter/csv',$data->to)}}" target="_blank"><span class="fa fa-eye"></span> CSV File</a><br>
    @endif
</div>
<br>
<div style="text-transform: uppercase">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
</div>
{!! $data->body !!}
<br>
<p style="margin-bottom:2px;">Sincerely,</p>
@if($data->is_sign == 1 && $signSrc)
    <img class="letter-preview-sign" src="{{ $signSrc }}" alt="Signer signature">
    @if($fmtPreviewStamp($data->sign_signed_at ?? null) !== '')
        <span class="letter-preview-stamp-date">{{ $fmtPreviewStamp($data->sign_signed_at) }}</span>
    @endif
@endif
<div class="letter-preview-closing">
@if($data->footer != null)
    {!! $data->footer !!}
@else
    {{ $data->name }}
@endif
@if($data->people_type === 'directory')
    @php $ccPeople = \App\Support\LetterRecipients::decodePeopleJson($data->cc_json); @endphp
    @if(count($ccPeople))
        <div>CC:
            @foreach($ccPeople as $ccPerson)
                {{ $ccPerson['name'] ?? '' }}{{ ! $loop->last ? ', ' : '' }}
            @endforeach
        </div>
    @endif
@elseif($data->cc)
    <div>CC:
        @php
            foreach (explode(",", $data->cc) as $cc) {
                echo $user::find($cc) ? $user::find($cc)->name .  ', ' : '';
            }
        @endphp
    </div>
@endif
</div>
@if($data->comment)
    <p class="small text-muted mt-2">Internal comment: {{ $data->comment }}</p>
@endif
