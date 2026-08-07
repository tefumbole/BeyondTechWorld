@php
    use App\Support\LetterSignature;
    use App\Support\LetterRecipients;

    $peopleType = $data->people_type;
    if ($peopleType == 'customer' || $peopleType == 'all') {
        $user_class = \App\Customer::class;
    } elseif ($peopleType == 'user') {
        $user_class = \App\Employee::class;
    } else {
        $user_class = \App\Customer::class;
    }

    if (! isset($user_to)) {
        $user_to = null;
    }

    // Classic customer/employee IDs
    if (! $user_to && ! empty($to) && in_array($peopleType, ['customer', 'user'], true)) {
        $user_to = $user_class::find($to);
    }

    // Directory letters: resolve from recipients_json / passed recipient object / prefixed id
    if (! $user_to && $peopleType === 'directory') {
        if (is_object($to) && (isset($to->name) || isset($to->email))) {
            $user_to = $to;
        } else {
            $matchId = is_string($to) ? $to : null;
            foreach (LetterRecipients::decodePeopleJson($data->recipients_json) as $person) {
                if ($matchId && (($person['id'] ?? null) === $matchId
                    || ($person['email'] ?? null) === $matchId
                    || ($person['phone'] ?? null) === $matchId)) {
                    $user_to = LetterRecipients::toSendObject($person);
                    break;
                }
            }
            if (! $user_to) {
                $people = LetterRecipients::decodePeopleJson($data->recipients_json);
                if (count($people) === 1) {
                    $user_to = LetterRecipients::toSendObject($people[0]);
                }
            }
        }
    }

    $replacements = [
        '[name]' => optional($user_to)->name ?? '',
        '[phone_number]' => optional($user_to)->phone_number ?? '',
        '[email]' => optional($user_to)->email ?? '',
        '[address]' => optional($user_to)->address ?? '',
        '[Column1]' => optional($user_to)->column1 ?? '',
        '[Column2]' => optional($user_to)->column2 ?? '',
        '[Column3]' => optional($user_to)->column3 ?? '',
        '[Column4]' => optional($user_to)->column4 ?? '',
        '[Column5]' => optional($user_to)->column5 ?? '',
        '[Column6]' => optional($user_to)->column6 ?? '',
        '[Column7]' => optional($user_to)->column7 ?? '',
        '[Column8]' => optional($user_to)->column8 ?? '',
        '[Column9]' => optional($user_to)->column9 ?? '',
        '[Column10]' => optional($user_to)->column10 ?? '',
        '[column1]' => optional($user_to)->column1 ?? '',
        '[column2]' => optional($user_to)->column2 ?? '',
        '[column3]' => optional($user_to)->column3 ?? '',
        '[column4]' => optional($user_to)->column4 ?? '',
        '[column5]' => optional($user_to)->column5 ?? '',
        '[column6]' => optional($user_to)->column6 ?? '',
        '[column7]' => optional($user_to)->column7 ?? '',
        '[column8]' => optional($user_to)->column8 ?? '',
        '[column9]' => optional($user_to)->column9 ?? '',
        '[column10]' => optional($user_to)->column10 ?? '',
    ];

    $rendered_header = strtr((string) $data->header, $replacements);
    $rendered_body = strtr((string) $data->body, $replacements);
    $rendered_footer = strtr((string) ($data->footer ?? ''), $replacements);

    $editUser = $data->edit_by ? \App\User::find($data->edit_by) : null;
    $editPath = LetterSignature::path($data->edit_signature)
        ?: ($editUser && $editUser->stemp && is_file(public_path('images/user/'.$editUser->stemp))
            ? public_path('images/user/'.$editUser->stemp) : null);

    $approveUser = $data->approved_by ? \App\User::find($data->approved_by) : null;
    $approvePath = LetterSignature::path($data->approve_signature)
        ?: ($approveUser && $approveUser->approve && is_file(public_path('images/user/'.$approveUser->approve))
            ? public_path('images/user/'.$approveUser->approve) : null);

    $signUser = $data->signed_by ? \App\User::find($data->signed_by) : null;
    $signPath = LetterSignature::path($data->sign_signature)
        ?: ($signUser && $signUser->sign && is_file(public_path('images/user/'.$signUser->sign))
            ? public_path('images/user/'.$signUser->sign) : null);

    $recipientName = trim((string) (optional($user_to)->name ?? ''));
    $recipientAddress = trim((string) (optional($user_to)->address ?? ''));
    $recipientPhone = trim((string) (optional($user_to)->phone_number ?? ''));
    $recipientEmail = trim((string) (optional($user_to)->email ?? ''));
