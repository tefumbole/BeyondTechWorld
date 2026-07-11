@extends('beyond.layout')

@section('title', 'My Profile')

@section('content')
<div class="min-h-screen bg-gray-50 py-12">
    <div class="max-w-2xl mx-auto px-4">
        <div class="bg-white rounded-xl shadow-md border-t-4 border-t-brand-blue overflow-hidden">
            <div class="p-6 border-b">
                <h1 class="text-2xl font-bold text-brand-blue flex items-center gap-2">
                    <i data-lucide="user-cog" class="w-6 h-6"></i> My Profile
                </h1>
                <p class="text-gray-600 text-sm mt-1">Update your account details, login username, and password.</p>
            </div>
            <div class="p-6">
                @if (session('success'))
                    <div class="mb-4 rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') }}</div>
                @endif
                @if ($errors->any())
                    <div class="mb-4 rounded-lg bg-red-50 border border-red-200 text-red-800 px-4 py-3 text-sm">{{ $errors->first() }}</div>
                @endif
                <form method="POST" action="{{ url('/user/profile') }}" class="space-y-4">
                    @csrf
                    @method('PATCH')
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Full Name</label>
                        <input type="text" name="full_name" required value="{{ old('full_name', optional($profile)->full_name ?: $user->name) }}"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Username</label>
                        <input type="text" name="username" value="{{ old('username', $user->username) }}"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2" placeholder="username for login">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Email Address</label>
                        <input type="email" name="email" value="{{ old('email', $user->email) }}"
                               class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <div>
                        <label class="text-sm font-semibold text-gray-700">Address</label>
                        <textarea name="address" rows="3" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">{{ old('address', $user->address) }}</textarea>
                    </div>
                    <div class="pt-4 border-t">
                        <p class="text-sm font-semibold text-gray-700 mb-2">Change Password (optional)</p>
                        <input type="password" name="password" placeholder="New password" class="w-full rounded-md border border-gray-200 px-3 py-2 mb-2">
                        <input type="password" name="password_confirmation" placeholder="Confirm password" class="w-full rounded-md border border-gray-200 px-3 py-2">
                    </div>
                    <button type="submit" class="bg-brand-blue hover:bg-brand-dark text-white font-bold px-6 py-3 rounded-md">Save Changes</button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection
