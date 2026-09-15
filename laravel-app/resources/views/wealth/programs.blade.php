@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">Programs</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')

        <div class="wm-card">
            <h5>Create program</h5>
            <form method="POST" action="{{ route('wealth.programs.store') }}" enctype="multipart/form-data">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="wm-label">Name *</label><input class="wm-field" name="name" required></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Code *</label><input class="wm-field" name="code" required></div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Company</label>
                        <select name="biller_id" class="wm-field"><option value="">—</option>@foreach($billers as $b)<option value="{{ $b->id }}">{{ $b->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Manager</label>
                        <select name="manager_user_id" class="wm-field"><option value="">—</option>@foreach($users as $u)<option value="{{ $u->id }}">{{ $u->name }}</option>@endforeach</select>
                    </div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Status</label>
                        <select name="status" class="wm-field">
                            <option value="planning">Planning</option>
                            <option value="active">Active</option>
                            <option value="completed">Completed</option>
                            <option value="cancelled">Cancelled</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Start</label><input type="date" class="wm-field" name="start_date"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">End</label><input type="date" class="wm-field" name="end_date"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Budget</label><input class="wm-field" name="proposed_budget" type="number" step="0.01"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Expected income</label><input class="wm-field" name="expected_income" type="number" step="0.01"></div>
                    <div class="col-md-4 mb-2"><label class="wm-label">Description</label><input class="wm-field" name="description"></div>
                    <div class="col-md-12"><button class="wm-btn" type="submit">Create program</button></div>
                </div>
            </form>
        </div>

        <div class="row">
            @forelse($cards as $card)
                @php $f = $card['finance']; $p = $card['program']; @endphp
                <div class="col-md-6">
                    <div class="wm-card">
                        <h4 style="color:#0b3f90;font-weight:800;">{{ $p->name }}</h4>
                        <div class="small text-muted">{{ strtoupper($p->status) }} · {{ $p->code }}</div>
                        <p class="mb-1">Income: {{ number_format($f['income'], 2) }} {{ $p->currency }}</p>
                        <p class="mb-1">Expenses: {{ number_format($f['expenses'], 2) }}</p>
                        <p class="mb-1">Balance: {{ number_format($f['balance'], 2) }}</p>
                        <p class="mb-2">Spent: {{ $f['spent_pct'] }}% · Remaining: {{ $f['remain_pct'] }}%</p>
                        <a class="wm-btn" href="{{ route('wealth.programs.show', $p->id) }}">View Financials</a>
                        <a class="wm-btn wm-btn-out" href="{{ route('wealth.income', ['program_id' => $p->id]) }}">Add Income</a>
                        <a class="wm-btn wm-btn-out" href="{{ route('wealth.expenses', ['program_id' => $p->id]) }}">Add Expense</a>
                    </div>
                </div>
            @empty
                <div class="col-12"><div class="wm-card text-muted">No programs yet. Create “Tehila Zammar” or any project here.</div></div>
            @endforelse
        </div>
    </div>
</section>
@endsection