@endphp

{{-- Normal letter order: letterhead (in open) → Ref → To → Dear → Subject → body → signature → footer --}}
<div class="letter-meta">
    Ref: {{ $data->reference }}<br>
    {{ date('M d, Y') }}
    @if(!empty($data->date_time))
        <br>Schedule: {{ \Carbon\Carbon::parse($data->date_time)->format('M d, Y h:i A') }}
    @endif
</div>

@if($recipientName !== '' || $recipientAddress !== '' || $recipientPhone !== '' || $recipientEmail !== '')
    <div class="letter-to-block">
        @if($recipientName !== '')
            <strong>{{ $recipientName }}</strong><br>
        @endif
        @if($recipientAddress !== '')
            {{ $recipientAddress }}<br>
        @endif
        @if($recipientPhone !== '')
            {{ $recipientPhone }}<br>
        @endif
        @if($recipientEmail !== '')
            {{ $recipientEmail }}
        @endif
    </div>
@endif

<div class="letter-dear">
    Dear{{ $recipientName !== '' ? ' '.$recipientName : '' }},
</div>

@if(trim(strip_tags($rendered_header)) !== '')
    <div class="letter-content-header">{!! $rendered_header !!}</div>
@endif

<div class="letter-body">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
    {!! $rendered_body !!}
</div>

<div class="letter-signature-row">
    <div class="letter-codes-back">
        <img src="data:image/png;base64,{{ DNS1D::getBarcodePNG($data->reference, 'C128') }}" width="280" alt="barcode"><br>
        <img src="data:image/png;base64,{{ DNS2D::getBarcodePNG(\App\Support\LetterQr::scanUrl($data), 'QRCODE') }}" width="90" alt="qr">
    </div>
    <div class="letter-signature-left">
        <p>Sincerely,</p>
        @if($data->is_edit == 1 && $editPath)
            <img class="edit" src="{{ $editPath }}" style="max-height:18px;width:auto;" alt="">
        @endif
        @if($data->is_approve == 1 && $approvePath)
            <img class="approve" src="{{ $approvePath }}" style="max-height:18px;width:auto;" alt="">
        @endif
        @if($data->is_sign == 1 && $signPath)
            <img src="{{ $signPath }}" style="max-height:56px;width:auto;" alt="Signature">
        @endif
    </div>
</div>

<div class="letter-footer-text">
    @if($data->footer != null && trim(strip_tags($rendered_footer)) !== '')
        {!! $rendered_footer !!}
    @else
        {{ $data->name }}
    @endif
    @if($peopleType === 'directory')
        @php $ccPeople = LetterRecipients::decodePeopleJson($data->cc_json); @endphp
        @if(count($ccPeople))
            <h5>CC:
                @foreach($ccPeople as $ccPerson)
                    {{ ($ccPerson['name'] ?? '') }}{{ ! $loop->last ? ', ' : '' }}
                @endforeach
            </h5>
        @endif
    @elseif($data->cc)
        <h5>CC:
            @foreach(explode(',', $data->cc) as $cc)
                {{ $user_class::find(trim($cc)) ? $user_class::find(trim($cc))->name . ', ' : '' }}
            @endforeach
        </h5>
    @endif
    @if($data->attachment)
        <h5>Files: {{ (isset($data->attachmentLib) && count($data->attachmentLib) > 0) ? count($data->attachmentLib) : 1 }}</h5>
    @endif
</div>
