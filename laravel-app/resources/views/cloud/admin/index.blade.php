@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <h3 class="mb-1" style="color:#0b3f90;font-weight:800;">Subscriptions</h3>
        <p class="text-muted">Prices, trials, and company portals. Changing a price applies to the next checkout. A company already on a trial keeps the price quoted when that trial started.</p>
        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">{{ $errors->first() }}</div>
        @endif

        <ul class="nav nav-tabs mb-3">
            <li class="nav-item"><a class="nav-link active" href="#cloud-plans">Plans &amp; pricing</a></li>
            <li class="nav-item"><a class="nav-link" href="#cloud-subscribers">Subscribers</a></li>
            <li class="nav-item"><a class="nav-link" href="#cloud-methods">Payment methods</a></li>
        </ul>

        <div id="cloud-plans" class="card mb-4">
            <div class="card-header bg-white"><strong>Plans &amp; pricing</strong></div>
            <div class="card-body">
                @foreach($plans as $plan)
                    <form method="POST" action="{{ route('cloud.admin.price', $plan->id) }}" class="border rounded p-3 mb-3">
                        @csrf
                        <div class="row align-items-end">
                            <div class="col-md-3">
                                <strong>{{ $plan->name }}</strong>
                                <div class="text-muted small">{{ $plan->module ? $plan->module->code : '' }}</div>
                            </div>
                            <div class="col-md-2 form-group mb-0">
                                <label>Price</label>
                                <input type="number" step="0.01" min="0" name="price" class="form-control" value="{{ $plan->price }}" required>
                            </div>
                            <div class="col-md-1 form-group mb-0">
                                <label>Currency</label>
                                <input type="text" name="currency" maxlength="3" class="form-control" value="{{ $plan->currency }}" required>
                            </div>
                            <div class="col-md-2 form-group mb-0">
                                <label>Trial length</label>
                                <input type="number" min="1" max="720" name="trial_value" class="form-control" value="{{ $plan->trial_value }}" required>
                            </div>
                            <div class="col-md-2 form-group mb-0">
                                <label>Trial unit</label>
                                <select name="trial_unit" class="form-control">
                                    <option value="HOUR" {{ $plan->trial_unit === 'HOUR' ? 'selected' : '' }}>Hours</option>
                                    <option value="MONTH" {{ $plan->trial_unit === 'MONTH' ? 'selected' : '' }}>Months</option>
                                </select>
                            </div>
                            <div class="col-md-1 form-group mb-0">
                                <label class="d-block">Active</label>
                                <input type="checkbox" name="active" value="1" {{ $plan->active ? 'checked' : '' }}>
                            </div>
                            <div class="col-md-1 form-group mb-0">
                                <button class="btn btn-primary btn-sm" type="submit">Save</button>
                            </div>
                        </div>
                    </form>
                @endforeach
            </div>
        </div>

        <div id="cloud-subscribers" class="card mb-4">
            <div class="card-header bg-white"><strong>Subscribers</strong></div>
            <div class="card-body table-responsive">
                <table class="table">
                    <thead>
                        <tr><th>Company</th><th>Phone</th><th>Subscriptions</th></tr>
                    </thead>
                    <tbody>
                        @forelse($tenants as $tenant)
                            <tr>
                                <td>{{ $tenant->name }}<div class="text-muted small">{{ $tenant->system_name }}</div></td>
                                <td>{{ $tenant->phone }}</td>
                                <td>
                                    @forelse($tenant->subscriptions as $subscription)
                                        <div>{{ $subscription->plan ? $subscription->plan->name : 'Plan' }} — {{ $subscription->status }}
                                            @if($subscription->trial_ends_at)
                                                until {{ $subscription->trial_ends_at->format('Y-m-d H:i') }}
                                            @endif
                                        </div>
                                    @empty
                                        <span class="text-muted">None yet</span>
                                    @endforelse
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="text-muted">No companies have registered a portal yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div id="cloud-methods" class="card mb-4">
            <div class="card-header bg-white"><strong>Payment methods</strong></div>
            <div class="card-body">
                <p class="text-muted">These use the MoMo and VISA checkouts already configured for this system.</p>
                <ul class="mb-0">
                    @foreach($methods as $method)
                        <li><strong>{{ $method->name }}</strong> — {{ $method->provider }} {{ $method->active ? '' : '(inactive)' }}</li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</section>
@endsection
