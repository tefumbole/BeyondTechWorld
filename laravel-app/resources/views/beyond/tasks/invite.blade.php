@extends('beyond.layout')

@section('title', 'Task Assignment')
@section('meta_description', 'Respond to your task assignment.')

@section('content')
<div class="min-h-screen bg-gray-50 flex items-center justify-center p-4">
    @if (! $assignment)
        <div class="max-w-md w-full bg-white rounded-xl shadow-lg p-8 text-center">
            <i data-lucide="alert-triangle" class="w-12 h-12 text-amber-500 mx-auto mb-3"></i>
            <h1 class="text-xl font-bold text-gray-800 mb-2">Invalid Invite</h1>
            <p class="text-gray-600">This task invite link is invalid or has expired.</p>
            <a href="{{ url('/') }}" class="inline-block mt-4 text-brand-blue font-semibold hover:underline">Return Home</a>
        </div>
    @else
        <div class="max-w-lg w-full bg-white rounded-xl shadow-lg overflow-hidden" x-data="{ showPassword: false }">
            <div class="bg-brand-blue text-white px-6 py-5">
                <h1 class="text-xl font-bold">Task Assignment</h1>
                <p class="text-blue-100 text-sm mt-1">
                    You have been assigned: <strong>{{ $task->title }}</strong>
                    @if ($task->deadline) · Deadline {{ $task->deadline->format('M j, Y') }} @endif
                </p>
            </div>
            <div class="p-6 space-y-5">
                @if ($errors->any())
                    <div class="bg-red-50 border border-red-200 text-red-700 rounded-lg px-4 py-3 text-sm">{{ $errors->first() }}</div>
                @endif

                @if ($task->description)
                    <p class="text-gray-600 text-sm">{{ $task->description }}</p>
                @endif

                @include('beyond.tasks.partials.attachments', ['task' => $task])

                @if ($assignment->status !== 'Pending')
                    <div class="rounded-lg bg-gray-50 border border-gray-200 p-4 text-center">
                        <p class="text-gray-700">This task is already <strong>{{ $assignment->status }}</strong>.</p>
                        @auth('beyond')
                            <a href="{{ route('user.tasks') }}" class="inline-block mt-3 text-brand-blue font-semibold hover:underline">Go to My Tasks</a>
                        @endauth
                    </div>
                @elseif ($loggedIn && ! $isOwner)
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                        This task invite belongs to a different account. Open the link on the invited WhatsApp.
                    </div>
                @else
                    @if(session('success') || session('status'))
                        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') ?: session('status') }}</div>
                    @endif
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 space-y-3">
                        <p class="text-sm font-semibold text-brand-blue">Accept this task</p>
                        <p class="text-sm text-blue-900">Tap Accept. The WhatsApp code is what accepts the task.</p>
                        @if (! ($otpSent ?? false))
                            <form method="POST" action="{{ route('task.invite.setup.otp', $token) }}" class="space-y-3">
                                @csrf
                                @if (! $maskedPhone)
                                    <div>
                                        <label class="text-xs font-semibold text-gray-700">WhatsApp number on this account</label>
                                        <div class="flex gap-2 mt-1">
                                            <select name="country_code" class="rounded-md border border-gray-200 px-2 py-2 w-36 shrink-0 bg-white">
                                                @foreach(($countryCodes ?? []) as $code => $label)
                                                    <option value="{{ $code }}" @if(old('country_code', '+237') === $code) selected @endif>{{ $code }}</option>
                                                @endforeach
                                            </select>
                                            <input type="tel" name="phone" required value="{{ old('phone') }}"
                                                   placeholder="675321739"
                                                   class="flex-1 rounded-md border border-gray-200 px-3 py-2">
                                        </div>
                                    </div>
                                @else
                                    <p class="text-sm text-blue-900">The code goes to <strong>{{ $maskedPhone }}</strong>.</p>
                                @endif
                                <button type="submit" class="w-full bg-brand-blue text-white font-semibold py-2.5 rounded-md hover:bg-brand-dark">
                                    Accept
                                </button>
                            </form>
                        @else
                            <p class="text-sm text-blue-900">Code sent to <strong>{{ $maskedPhone ?: 'your WhatsApp' }}</strong>.</p>
                            <form method="POST" action="{{ route('task.invite.accept', $token) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="text-xs font-semibold text-gray-700">WhatsApp code</label>
                                    <input id="otp-code" type="text" name="otp" maxlength="8" required inputmode="numeric" autocomplete="one-time-code"
                                           value="{{ old('otp') }}"
                                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2 tracking-widest text-center text-lg"
                                           placeholder="47 47 82">
                                </div>
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-md">
                                    Accept task
                                </button>
                            </form>
                            <form method="POST" action="{{ route('task.invite.setup.otp', $token) }}" class="text-center">
                                @csrf
                                <button type="submit" class="text-sm text-brand-blue font-semibold hover:underline">Resend code</button>
                            </form>
                            @include('partials.otp_assist', ['otpInputId' => 'otp-code'])
                        @endif
                    </div>
                    @if ($isOwner)
                    <form method="POST" action="{{ route('task.invite.decline', $token) }}">
                        @csrf
                        <button type="submit" class="w-full border border-red-200 text-red-600 font-semibold py-2.5 rounded-md hover:bg-red-50">
                            Reject task
                        </button>
                    </form>
                    @endif
                    @if ($isOwner)
                    <div class="rounded-lg border border-gray-200 p-4">
                        <button type="button" class="text-sm font-semibold text-brand-blue hover:underline" @click="showPassword = !showPassword">
                            Optional: create a username and password
                        </button>
                        <form method="POST" action="{{ route('task.invite.setup', $token) }}" class="space-y-3 mt-3" x-show="showPassword" x-cloak>
                            @csrf
                            <p class="text-xs text-gray-500">Not required to accept the task.</p>
                            <div>
                                <label class="text-xs font-semibold text-gray-700">Username</label>
                                <input type="text" name="username" minlength="3" maxlength="100"
                                       value="{{ old('username') }}"
                                       class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                                       placeholder="e.g. gracious.chia">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-700">Password</label>
                                <input type="password" name="password" minlength="8" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                            </div>
                            <div>
                                <label class="text-xs font-semibold text-gray-700">Confirm password</label>
                                <input type="password" name="password_confirmation" minlength="8" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                            </div>
                            <button type="submit" class="w-full border border-brand-blue text-brand-blue font-semibold py-2.5 rounded-md hover:bg-blue-50">
                                Save username and password
                            </button>
                        </form>
                    </div>
                    @endif
                @endif
            </div>

        </div>
    @endif
</div>
@endsection
