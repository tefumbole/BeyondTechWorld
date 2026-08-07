{{-- Created By + user signature (between name and email) with document date stamp --}}
@php
    $createdUser = $createdUser ?? null;
    $stampDate = $stampDate ?? null;
    $createdClass = $createdClass ?? 'inv-created';
    $sign = \App\Support\LetterSignature::invoiceSignatureForUser($createdUser, $stampDate);
@endphp
@if($createdUser)
    <div class="{{ $createdClass }}">
        <strong>{{ trans('file.Created By') }}:</strong> {{ $createdUser->name }}
        @if(!empty($sign['src']))
            <div class="inv-admin-sign inv-user-sign" style="margin:3px 0 2px;">
                <img src="{{ $sign['src'] }}" alt="Signature" style="height:42px;width:auto;max-width:180px;display:block;">
                @if(!empty($sign['stamp']))
                    <span class="inv-sign-date" style="display:block;font-size:7px;line-height:1.1;color:#444;margin-top:1px;">{{ $sign['stamp'] }}</span>
                @endif
            </div>
        @endif
        @if(!empty($createdUser->email))
            <div class="inv-created-email">{{ $createdUser->email }}</div>
        @endif
    </div>
@endif
