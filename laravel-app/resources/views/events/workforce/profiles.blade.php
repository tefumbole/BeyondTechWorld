@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h4 class="mb-0">Event Worker Profiles</h4>
            <a href="{{ route('events.dashboard') }}" class="btn btn-light">← Events</a>
        </div>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif

        <div class="row">
            <div class="col-lg-4 mb-3">
                <div class="card">
                    <div class="card-header font-weight-bold">Enable worker</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('events.workforce.profiles.store') }}">
                            @csrf
                            <div class="form-group">
                                <label>From existing customer ID</label>
                                <input type="number" name="customer_id" class="form-control" placeholder="Customer ID">
                                <small class="text-muted">Or fill details below for a new profile.</small>
                            </div>
                            <div class="form-group">
                                <label>Category</label>
                                <select name="worker_category_id" class="form-control" required>
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat->id }}">{{ $cat->name }} ({{ number_format($cat->default_daily_rate) }} XAF)</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Daily rate (XAF)</label>
                                <input type="number" name="standard_daily_rate" class="form-control" min="0" required>
                            </div>
                            <div class="form-group">
                                <label>Specialization</label>
                                <select name="specialization" class="form-control">
                                    @foreach(\App\Services\EventWorkforceService::SPECIALIZATIONS as $k => $label)
                                        <option value="{{ $k }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Telephone</label>
                                <input type="text" name="telephone" class="form-control">
                            </div>
                            <div class="form-group">
                                <label>Email</label>
                                <input type="email" name="email" class="form-control">
                            </div>
                            <button type="submit" class="btn btn-primary btn-block">Create profile</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background:#0b3f90;color:#fff;">
                                <tr><th>Name</th><th>Category</th><th>Rate</th><th>Specialization</th><th>Phone</th></tr>
                            </thead>
                            <tbody>
                                @foreach($profiles as $p)
                                    <tr>
                                        <td><strong>{{ $p->displayName() }}</strong></td>
                                        <td>{{ optional($p->category)->name }}</td>
                                        <td>{{ number_format($p->standard_daily_rate) }} XAF</td>
                                        <td>{{ \App\Services\EventWorkforceService::SPECIALIZATIONS[$p->specialization] ?? $p->specialization }}</td>
                                        <td>{{ $p->telephone }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="card-footer">{{ $profiles->links() }}</div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
