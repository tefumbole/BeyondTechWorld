@extends('layout.main')

@section('content')
@include('whatsapp_hub.partials.styles')
<section class="forms">
    <div class="container-fluid wa-shell">
        @include('whatsapp_hub.partials.nav')
        <h1 class="wa-title">Leads</h1>
        <p class="wa-sub">WhatsApp enquiries captured as CRM leads. Internal notes are never sent to the customer.</p>
        @if(session('message'))<div class="alert alert-success">{{ session('message') }}</div>@endif
        @if(session('not_permitted'))<div class="alert alert-danger">{{ session('not_permitted') }}</div>@endif

        <div class="row">
            <div class="col-md-2"><div class="wa-card"><div class="wa-stat-label">New</div><p class="wa-stat">{{ $cards['new'] }}</p></div></div>
            <div class="col-md-2"><div class="wa-card"><div class="wa-stat-label">Unassigned</div><p class="wa-stat">{{ $cards['unassigned'] }}</p></div></div>
            <div class="col-md-2"><div class="wa-card"><div class="wa-stat-label">Follow-up due</div><p class="wa-stat">{{ $cards['follow_up'] }}</p></div></div>
            <div class="col-md-2"><div class="wa-card"><div class="wa-stat-label">Quotation required</div><p class="wa-stat">{{ $cards['quotation'] }}</p></div></div>
            <div class="col-md-2"><div class="wa-card"><div class="wa-stat-label">Converted</div><p class="wa-stat">{{ $cards['converted'] }}</p></div></div>
            <div class="col-md-2"><div class="wa-card"><div class="wa-stat-label">Lost</div><p class="wa-stat">{{ $cards['lost'] }}</p></div></div>
        </div>

        <form method="get" class="form-inline mb-3">
            <input type="text" name="q" value="{{ request('q') }}" class="form-control mr-2 mb-2" placeholder="Name, phone, company, enquiry">
            <select name="status" class="form-control mr-2 mb-2">
                <option value="">All statuses</option>
                @foreach(\App\WhatsApp\LeadCatalog::statuses() as $k=>$label)
                    <option value="{{ $k }}" {{ request('status') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="category" class="form-control mr-2 mb-2">
                <option value="">All categories</option>
                @foreach(\App\WhatsApp\LeadCatalog::categories() as $k=>$label)
                    <option value="{{ $k }}" {{ request('category') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="priority" class="form-control mr-2 mb-2">
                <option value="">Any priority</option>
                @foreach(\App\WhatsApp\LeadCatalog::priorities() as $k=>$label)
                    <option value="{{ $k }}" {{ request('priority') === $k ? 'selected' : '' }}>{{ $label }}</option>
                @endforeach
            </select>
            <select name="assigned_user_id" class="form-control mr-2 mb-2">
                <option value="">Any staff</option>
                @foreach($staff as $u)
                    <option value="{{ $u->id }}" {{ (string) request('assigned_user_id') === (string) $u->id ? 'selected' : '' }}>{{ $u->name }}</option>
                @endforeach
            </select>
            <label class="mr-2 mb-2"><input type="checkbox" name="unassigned" value="1" {{ request('unassigned') ? 'checked' : '' }}> Unassigned</label>
            <label class="mr-2 mb-2"><input type="checkbox" name="follow_up_due" value="1" {{ request('follow_up_due') ? 'checked' : '' }}> Follow-up due</label>
            <input type="date" name="from" value="{{ request('from') }}" class="form-control mr-2 mb-2">
            <input type="date" name="to" value="{{ request('to') }}" class="form-control mr-2 mb-2">
            <button class="btn btn-primary mb-2" type="submit">Filter</button>
        </form>

        <div class="wa-card table-responsive">
            <table class="table mb-0">
                <thead>
                    <tr>
                        <th>Lead</th>
                        <th>Phone</th>
                        <th>Category</th>
                        <th>Status</th>
                        <th>Priority</th>
                        <th>Assigned</th>
                        <th>Follow-up</th>
                        <th>Latest enquiry</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($list as $lead)
                    <tr>
                        <td><a href="{{ route('whatsapp.leads.show', $lead->id) }}">{{ $lead->name ?: 'Unknown' }}</a></td>
                        <td>{{ $lead->normalized_phone }}</td>
                        <td>{{ $lead->categoryLabel() }}</td>
                        <td>{{ $lead->statusLabel() }}</td>
                        <td>{{ $lead->priority }}</td>
                        <td>{{ optional($lead->assignee)->name ?: 'Unassigned' }}</td>
                        <td>{{ $lead->follow_up_at ?: '—' }}</td>
                        <td>{{ \Illuminate\Support\Str::limit($lead->latest_enquiry, 50) }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-muted">No leads match.</td></tr>
                @endforelse
                </tbody>
            </table>
            {{ $list->appends(request()->query())->links() }}
        </div>
    </div>
</section>
@endsection
