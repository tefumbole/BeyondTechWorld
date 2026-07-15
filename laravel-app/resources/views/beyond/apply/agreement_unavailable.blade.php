@extends('beyond.layout')

@section('title', 'Agreement Unavailable')

@section('content')
<div class="min-h-screen bg-gray-50 py-16 px-4">
    <div class="max-w-lg mx-auto bg-white rounded-xl shadow-lg border border-gray-100 p-8 text-center">
        <h1 class="text-2xl font-extrabold text-brand-blue mb-2">Link not active</h1>
        <p class="text-gray-600">{{ $message }}</p>
        <a href="{{ url('/') }}" class="inline-block mt-6 text-brand-blue font-semibold hover:underline">Back to Home</a>
    </div>
</div>
@endsection
