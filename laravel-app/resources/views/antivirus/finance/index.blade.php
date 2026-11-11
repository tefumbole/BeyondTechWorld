@extends('layout.main')

@section('content')
<section class="av-admin">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-start flex-wrap mb-3" style="gap:12px;">
            <div>
                <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">Antivirus requests</h3>
                <p class="text-muted mb-0">Station requests for antivirus licences. Payment and receipt stay separate from the approval.</p>
            </div>
            <a class="btn btn-outline-primary" href="{{ $publicUrl }}" target="_blank">Open the request link</a>
        </div>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</div>@endif

        <div class="card mb-3">
            <div class="card-body">
                <h4 class="mb-1" style="color:#0b3f90;">Campaign</h4>
                <p class="text-muted">The public form uses this year. The Chief of Administration and Finance signs from each request.</p>
                <form method="POST" action="{{ route('admin.antivirus.settings') }}" class="form-row">
                    @csrf
                    <div class="form-group col-md-2">
                        <label for="campaign_year">Year</label>
                        <input class="form-control" id="campaign_year" name="campaign_year" inputmode="numeric" required value="{{ old('campaign_year', $settings->campaign_year) }}">
                    </div>
                    <div class="form-group col-md-4">
                        <label for="chief_name">Chief’s name</label>
                        <input class="form-control" id="chief_name" name="chief_name" data-wa-phone="off" value="{{ old('chief_name', $settings->chief_name) }}">
                    </div>
                    <div class="form-group col-md-3">
                        <label for="chief_phone">Chief’s phone</label>
                        <input class="form-control" id="chief_phone" name="chief_phone" type="tel" value="{{ old('chief_phone', $settings->chief_phone) }}" placeholder="+237 6 75 32 17 39">
                    </div>
                    <div class="form-group col-md-3 d-flex align-items-end">
                        <button class="btn btn-primary" type="submit" style="background:#0b3f90;border-color:#0b3f90;">Save</button>
                    </div>
                </form>
                <p class="mb-0"><strong>Shared link:</strong> {{ $publicUrl }}</p>
            </div>
        </div>

        <form method="GET" class="form-row mb-3">
            <div class="form-group col-md-3">
                <label for="filter-region">Region</label>
                <select class="form-control" id="filter-region" name="region">
                    <option value="">All regions</option>
                    @foreach($regions as $region)
                        <option value="{{ $region }}" {{ $filters['region'] === $region ? 'selected' : '' }}>{{ $region }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2">
                <label for="filter-year">Year</label>
                <input class="form-control" id="filter-year" name="year" value="{{ $filters['year'] }}" placeholder="2027">
            </div>
            <div class="form-group col-md-3">
                <label for="filter-status">Status</label>
                <select class="form-control" id="filter-status" name="status">
                    <option value="">All statuses</option>
                    @foreach(['awaiting_station' => 'Awaiting Station Approval', 'awaiting_chief' => 'Awaiting Chief Approval', 'approved' => 'Approved', 'declined' => 'Declined'] as $key => $label)
                        <option value="{{ $key }}" {{ $filters['status'] === $key ? 'selected' : '' }}>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div class="form-group col-md-2 d-flex align-items-end">
                <button class="btn btn-primary" type="submit" style="background:#c6ab47;border-color:#c6ab47;color:#0b3f90;font-weight:700;">Filter</button>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table">
                <thead>
                    <tr>
                        <th>Station</th>
                        <th>Region</th>
                        <th>Year</th>
                        <th>Quantity</th>
                        <th>Approval</th>
                        <th>Payment</th>
                        <th>Receipt</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        <tr>
                            <td><a href="{{ route('admin.antivirus.show', ['id' => $row->id]) }}">{{ $row->station }}</a><div class="text-muted">{{ $row->reference }}</div></td>
                            <td>{{ $row->region }}</td>
                            <td>{{ $row->year }}</td>
                            <td><strong>{{ $row->quantity }}</strong></td>
                            <td>{{ $row->progressLabel() }}<div class="text-muted">{{ $row->decisionLabel() }}</div></td>
                            <td>{{ $row->paymentLabel() }}</td>
                            <td @if($row->receipt_status === 'shortfall') style="color:#9f1239;font-weight:700;" @endif>{{ $row->receiptLabel() }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="7">No requests yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        {{ $rows->links() }}
    </div>
</section>
@endsection
