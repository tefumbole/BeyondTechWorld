@extends('beyond.auth.layout')

@section('title', 'Complete Profile')

@php
    $title = 'Complete Your Profile';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Complete Your Profile</h1><p class="text-brand-blue text-sm mt-1">Set your login credentials before continuing</p>';
@endphp

@section('auth_body')
<form method="POST" action="{{ url('/complete-profile') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-sm font-semibold text-gray-700">Full Name *</label>
        <input type="text" name="full_name" required value="{{ old('full_name', $user->name) }}" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
    </div>
    <div>
        <label class="text-sm font-semibold text-gray-700">Username *</label>
        <input type="text" name="username" required value="{{ old('username', $user->username) }}" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
    </div>
    <div>
        <label class="text-sm font-semibold text-gray-700">Email *</label>
        <input type="email" name="email" required value="{{ old('email', $user->email) }}" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
    </div>
    <div>
        <label class="text-sm font-semibold text-gray-700">Address *</label>
        <textarea name="address" required rows="2" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">{{ old('address', $user->address) }}</textarea>
    </div>
    <div>
        <label class="text-sm font-semibold text-gray-700">New Password *</label>
        <input type="password" name="password" required minlength="6" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
    </div>
    <div>
        <label class="text-sm font-semibold text-gray-700">Confirm Password *</label>
        <input type="password" name="password_confirmation" required class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
    </div>
    <button type="submit" class="w-full bg-brand-gold text-brand-blue font-bold py-3 rounded-md">Save & Continue</button>
</form>
@endsection
