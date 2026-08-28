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
        <div class="max-w-lg w-full bg-white rounded-xl shadow-lg overflow-hidden" x-data="{ signFor: {{ $isOwner && $assignment->status === 'Pending' ? "'".$assignment->id."'" : 'null' }} }">
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
                @elseif (! $loggedIn)
                    @if(session('success') || session('status'))
                        <div class="rounded-lg bg-green-50 border border-green-200 text-green-800 px-4 py-3 text-sm">{{ session('success') ?: session('status') }}</div>
                    @endif
                    <div class="rounded-lg border border-blue-100 bg-blue-50 p-4 space-y-3">
                        <p class="text-sm font-semibold text-brand-blue">Create a username and password to accept this task</p>
                        <p class="text-sm text-blue-900">
                            You do not need an account yet. We will send a code to
                            <strong>{{ $maskedPhone ?: 'your WhatsApp' }}</strong>, then you choose a username and password.
                            We will WhatsApp those details to you.
                        </p>
                        @if (! ($otpSent ?? false))
                            <form method="POST" action="{{ route('task.invite.setup.otp', $token) }}">
                                @csrf
                                <button type="submit" class="w-full bg-brand-blue text-white font-semibold py-2.5 rounded-md hover:bg-brand-dark">
                                    Send WhatsApp code
                                </button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('task.invite.setup', $token) }}" class="space-y-3">
                                @csrf
                                <div>
                                    <label class="text-xs font-semibold text-gray-700">Verification code</label>
                                    <input type="text" name="otp" maxlength="6" required inputmode="numeric" autocomplete="one-time-code"
                                           value="{{ old('otp') }}"
                                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700">Username</label>
                                    <input type="text" name="username" required minlength="3" maxlength="100"
                                           value="{{ old('username') }}"
                                           class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2"
                                           placeholder="e.g. gracious.chia">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700">Password</label>
                                    <input type="password" name="password" required minlength="8" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                                </div>
                                <div>
                                    <label class="text-xs font-semibold text-gray-700">Confirm password</label>
                                    <input type="password" name="password_confirmation" required minlength="8" class="w-full mt-1 rounded-md border border-gray-200 px-3 py-2">
                                </div>
                                <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-md">
                                    Save login and continue
                                </button>
                            </form>
                            <form method="POST" action="{{ route('task.invite.setup.otp', $token) }}" class="text-center">
                                @csrf
                                <button type="submit" class="text-sm text-brand-blue font-semibold hover:underline">Resend code</button>
                            </form>
                        @endif
                    </div>
                    <div class="text-center text-sm space-y-2">
                        <a href="{{ url('/login?redirect='.urlencode('/task-invite/'.$token)) }}" class="block text-brand-blue font-semibold hover:underline">
                            Already have a login? Sign in
                        </a>
                        <a href="{{ url('/forgot-password?redirect='.urlencode('/task-invite/'.$token).(! empty($assignee->phone) ? '&phone='.urlencode($assignee->phone) : '')) }}"
                           class="block text-gray-600 hover:underline">
                            Forgot password? Reset with a WhatsApp OTP
                        </a>
                    </div>
                @elseif (! $isOwner)
                    <div class="rounded-lg bg-amber-50 border border-amber-200 p-4 text-sm text-amber-800">
                        This task invite belongs to a different account. Please sign in with the invited account.
                    </div>
                @else
                    <div class="flex gap-2">
                        <button type="button" @click="signFor = '{{ $assignment->id }}'" class="flex-1 bg-green-600 hover:bg-green-700 text-white font-semibold py-2.5 rounded-md inline-flex items-center justify-center gap-2">
                            <i data-lucide="check-circle" class="w-4 h-4"></i> Accept Task
                        </button>
                        <form method="POST" action="{{ route('task.invite.decline', $token) }}" class="flex-1">
                            @csrf
                            <button type="submit" class="w-full border border-red-200 text-red-600 font-semibold py-2.5 rounded-md inline-flex items-center justify-center gap-2 hover:bg-red-50">
                                <i data-lucide="x-circle" class="w-4 h-4"></i> Reject Task
                            </button>
                        </form>
                    </div>
                @endif
            </div>

            @if ($isOwner && $assignment->status === 'Pending')
                @include('beyond.tasks.partials.sign-modal', [
                    'assignmentId' => $assignment->id,
                    'action' => route('task.invite.accept', $token),
                ])
            @endif
        </div>
    @endif
</div>
@endsection
