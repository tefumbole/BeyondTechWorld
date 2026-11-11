@extends('layout.main')

@section('content')
<section>
    <div class="container-fluid">
        <p class="mb-1"><a href="{{ route('admin.antivirus.index') }}">Antivirus requests</a></p>
        <div class="d-flex justify-content-between flex-wrap" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">{{ $row->reference ?: 'Draft' }}</h3>
                <p class="mb-0">{{ $row->decisionLabel() }}</p>
            </div>
            <div style="font-size:42px;font-weight:800;color:#0b3f90;line-height:1;">{{ $row->quantity }}</div>
        </div>
        @if(session('message'))<div class="alert alert-success mt-3">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger mt-3">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

        <div class="row mt-3">
            <div class="col-lg-7">
                <div class="card mb-3"><div class="card-body">
                    <p><strong>Station:</strong> {{ $row->station }}</p>
                    <p><strong>Region:</strong> {{ $row->region }}</p>
                    <p><strong>Year:</strong> {{ $row->year }}</p>
                    <p><strong>Requester:</strong> {{ $row->requester_name }} · {{ $row->requesterRoleLabel() }}</p>
                    <p class="mb-2"><strong>Approval:</strong> {{ $row->progressLabel() }}</p>
                    @foreach($row->approvers as $approver)
                        <div>{{ $approver->name }} · {{ $approver->roleLabel() }} · {{ ucfirst($approver->status) }}
                            @if($approver->signed_at) · {{ $approver->signed_at->format('d M Y H:i') }}@endif
                            @if($approver->signature_path) · <a href="{{ route('admin.antivirus.approver-signature', ['id' => $approver->id]) }}" target="_blank">Signature</a>@endif
                            @if($approver->status === 'pending')
                                <form method="POST" action="{{ route('admin.antivirus.resend', ['id' => $row->id]) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="kind" value="approver">
                                    <input type="hidden" name="approver_id" value="{{ $approver->id }}">
                                    <button class="btn btn-link btn-sm p-0" type="submit">Resend link</button>
                                </form>
                            @endif
                        </div>
                    @endforeach
                    <p class="mt-3 mb-1"><strong>Payment:</strong> {{ $row->paymentLabel() }}
                        @if($row->payment_recorded_at) · {{ $row->payment_date ? $row->payment_date->format('d M Y') : '' }} · {{ $row->payment_amount }} · {{ $row->payment_reference }}@endif
                    </p>
                    <p class="mb-1"><strong>Receipt:</strong> {{ $row->receiptLabel() }}
                        @if($row->receipt_confirmed_at) · {{ $row->receipt_quantity }} of {{ $row->quantity }} on {{ $row->receipt_confirmed_at->format('d M Y H:i') }}@endif
                    </p>
                    <p class="mb-0"><strong>Finance acknowledgement:</strong>
                        {{ $row->finance_acknowledged_at ? $row->finance_acknowledged_at->format('d M Y H:i') : 'Not acknowledged' }}
                    </p>
                </div></div>

                @if($row->status === 'approved')
                    <p><a class="btn btn-primary" style="background:#0b3f90;border-color:#0b3f90;" href="{{ route('admin.antivirus.pdf', ['id' => $row->id]) }}" target="_blank">Signed approval PDF</a></p>
                @endif
                @if($row->selfie_path)
                    <p><a href="{{ route('admin.antivirus.selfie', ['id' => $row->id]) }}" target="_blank">Requester selfie</a> · kept in the private record</p>
                @endif

                @if($row->forwarded_at && ! $row->finance_acknowledged_at)
                    <form method="POST" action="{{ route('admin.antivirus.acknowledge', ['id' => $row->id]) }}" class="mb-3">
                        @csrf
                        <button class="btn btn-outline-primary" type="submit">Acknowledge forwarded request</button>
                    </form>
                @endif

                @if($row->status === 'awaiting_chief')
                    <div class="card mb-3"><div class="card-body">
                        <h4 style="color:#0b3f90;">Chief approval</h4>
                        <p>{{ $settings->chief_name ?: 'Chief' }} · {{ $settings->chief_phone ?: 'Add the Chief’s phone on the Antivirus tab.' }}</p>
                        @if(!$chiefVerified)
                            <form method="POST" action="{{ route('admin.antivirus.chief-otp', ['id' => $row->id]) }}" class="mb-2">
                                @csrf
                                <input type="hidden" name="send" value="1">
                                <button class="btn btn-primary" type="submit" style="background:#c6ab47;border-color:#c6ab47;color:#0b3f90;">Send code</button>
                            </form>
                            <form method="POST" action="{{ route('admin.antivirus.chief-otp', ['id' => $row->id]) }}">
                                @csrf
                                <label for="chief-otp">Code</label>
                                <input class="form-control mb-2" id="chief-otp" name="otp" inputmode="numeric" autocomplete="one-time-code" required>
                                <button class="btn btn-outline-primary" type="submit">Verify</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.antivirus.chief-sign', ['id' => $row->id]) }}" id="av-sign-form">
                                @csrf
                                <label>Signature</label>
                                <canvas id="av-pad" width="520" height="160" style="width:100%;height:160px;border:1px dashed #94a3b8;border-radius:12px;touch-action:none;"></canvas>
                                <input type="hidden" name="signature" value="">
                                <p id="av-sign-error" class="text-danger"></p>
                                <button class="btn btn-light" id="av-clear" type="button">Clear</button>
                                <button class="btn btn-light" id="av-redo" type="button">Redo</button>
                                <button class="btn btn-primary" type="submit" style="background:#0b3f90;border-color:#0b3f90;">Sign and approve</button>
                            </form>
                            <form method="POST" action="{{ route('admin.antivirus.chief-decline', ['id' => $row->id]) }}" class="mt-3">
                                @csrf
                                <label for="chief-reason">Decline reason</label>
                                <input class="form-control mb-2" id="chief-reason" name="reason" required data-wa-phone="off">
                                <button class="btn btn-outline-danger" type="submit">Decline</button>
                            </form>
                        @endif
                        <form method="POST" action="{{ route('admin.antivirus.resend', ['id' => $row->id]) }}" class="mt-3">
                            @csrf
                            <input type="hidden" name="kind" value="chief">
                            <button class="btn btn-link p-0" type="submit">Resend the Chief’s notice</button>
                        </form>
                    </div></div>
                @endif

                @if($row->status === 'approved')
                    <div class="card mb-3"><div class="card-body">
                        <h4 style="color:#0b3f90;">Payment</h4>
                        <form method="POST" action="{{ route('admin.antivirus.payment', ['id' => $row->id]) }}">
                            @csrf
                            <div class="form-row">
                                <div class="form-group col-md-4">
                                    <label for="payment_date">Date</label>
                                    <input class="form-control" id="payment_date" name="payment_date" type="date" required value="{{ old('payment_date', $row->payment_date ? $row->payment_date->format('Y-m-d') : '') }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="payment_amount">Amount</label>
                                    <input class="form-control" id="payment_amount" name="payment_amount" inputmode="decimal" data-wa-phone="off" required value="{{ old('payment_amount', $row->payment_amount) }}">
                                </div>
                                <div class="form-group col-md-4">
                                    <label for="payment_reference">Reference</label>
                                    <input class="form-control" id="payment_reference" name="payment_reference" data-wa-phone="off" required value="{{ old('payment_reference', $row->payment_reference) }}">
                                </div>
                            </div>
                            <button class="btn btn-primary" type="submit" style="background:#0b3f90;border-color:#0b3f90;">Record payment</button>
                        </form>
                    </div></div>
                @endif
            </div>
            <div class="col-lg-5">
                <div class="card"><div class="card-body">
                    <h4 style="color:#0b3f90;">Activity</h4>
                    @foreach($row->activities as $activity)
                        <div class="mb-2">
                            <strong>{{ $activity->event }}</strong>
                            <div class="text-muted">{{ $activity->created_at ? $activity->created_at->format('d M Y H:i') : '' }}</div>
                            @if($activity->detail)<div>{{ $activity->detail }}</div>@endif
                        </div>
                    @endforeach
                    <form method="POST" action="{{ route('admin.antivirus.resend', ['id' => $row->id]) }}">
                        @csrf
                        <input type="hidden" name="kind" value="queued">
                        <button class="btn btn-link p-0" type="submit">Retry unsent messages</button>
                    </form>
                </div></div>
            </div>
        </div>
    </div>
</section>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/signature_pad@4.1.7/dist/signature_pad.umd.min.js"></script>
<script>
(function () {
    var canvas = document.getElementById('av-pad');
    var form = document.getElementById('av-sign-form');
    if (!canvas || !window.SignaturePad || !form) return;
    var pad = new SignaturePad(canvas, { minWidth: 0.8, maxWidth: 2.4, penColor: '#0b3f90' });
    var cleared = null;
    document.getElementById('av-clear').addEventListener('click', function () {
        if (!pad.isEmpty()) cleared = pad.toData();
        pad.clear();
    });
    document.getElementById('av-redo').addEventListener('click', function () {
        if (cleared) { pad.fromData(cleared); cleared = null; return; }
        var data = pad.toData();
        if (!data.length) return;
        data.pop();
        pad.fromData(data);
    });
    form.addEventListener('submit', function (event) {
        if (pad.isEmpty()) {
            event.preventDefault();
            document.getElementById('av-sign-error').textContent = 'Draw your signature before continuing.';
            return;
        }
        form.querySelector('input[name="signature"]').value = pad.toDataURL('image/png');
    });
})();
</script>
@endsection
