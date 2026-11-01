@extends('layout.main')

@section('content')
@include('events.partials.modern')
<section class="forms events-shell">
    <div class="container-fluid">
        <div class="ev-page-head">
            <div>
                <div class="ev-kicker">Events</div>
                <h4>Event Settings</h4>
                <p>These roles and daily rates are what you assign when you add an engineer.</p>
            </div>
        </div>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="row">
            <div class="col-lg-4 mb-4">
                <div class="card">
                    <div class="card-header font-weight-bold">Add role</div>
                    <div class="card-body">
                        <form method="POST" action="{{ route('events.settings.categories.store') }}">
                            @csrf
                            <div class="form-group">
                                <label>Role</label>
                                <input type="text" name="name" class="form-control" required>
                            </div>
                            <div class="form-group">
                                <label>Code</label>
                                <input type="text" name="code" class="form-control" required placeholder="e.g. technician">
                            </div>
                            <div class="form-group">
                                <label>Default daily rate (XAF)</label>
                                <input type="number" name="default_daily_rate" class="form-control" min="0" required>
                            </div>
                            <div class="form-group">
                                <label>Description</label>
                                <textarea name="description" class="form-control" rows="2"></textarea>
                            </div>
                            <button type="submit" class="btn btn-primary">Add role</button>
                        </form>
                    </div>
                </div>
            </div>
            <div class="col-lg-8">
                <div class="card">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead style="background:#0b3f90;color:#fff;">
                                <tr>
                                    <th>Role</th>
                                    <th>Code</th>
                                    <th>Daily rate</th>
                                    <th>Status</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($categories as $cat)
                                    <tr>
                                        <td><strong>{{ $cat->name }}</strong><br><small class="text-muted">{{ $cat->description }}</small></td>
                                        <td><code>{{ $cat->code }}</code></td>
                                        <td>
                                            <form method="POST" action="{{ route('events.settings.categories.update', $cat->id) }}" class="form-inline">
                                                @csrf
                                                <input type="hidden" name="name" value="{{ $cat->name }}">
                                                <input type="hidden" name="code" value="{{ $cat->code }}">
                                                <input type="hidden" name="is_active" value="{{ $cat->is_active ? 1 : 0 }}">
                                                <input type="number" name="default_daily_rate" class="form-control form-control-sm mr-2" min="0" value="{{ $cat->default_daily_rate }}" style="width:120px" required>
                                                <button type="submit" class="btn btn-sm btn-primary">Save</button>
                                            </form>
                                        </td>
                                        <td>{{ $cat->is_active ? 'Active' : 'Inactive' }}</td>
                                        <td>
                                            <form method="POST" action="{{ route('events.settings.categories.update', $cat->id) }}">
                                                @csrf
                                                <input type="hidden" name="name" value="{{ $cat->name }}">
                                                <input type="hidden" name="code" value="{{ $cat->code }}">
                                                <input type="hidden" name="default_daily_rate" value="{{ $cat->default_daily_rate }}">
                                                <input type="hidden" name="is_active" value="{{ $cat->is_active ? 0 : 1 }}">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary">{{ $cat->is_active ? 'Deactivate' : 'Activate' }}</button>
                                            </form>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
@endsection
