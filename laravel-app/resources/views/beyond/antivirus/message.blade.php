@extends('beyond.layout')

@section('title', $title)

@section('content')
@include('beyond.antivirus._styles')
<section class="av-page">
    <div class="av-wrap">
        <div class="av-card">
            <h1 style="font-size:28px">{{ $title }}</h1>
            <p>{{ $message }}</p>
            <a class="av-btn" style="text-align:center;text-decoration:none;line-height:52px" href="{{ route('antivirus.form') }}">Back to the request form</a>
        </div>
    </div>
</section>
@endsection
