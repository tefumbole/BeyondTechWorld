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
        <a href="{{url('public/announcement/attachment',$data->attachment)}}" target="_blank"><span class="fa fa-eye"></span> View Attachment</a>
        <a href="{{route('announcement.attachment.delete.first', ['id' => $data->id])}}" class="text-danger" onclick="return confirmDelete()">X</a><br>
@endif

@if($data->attachmentLib)
    @foreach($data->attachmentLib as $key => $attachment)
        @if($key == 0)
            @continue
        @endif
        <a href="{{url('public/announcement/attachment',$attachment->attachment)}}" target="_blank"><span class="fa fa-eye"></span> View Attachment</a>
        <a href="{{route('announcement.attachment.delete', ['id' => $attachment->id])}}" class="text-danger" onclick="return confirmDelete()">X</a>
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
    } else {
        $user = \App\Employee::class;
    }
@endphp

<div class="pull-right-no-margin">

    <span class ="header-letter">{!! $data->header !!}</span>

</div>
<br><br><br><br>

<div>
    Ref: {{ $data->id }} <br>
    {{ date('M d, Y') }}
    @if(!empty($data->date_time))
        <br>
        Schedule: {{ \Carbon\Carbon::parse($data->date_time)->format('M d, Y h:i A') }}
    @endif
</div>
<br>
<div>Dear:
    @if($data->people_type != 'csv')
        @foreach (explode(",", $data->to) as $to)
            {{ $user::find($to) ? $user::find($to)->name .  ', ' : '' }}
        @endforeach
    @else
        <a href="{{url('public/announcement/csv',$data->to)}}" target="_blank"><span class="fa fa-eye"></span> CSV File</a><br>
    @endif

</div>
<br><br>
<div  style="text-transform: uppercase">
    <h2>Subject: <span style="text-decoration: underline;">{{ $data->subject }}</span></h2>
</div>
{!! $data->body !!}
<br>

@if($data->footer != null)
    {!! $data->footer !!}
@else
    {{ $data->name }}
@endif
@if($data->cc)
    <br><br>
    <h2>CC:
        @php
            foreach (explode(",", $data->cc) as $cc) {
                echo $user::find($cc) ? $user::find($cc)->name .  ', ' : '';
            }
        @endphp
    </h2>
@endif

