{{--
  Reusable Sign / Comment / Approve field actions.
  Params: $type (sign|stemp|approve), $label, $user, $fileField (current filename), $inputName
--}}
@php
    $type = $type ?? 'sign';
    $label = $label ?? 'Signature';
    $fileField = $fileField ?? null;
    $inputName = $inputName ?? $type;
    $uid = 'sig-'.$type;
    $phoneDisplay = \App\Support\WhatsAppPhone::display($user->phone ?: $user->additional_phone);
    $pending = !empty($user->sign_request_token)
        && ($user->sign_request_type ?? 'sign') === $type
        && $user->sign_request_expires_at
        && $user->sign_request_expires_at->isFuture();
@endphp
<div class="form-group user-sig-block" id="{{ $uid }}-block" data-type="{{ $type }}">
    <label><strong>{{ $label }}</strong></label>
    <input type="file" class="form-control" name="{{ $inputName }}" accept="image/*"
           @if($fileField) data-current="{{ url('public/images/user/'.$fileField) }}" @endif>
    <div class="{{ $uid }}-preview mt-2">
        @if($fileField)
            <img src="{{ url('public/images/user', $fileField) }}" height="50" style="float:none;display:block;" alt="{{ $label }}">
        @else
            <span class="text-muted d-block">No {{ strtolower($label) }} found</span>
        @endif
    </div>

    <div class="user-sign-actions mt-2">
        <button type="button" class="btn btn-info btn-sm btn-sig-add" data-type="{{ $type }}">
            <i class="dripicons-pencil"></i> Add {{ $label }}
        </button>
        <button type="button" class="btn btn-outline-info btn-sm btn-sig-pad" data-type="{{ $type }}" style="display:none;">
            Sign on this device
        </button>
        <button type="button" class="btn btn-outline-primary btn-sm btn-sig-whatsapp" data-type="{{ $type }}" style="display:none;"
                data-phone="{{ $phoneDisplay }}"
                data-url="{{ route('user.signature.request', $user->id) }}">
            <i class="fa fa-whatsapp"></i> Request link (WhatsApp)
        </button>
        @if($fileField)
            <button type="button" class="btn btn-outline-danger btn-sm btn-sig-delete" data-type="{{ $type }}"
                    data-url="{{ route('user.signature.delete', $user->id) }}">
                <i class="dripicons-trash"></i> Delete
            </button>
        @endif
    </div>

    <div class="sig-request-result mt-2" data-type="{{ $type }}" style="display:none;"></div>

    @if($pending)
        <div class="alert alert-light border mt-2 mb-0 p-2 small pending-sig-link" data-type="{{ $type }}">
            Pending {{ strtolower($label) }} link:
            <a href="{{ url('/user-sign/'.$user->sign_request_token) }}" target="_blank" rel="noopener">
                {{ url('/user-sign/'.$user->sign_request_token) }}
            </a>
        </div>
    @endif

    <p class="text-muted small mb-0 sig-hint" data-type="{{ $type }}" style="display:none;">
        Draw here, or WhatsApp a link so the user can add their {{ strtolower($label) }} on their phone.
    </p>

    <div class="user-sign-pad-wrap sig-pad-wrap" data-type="{{ $type }}" id="{{ $uid }}-pad-wrap">
        <p class="small text-muted mb-2">Draw below, then click Save.</p>
        <canvas class="sig-canvas" data-type="{{ $type }}" width="500" height="140"
                style="width:100%;max-width:500px;height:140px;background:transparent;border-radius:8px;touch-action:none;"></canvas>
        <div class="mt-2">
            <button type="button" class="btn btn-secondary btn-sm btn-sig-clear" data-type="{{ $type }}">Clear</button>
            <button type="button" class="btn btn-primary btn-sm btn-sig-save" data-type="{{ $type }}"
                    data-url="{{ route('user.signature.pad', $user->id) }}">Save {{ $label }}</button>
        </div>
        <div class="small mt-2 sig-pad-status" data-type="{{ $type }}"></div>
    </div>
</div>
