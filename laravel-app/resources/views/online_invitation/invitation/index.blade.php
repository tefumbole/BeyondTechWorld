@extends('layout.main')
@section('content')

@if(session()->has('not_permitted'))
    <div class="alert alert-danger alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {{ session()->get('not_permitted') }}
    </div>
@endif
@if(session()->has('message'))
    <div class="alert alert-success alert-dismissible text-center">
        <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        {!! session()->get('message') !!}
    </div>
@endif

<section>
    <div class="container-fluid">
        <div class="d-flex flex-wrap justify-content-between align-items-center">
            <a href="{{ route('online_invitation.invitations.create') }}" class="btn btn-info mb-2"><i class="dripicons-plus"></i> Create Invitation</a>

            <form id="oi-bulk-send-form" action="{{ route('online_invitation.invitations.bulk_send') }}" method="POST" class="mb-2">
                @csrf
                <div id="oi-bulk-send-inputs" style="display:none;"></div>
                <div class="d-flex align-items-center" style="gap:10px;">
                    <div class="custom-control custom-checkbox">
                        <input type="checkbox" class="custom-control-input" id="oi-select-all">
                        <label class="custom-control-label" for="oi-select-all">Select all</label>
                    </div>
                    <button type="button" id="oi-bulk-send-btn" class="btn btn-primary" disabled>
                        <i class="dripicons-to-do"></i> Send Selected
                    </button>
                    <button type="button" id="oi-bulk-delete-btn" class="btn btn-danger" disabled>
                        <i class="dripicons-trash"></i> Delete Selected
                    </button>
                </div>
            </form>

            <form id="oi-bulk-delete-form" action="{{ route('online_invitation.invitations.bulk_delete') }}" method="POST" class="mb-2" style="display:none;">
                @csrf
                <div id="oi-bulk-delete-inputs" style="display:none;"></div>
            </form>

            <form method="GET" action="{{ route('online_invitation.invitations.index') }}" class="form-inline mb-2">
                <select name="status" class="form-control mr-2">
                    <option value="">All Status</option>
                    <option value="awaiting_sending" {{ request('status') === 'awaiting_sending' ? 'selected' : '' }}>awaiting_sending</option>
                    <option value="sent" {{ request('status') === 'sent' ? 'selected' : '' }}>sent</option>
                    <option value="used" {{ request('status') === 'used' ? 'selected' : '' }}>used</option>
                    <option value="failed" {{ request('status') === 'failed' ? 'selected' : '' }}>failed</option>
                </select>
                <div class="input-group">
                    <input type="text" name="q" class="form-control" placeholder="Search invitations..." value="{{ request('q') }}">
                    <div class="input-group-append">
                        <button class="btn btn-secondary" type="submit"><i class="dripicons-search"></i></button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="container-fluid mt-3">
        <div class="row">
            @forelse($data as $row)
                @php
                    $recipientName = $row->recipient_name ?: (optional($row->customer)->name ?: optional($row->user)->name);
                    $recipientPhone = $row->recipient_phone ?: (optional($row->customer)->phone_number ?: optional($row->user)->phone);
                    $recipientEmail = $row->recipient_email ?: (optional($row->customer)->email ?: optional($row->user)->email);
                    $recipientEmail = trim((string) $recipientEmail);
                    $recipientEmailLower = strtolower($recipientEmail);
                    if ($recipientEmail === '' || in_array($recipientEmailLower, ['—', '-', 'n/a', 'na', 'null', 'none'], true)) {
                        $recipientEmail = null;
                    }
                    $recipientLabel = $row->customer_id ? 'Customer' : ($row->user_id ? 'User' : 'CSV');
                @endphp
                <div class="col-sm-12 col-md-6 col-lg-4 mb-3">
                    <div class="card h-100" style="border-radius: 10px;">
                        <div class="card-body d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="custom-control custom-checkbox mb-1">
                                        <input type="checkbox" class="custom-control-input oi-invitation-checkbox" id="oi-inv-{{ $row->id }}" value="{{ $row->id }}">
                                        <label class="custom-control-label" for="oi-inv-{{ $row->id }}" style="font-size:12px; color:#6c757d;">Select</label>
                                    </div>
                                    <div class="text-muted" style="font-size: 12px;">Invitation #{{ $row->id }}</div>
                                    <h5 class="mb-1" style="font-weight: 600;">{{ optional($row->event)->name ?: '—' }}</h5>
                                    <div style="font-size: 13px;">
                                        <span class="text-muted">To ({{ $recipientLabel }}):</span>
                                        {{ $recipientName ?: '—' }}
                                        @if($recipientPhone)
                                            <span class="text-muted">({{ $recipientPhone }})</span>
                                        @endif
                                    </div>
                                    @if($recipientEmail)
                                        <div class="text-muted" style="font-size: 12px;">{{ $recipientEmail }}</div>
                                    @endif
                                </div>
                                <div class="text-right">
                                    @if($row->status === 'sent')
                                        <span class="badge badge-success">sent</span>
                                    @elseif($row->status === 'failed')
                                        <span class="badge badge-danger">failed</span>
                                    @else
                                        <span class="badge badge-warning">awaiting_sending</span>
                                    @endif
                                    @if($row->used_at)
                                        <span class="badge badge-info">used</span>
                                    @endif
                                </div>
                            </div>

                            <hr class="my-3">

                            <div style="font-size: 13px;">
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Attempts</span>
                                    <span>{{ (int) $row->send_attempts }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Sent At</span>
                                    <span>{{ $row->sent_at ?: '—' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Accepted At</span>
                                    <span>{{ $row->accepted_at ?: '—' }}</span>
                                </div>
                                <div class="d-flex justify-content-between">
                                    <span class="text-muted">Used At</span>
                                    <span>{{ $row->used_at ?: '—' }}</span>
                                </div>
                            </div>

                            @if($row->last_error)
                                <div class="mt-3 p-2" style="background: #fff5f5; border: 1px solid #f5c6cb; border-radius: 6px; color: #721c24; font-size: 12px; max-height: 62px; overflow: hidden;">
                                    {{ $row->last_error }}
                                </div>
                            @endif

                            <div class="mt-auto pt-3 d-flex flex-wrap" style="gap: 8px;">
                                @if($row->token)
                                    <a href="{{ route('online_invitation.invite.show', $row->token) }}" class="btn btn-sm btn-outline-secondary" title="Scan / verify view"><i class="dripicons-preview"></i> Verify</a>
                                    <a href="{{ route('online_invitation.invite.show', $row->token) }}?full=1" class="btn btn-sm btn-outline-secondary">Full invite</a>
                                @endif

                                @if(in_array($row->status, ['awaiting_sending', 'failed', 'sent'], true))
                                    <form action="{{ route('online_invitation.invitations.send', $row->id) }}" method="POST">
                                        @csrf
                                        <button type="submit" class="btn btn-sm btn-outline-primary">
                                            <i class="dripicons-to-do"></i>
                                            @if($row->status === 'sent')
                                                Resend
                                            @elseif($row->status === 'failed')
                                                Send Again
                                            @else
                                                Send
                                            @endif
                                        </button>
                                    </form>
                                @endif

                                <form action="{{ route('online_invitation.invitations.guest_apply_link', $row->id) }}" method="POST">
                                    @csrf
                                    <button type="submit" class="btn btn-sm btn-outline-info" title="Link for guests to request this event type by phone">
                                        <i class="dripicons-link"></i> Guest link
                                    </button>
                                </form>

                                <form action="{{ route('online_invitation.invitations.destroy', $row->id) }}" method="POST" onsubmit="return confirmDelete()">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="btn btn-sm btn-outline-danger"><i class="dripicons-trash"></i> Delete</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            @empty
                <div class="col-12">
                    <div class="alert alert-info mb-0">No invitations found.</div>
                </div>
            @endforelse
        </div>

        <nav>
            <ul class="pagination">
                {{ $data->appends(request()->query())->links() }}
            </ul>
        </nav>
    </div>
</section>

<script type="text/javascript">
    $("ul#online_invitation").siblings('a').attr('aria-expanded','true');
    $("ul#online_invitation").addClass("show");
    (function () {
        var params = new URLSearchParams(window.location.search);
        var status = params.get('status');
        if (status === 'sent') {
            $("ul#online_invitation #online-invitation-invitation-sent-menu").addClass("active");
        } else if (status === 'used') {
            $("ul#online_invitation #online-invitation-invitation-used-menu").addClass("active");
        } else if (status === 'failed') {
            $("ul#online_invitation #online-invitation-invitation-failed-menu").addClass("active");
        } else if (status === 'awaiting_sending') {
            $("ul#online_invitation #online-invitation-invitation-awaiting-menu").addClass("active");
        } else {
            $("ul#online_invitation #online-invitation-invitation-menu").addClass("active");
        }
    })();

    function oiUpdateBulkButton() {
        var anyChecked = $('.oi-invitation-checkbox:checked').length > 0;
        $('#oi-bulk-send-btn').prop('disabled', !anyChecked);
        $('#oi-bulk-delete-btn').prop('disabled', !anyChecked);
    }

    $('#oi-select-all').on('change', function () {
        var checked = $(this).is(':checked');
        $('.oi-invitation-checkbox').prop('checked', checked);
        oiUpdateBulkButton();
    });

    $(document).on('change', '.oi-invitation-checkbox', function () {
        var total = $('.oi-invitation-checkbox').length;
        var checkedCount = $('.oi-invitation-checkbox:checked').length;
        $('#oi-select-all').prop('checked', total > 0 && checkedCount === total);
        oiUpdateBulkButton();
    });

    $('#oi-bulk-send-btn').on('click', function () {
        var ids = $('.oi-invitation-checkbox:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length) return;

        var container = $('#oi-bulk-send-inputs');
        container.empty();
        for (var i = 0; i < ids.length; i++) {
            container.append('<input type="hidden" name="ids[]" value="' + ids[i] + '">');
        }
        $('#oi-bulk-send-form')[0].submit();
    });

    $('#oi-bulk-delete-btn').on('click', function () {
        var ids = $('.oi-invitation-checkbox:checked').map(function () { return $(this).val(); }).get();
        if (!ids.length) return;

        if (!confirm('Delete selected invitations?')) return;

        var container = $('#oi-bulk-delete-inputs');
        container.empty();
        for (var i = 0; i < ids.length; i++) {
            container.append('<input type="hidden" name="ids[]" value="' + ids[i] + '">');
        }
        $('#oi-bulk-delete-form')[0].submit();
    });
</script>

@endsection
