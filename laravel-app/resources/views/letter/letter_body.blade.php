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
    .pull-left {
        float: left;
        margin-left: 200px;
    }
    .pull-right-no-margin{
        float: right;
    }
    .pull-right {
        float: right;
        margin-right: 200px;
    }
    .waterm-mark {
        width: 20%;
        position: absolute;
        top: 40%;
        right: 330px;
        opacity: 0.3;
    }
    table {width: 100%;}
    tfoot tr th:first-child {text-align: left;}

    .centered {
        text-align: center;
        align-content: center;
    }
    .edit{
        position: absolute;
        margin-top: 0px;
        margin-left: 115px;
        z-index: 0;
        opacity: 0.5;
    }
    .approve{
        position: absolute;
        margin-top: 0px;
        margin-left: 50px;
        z-index: 0;
        opacity: 0.5;
    }
    .header-letter{
        margin-top: 40px;
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
@endphp

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
            @if(!empty($person['email']))<br>{{ $person['email'] }}@endif
        </div>
        <br>
    @endforeach
@endif
<div>Dear{{ count($dearNames) ? ' '.implode(', ', $dearNames) : '' }},
    @if($data->people_type == 'csv')
        <a href="{{url('public/letter/csv',$data->to)}}" target="_blank"><span class="fa fa-eye"></span> CSV File</a><br>
    @endif
</div>
@if(trim(strip_tags((string) $data->header)) !== '')
    <div class="mt-2 mb-2">{!! $data->header !!}</div>
@endif
<br><br>
<div  style="text-transform: uppercase">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
</div>
{!! $data->body !!}
<br>
<p>Sincerely, </p>
<div class="row">
    <div class="col-md-6">
        @if($data->is_sign == 1)
            @php
                $signUser = \App\User::find($data->signed_by);
                $signSrc = \App\Support\LetterSignature::resolveSignSrc($data, $signUser);
            @endphp
            @if($signSrc)
                <img class="letter-signature-img sign" src="{{ $signSrc }}" alt="Signer signature">
            @endif
        @endif
    </div>
</div>
<br>
@if($data->footer != null)
    {!! $data->footer !!}
@else
    {{ $data->name }}
@endif
@if($data->comment)
    <h1 style="background: yellow">Comment:
        {{ $data->comment }}
    </h1>
@endif
@if($data->people_type === 'directory')
    @php $ccPeople = \App\Support\LetterRecipients::decodePeopleJson($data->cc_json); @endphp
    @if(count($ccPeople))
        <br><br>
        <h2>CC:
            @foreach($ccPeople as $ccPerson)
                {{ $ccPerson['name'] ?? '' }}{{ ! $loop->last ? ', ' : '' }}
            @endforeach
        </h2>
    @endif
@elseif($data->cc)
    <br><br>
    <h2>CC:
        @php
            foreach (explode(",", $data->cc) as $cc) {
                echo $user::find($cc) ? $user::find($cc)->name .  ', ' : '';
            }
        @endphp
    </h2>
@endif

