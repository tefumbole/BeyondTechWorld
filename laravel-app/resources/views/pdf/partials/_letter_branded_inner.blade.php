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

    if (! $user_to && ! empty($to) && in_array($peopleType, ['customer', 'user'], true)) {
        $user_to = $user_class::find($to);
    }

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
    $editDate = $data->edit_signed_at ?? ($data->updated_at ?? now());
    $editSrc = $editPath ? (LetterSignature::stampedDataUriFromPath($editPath, $editDate) ?: $editPath) : null;

    $approveUser = $data->approved_by ? \App\User::find($data->approved_by) : null;
    $approvePath = LetterSignature::path($data->approve_signature)
        ?: ($approveUser && $approveUser->approve && is_file(public_path('images/user/'.$approveUser->approve))
            ? public_path('images/user/'.$approveUser->approve) : null);
    $approveDate = $data->approve_signed_at ?? ($data->updated_at ?? now());
    $approveSrc = $approvePath ? (LetterSignature::stampedDataUriFromPath($approvePath, $approveDate) ?: $approvePath) : null;

    $signUser = $data->signed_by ? \App\User::find($data->signed_by) : null;
    $signPath = LetterSignature::path($data->sign_signature)
        ?: ($signUser && $signUser->sign && is_file(public_path('images/user/'.$signUser->sign))
            ? public_path('images/user/'.$signUser->sign) : null);
    // Prefer letter PNG signature (transparent ink). Fall back to account file path for DomPDF.
    $signSrc = $signPath;

    $recipientName = trim((string) (optional($user_to)->name ?? ''));
    $recipientAddress = trim((string) (optional($user_to)->address ?? ''));
    $recipientPhone = trim((string) (optional($user_to)->phone_number ?? ''));
    // Email intentionally omitted from the printed letter.

    $closingName = trim(strip_tags((string) ($data->name ?? '')));
    $hasFooterHtml = $data->footer != null && trim(strip_tags($rendered_footer)) !== '';
@endphp

{{-- Top-right: letter header (e.g. BCL) + tiny comment/approve stamps with dates --}}
<div class="letter-top-right">
    @if(trim(strip_tags($rendered_header)) !== '')
        <div class="letter-corner-header">{!! $rendered_header !!}</div>
    @endif
    @if($data->is_edit == 1 && $editSrc)
        <div class="letter-corner-stamp">
            <img src="{{ $editSrc }}" alt="Comment">
        </div>
    @endif
    @if($data->is_approve == 1 && $approveSrc)
        <div class="letter-corner-stamp">
            <img src="{{ $approveSrc }}" alt="Approve">
        </div>
    @endif
</div>

<div class="letter-meta">
    Ref: {{ $data->reference }}<br>
    {{ date('M d, Y') }}
    @if(!empty($data->date_time))
        <br>Schedule: {{ \Carbon\Carbon::parse($data->date_time)->format('M d, Y h:i A') }}
    @endif
</div>

@if($recipientName !== '' || $recipientAddress !== '' || $recipientPhone !== '')
    <div class="letter-to-block">
        @if($recipientName !== '')
            <strong>{{ $recipientName }}</strong><br>
        @endif
        @if($recipientAddress !== '')
            {{ $recipientAddress }}<br>
        @endif
        @if($recipientPhone !== '')
            {{ $recipientPhone }}
        @endif
    </div>
@endif

<div class="letter-dear">
    Dear{{ $recipientName !== '' ? ' '.$recipientName : '' }},
</div>

<div class="letter-body">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
    {!! $rendered_body !!}
</div>

<div class="letter-signature-row">
    <div class="letter-codes-back">
        <img class="letter-qr" src="data:image/png;base64,{{ DNS2D::getBarcodePNG(\App\Support\LetterQr::scanUrl($data), 'QRCODE') }}" width="90" alt="qr"><br>
        <img class="letter-barcode" src="data:image/png;base64,{{ DNS1D::getBarcodePNG($data->reference, 'C128') }}" width="280" alt="barcode">
    </div>
    <div class="letter-signature-left">
        <p class="letter-sincerely">Sincerely,</p>
        @if($data->is_sign == 1 && $signSrc)
            <img class="letter-sign-img" src="{{ $signSrc }}" alt="Signature">
        @endif
        <div class="letter-closing">
            @if($hasFooterHtml)
                {!! $rendered_footer !!}
            @elseif($closingName !== '')
                {{ $closingName }}
            @endif
            @if($peopleType === 'directory')
                @php $ccPeople = LetterRecipients::decodePeopleJson($data->cc_json); @endphp
                @if(count($ccPeople))
                    <div class="letter-cc">CC:
                        @foreach($ccPeople as $ccPerson)
                            {{ ($ccPerson['name'] ?? '') }}{{ ! $loop->last ? ', ' : '' }}
                        @endforeach
                    </div>
                @endif
            @elseif($data->cc)
                <div class="letter-cc">CC:
                    @foreach(explode(',', $data->cc) as $cc)
                        {{ $user_class::find(trim($cc)) ? $user_class::find(trim($cc))->name . ', ' : '' }}
                    @endforeach
                </div>
            @endif
            @if($data->attachment)
                <div class="letter-cc">Files: {{ (isset($data->attachmentLib) && count($data->attachmentLib) > 0) ? count($data->attachmentLib) : 1 }}</div>
            @endif
        </div>
    </div>
</div>
