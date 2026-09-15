@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">{{ $program->name }}</h1>
        @include('wealth.partials.nav')
        @include('wealth.partials.filters')

        <div class="wm-kpis mb-3">
            <div class="wm-kpi"><div class="lbl">Income</div><div class="val">{{ number_format($finance['income'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Expenses</div><div class="val">{{ number_format($finance['expenses'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Balance</div><div class="val">{{ number_format($finance['balance'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Budget</div><div class="val">{{ number_format($finance['budget'], 2) }}</div></div>
            <div class="wm-kpi"><div class="lbl">Spent</div><div class="val">{{ $finance['spent_pct'] }}%</div></div>
            <div class="wm-kpi"><div class="lbl">Remaining</div><div class="val">{{ $finance['remain_pct'] }}%</div></div>
        </div>

        <div class="wm-card">
            <h5>Budget lines</h5>
            <table class="table wm-table">
                <thead><tr><th>Line</th><th>Budget</th><th>Actual</th><th>Variance</th><th>%</th><th>Status</th><th></th></tr></thead>
                <tbody>
                @foreach($finance['lines'] as $line)
                    <tr>
                        <td>{{ $line['line']->name }}</td>
                        <td>{{ number_format($line['line']->budget_amount, 2) }}</td>
                        <td>{{ number_format($line['actual'], 2) }}</td>
                        <td>{{ number_format($line['variance'], 2) }}</td>
                        <td>{{ $line['used_pct'] }}%</td>
                        <td>{{ $line['status'] }}</td>
                        <td>
                            <form method="POST" action="{{ route('wealth.programs.budget.destroy', $line['line']->id) }}" onsubmit="return confirm('Remove this line?');">
                                @csrf
                                <button class="btn btn-link btn-sm text-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
            <form method="POST" action="{{ route('wealth.programs.budget.store', $program->id) }}" class="row">
                @csrf
                <div class="col-md-3 mb-2"><input class="wm-field" name="name" placeholder="e.g. Sound" required></div>
                <div class="col-md-2 mb-2"><input class="wm-field" name="budget_amount" type="number" step="0.01" placeholder="Amount" required></div>
                <div class="col-md-3 mb-2">
                    <select name="subcategory_id" class="wm-field">
                        <option value="">Subcategory (optional)</option>
                        @foreach($subcategories as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach
                    </select>
                </div>
                <div class="col-md-2 mb-2"><input class="wm-field" name="warn_percent" type="number" value="85" placeholder="Warn %"></div>
                <div class="col-md-2 mb-2"><button class="wm-btn" type="submit">Add line</button></div>
            </form>
        </div>

        <div class="wm-card">
            <form method="POST" action="{{ route('wealth.programs.update', $program->id) }}">
                @csrf
                <div class="row">
                    <div class="col-md-3 mb-2"><label class="wm-label">Name</label><input class="wm-field" name="name" value="{{ $program->name }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Code</label><input class="wm-field" name="code" value="{{ $program->code }}"></div>
                    <div class="col-md-2 mb-2">
                        <label class="wm-label">Status</label>
                        <select name="status" class="wm-field">
                            @foreach(['planning','active','completed','cancelled'] as $st)
                                <option value="{{ $st }}" @if($program->status===$st) selected @endif>{{ $st }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Budget</label><input class="wm-field" name="proposed_budget" value="{{ $program->proposed_budget }}"></div>
                    <div class="col-md-2 mb-2"><label class="wm-label">Expected income</label><input class="wm-field" name="expected_income" value="{{ $program->expected_income }}"></div>
                    <div class="col-md-12"><button class="wm-btn" type="submit">Save program</button>
                        <a class="wm-btn wm-btn-out" href="{{ route('wealth.reports', ['program_id' => $program->id]) }}">Generate report</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</section>
@endsection
