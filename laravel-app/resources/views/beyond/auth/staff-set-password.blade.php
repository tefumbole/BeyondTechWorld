@extends('beyond.auth.layout')

@section('title', 'Create password')

@php
    $title = 'Create password';
    $header = '<h1 class="text-2xl font-bold text-brand-blue">Create your password</h1><p class="text-brand-blue text-sm mt-1">Use this password for future ERP sign-ins</p>';
@endphp

@section('auth_body')
<p class="text-sm text-gray-600 mb-4">Your phone is verified. Choose a password (minimum 8 characters) so you can sign in with email/username next time.</p>
<form method="POST" action="{{ url('/staff-set-password') }}" class="space-y-4">
    @csrf
    <div>
        <label class="text-sm font-semibold text-gray-700">New password</label>
        <input type="password" name="password" required minlength="8" autocomplete="new-password"
               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
        @error('password')
            <p class="text-red-600 text-sm mt-1">{{ $message }}</p>
        @enderror
    </div>
    <div>
        <label class="text-sm font-semibold text-gray-700">Confirm password</label>
        <input type="password" name="password_confirmation" required minlength="8" autocomplete="new-password"
               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
    </div>
    <button type="submit" class="w-full bg-brand-blue hover:bg-brand-dark text-white font-bold py-3 rounded-md">
        Save password and continue
    </button>
</form>
@endsection
