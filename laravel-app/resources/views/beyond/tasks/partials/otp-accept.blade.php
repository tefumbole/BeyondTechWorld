@php
    $waiting = isset($acceptWaiting) && (string) $acceptWaiting === (string) $assignmentId;
@endphp
@if ($waiting)
    <form method="POST" action="{{ $acceptAction }}" class="flex-1 space-y-2">
        @csrf
        <label class="block text-xs font-semibold text-gray-700">Code sent to {{ $acceptMasked ?: 'your WhatsApp' }}</label>
        <input type="text" name="otp" required maxlength="8" inputmode="numeric" autocomplete="one-time-code"
               placeholder="47 47 82"
               class="w-full rounded-md border border-gray-200 px-3 py-2 text-center tracking-widest">
        <button type="submit" class="w-full bg-green-600 hover:bg-green-700 text-white text-sm font-semibold py-2 rounded-md">
            Accept task
        </button>
    </form>
    <form method="POST" action="{{ $sendAction }}">
        @csrf
        <button type="submit" class="text-xs text-brand-blue font-semibold px-2 py-2 hover:underline">Resend</button>
    </form>
@else
    <form method="POST" action="{{ $sendAction }}" class="flex-1">
        @csrf
        <button type="submit" class="w-full bg-brand-blue text-white hover:bg-brand-dark text-sm font-semibold py-2 rounded-md">
            Accept
        </button>
    </form>
@endif
