<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Client Portal - {{ $general_setting->site_title ?? 'Rentals' }}</title>
    <link rel="stylesheet" href="{{ asset('public/vendor/bootstrap/css/bootstrap.min.css') }}">
    <style>
        body { background: #f3f6fb; font-family: "Nunito", sans-serif; }
        .portal-header {
            background: #0b3f90; color: #fff; padding: 24px 0;
        }
        .portal-card {
            background: #fff; border-radius: 14px; padding: 20px;
            box-shadow: 0 2px 12px rgba(11,63,144,0.08); margin-bottom: 18px;
        }
        .badge-signed { background: #c6ab47; color: #10213d; }
    </style>
</head>
<body>
<div class="portal-header">
    <div class="container">
        <h2 class="mb-1">{{ $general_setting->site_title ?? 'Client Portal' }}</h2>
        <p class="mb-0">Welcome, {{ $customer->name }}</p>
    </div>
</div>

<div class="container py-4">
    @if(session()->has('message'))
        <div class="alert alert-success">{{ session()->get('message') }}</div>
    @endif
    @if(session()->has('not_permitted'))
        <div class="alert alert-danger">{{ session()->get('not_permitted') }}</div>
    @endif

    @if($contract->signed_at)
        <div class="portal-card">
            @if($contract->isPendingReview())
                <span class="badge badge-warning p-2">Awaiting Admin Review</span>
                <p class="mt-3 mb-0">You signed on {{ $contract->signed_at->format('d M Y, H:i') }}. Our team will countersign and send your final PDF and QR code via WhatsApp shortly.</p>
            @elseif($contract->isApproved())
                <span class="badge badge-signed p-2">Agreement Approved</span>
                <p class="mt-3 mb-0">Signed on {{ $contract->signed_at->format('d M Y, H:i') }} · Approved {{ optional($contract->approved_at)->format('d M Y, H:i') }}</p>
            @else
                <span class="badge badge-signed p-2">Agreement Signed</span>
                <p class="mt-3 mb-0">Signed on {{ $contract->signed_at->format('d M Y, H:i') }}</p>
            @endif
            @if($contract->client_username && $contract->generated_password)
                <p class="mt-2 mb-0"><strong>Login username:</strong> {{ $contract->client_username }}</p>
                <p class="mb-0"><strong>System password:</strong> {{ $contract->generated_password }}</p>
            @endif
        </div>
    @endif

    <div class="portal-card">
        <h4>{{ ($contract->contract_type ?? '') === 'software_license' ? 'Your Subscriptions' : 'Your Rentals' }}</h4>
        <div class="table-responsive">
            <table class="table table-bordered">
                <thead>
                    <tr>
                        <th>Reference</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Total</th>
                        <th>{{ ($contract->contract_type ?? '') === 'software_license' ? 'Products' : 'Equipment' }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($bookings as $rental)
                        <tr>
                            <td>{{ $rental->reference_no }}</td>
                            <td>{{ $rental->created_at->format('d M Y') }}</td>
                            <td>
                                @if($rental->booking_status == 1) Completed
                                @elseif($rental->booking_status == 2) Pending
                                @elseif($rental->booking_status == 3) Returned
                                @else Draft @endif
                            </td>
                            <td>{{ number_format($rental->grand_total, 2) }}</td>
                            <td>
                                @foreach($rental->bookingProduct as $line)
                                    {{ optional($line->product)->name }} (x{{ $line->qty }})<br>
                                @endforeach
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    @if($contract->signed_at)
        <div class="portal-card">
            <h4>Create Your Login Credentials</h4>
            <p class="text-muted">You can keep the phone-number login or set a custom username and password.</p>
            <form method="POST" action="{{ route('rental.portal.credentials', $token) }}">
                @csrf
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" name="username" class="form-control" value="{{ $contract->client_username ?? $customer->phone_number }}" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="password" class="form-control" required minlength="6">
                </div>
                <div class="form-group">
                    <label>Confirm Password</label>
                    <input type="password" name="password_confirmation" class="form-control" required minlength="6">
                </div>
                <button type="submit" class="btn btn-primary">Save Credentials</button>
                <a href="{{ route('frontend.book.index') }}" class="btn btn-outline-secondary ml-2">Go to My Bookings</a>
            </form>
        </div>
    @endif
</div>
</body>
</html>
