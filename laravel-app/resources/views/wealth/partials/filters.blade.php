@php $filter = $filter ?? null; @endphp
<form method="GET" class="wm-card">
    <div class="row">
        <div class="col-md-2 mb-2">
            <label class="wm-label">Financial entity</label>
            <select name="entity_type" class="wm-field">
                @foreach(['all'=>'All','company'=>'Company','personal'=>'Personal','staff'=>'Staff','program'=>'Program'] as $k=>$v)
                    <option value="{{ $k }}" @if(optional($filter)->entityType===$k) selected @endif>{{ $v }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">Company (Biller)</label>
            <select name="biller_id" class="wm-field">
                <option value="">All</option>
                @foreach($billers as $b)
                    <option value="{{ $b->id }}" @if(optional($filter)->billerId==$b->id) selected @endif>{{ $b->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">User</label>
            <select name="user_id" class="wm-field">
                <option value="">All</option>
                @foreach($users as $u)
                    <option value="{{ $u->id }}" @if(optional($filter)->userId==$u->id) selected @endif>{{ $u->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">Staff</label>
            <select name="employee_id" class="wm-field">
                <option value="">All</option>
                @foreach($employees as $e)
                    <option value="{{ $e->id }}" @if(optional($filter)->employeeId==$e->id) selected @endif>{{ $e->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">Program</label>
            <select name="program_id" class="wm-field">
                <option value="">All</option>
                @foreach($programs as $p)
                    <option value="{{ $p->id }}" @if(optional($filter)->programId==$p->id) selected @endif>{{ $p->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">Month</label>
            <select name="month" class="wm-field">
                <option value="">—</option>
                @for($m=1;$m<=12;$m++)
                    <option value="{{ $m }}" @if((int)optional($filter)->month===$m) selected @endif>{{ date('F', mktime(0,0,0,$m,1)) }}</option>
                @endfor
            </select>
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">Year</label>
            <input type="number" name="year" class="wm-field" value="{{ optional($filter)->year }}" placeholder="{{ date('Y') }}">
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">From</label>
            <input type="date" name="start_date" class="wm-field" value="{{ optional($filter)->startDate }}">
        </div>
        <div class="col-md-2 mb-2">
            <label class="wm-label">To</label>
            <input type="date" name="end_date" class="wm-field" value="{{ optional($filter)->endDate }}">
        </div>
        <div class="col-md-2 mb-2 d-flex align-items-end">
            <button class="wm-btn" type="submit">Apply filters</button>
        </div>
    </div>
</form>
