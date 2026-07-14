@php $budget = $event->labourBudget; @endphp
<div class="row">
    <div class="col-lg-5">
        <div class="card">
            <div class="card-header font-weight-bold">Labour budget</div>
            <div class="card-body">
                <form method="POST" action="{{ route('events.labour-budget.save', $event->id) }}">
                    @csrf
                    <div class="form-group">
                        <label>Total event labour budget (XAF)</label>
                        <input type="number" name="total_budget" class="form-control" min="0" required
                               value="{{ old('total_budget', optional($budget)->total_budget ?? 0) }}">
                    </div>
                    <div class="form-group">
                        <label>Distribution mode</label>
                        <select name="distribution_mode" class="form-control">
                            @foreach(['manual' => 'Manual', 'equal' => 'Equally', 'category_weight' => 'By category weight', 'hours' => 'By approved hours', 'days' => 'By approved days'] as $k => $label)
                                <option value="{{ $k }}" {{ optional($budget)->distribution_mode == $k ? 'selected' : '' }}>{{ $label }}</option>
                            @endforeach
                        </select>
                        <small class="text-muted">Auto-distribution logic arrives in Phase 8.</small>
                    </div>
                    <div class="form-group">
                        <label>Notes</label>
                        <textarea name="notes" class="form-control" rows="2">{{ optional($budget)->notes }}</textarea>
                    </div>
                    <div class="form-group">
                        <label>Budget override reason (if over-allocated)</label>
                        <input type="text" name="budget_override_reason" class="form-control" placeholder="Required when allocated exceeds budget">
                    </div>
                    <button type="submit" class="btn btn-primary">Save budget</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card">
            <div class="card-header font-weight-bold">Budget summary</div>
            <div class="card-body">
                <dl class="row mb-0">
                    <dt class="col-sm-6">Total budget</dt>
                    <dd class="col-sm-6">{{ number_format(optional($budget)->total_budget ?? 0) }} XAF</dd>
                    <dt class="col-sm-6">Allocated (assignments)</dt>
                    <dd class="col-sm-6">{{ number_format(optional($budget)->allocated_amount ?? $event->assignments->sum('expected_total')) }} XAF</dd>
                    <dt class="col-sm-6">Remaining</dt>
                    <dd class="col-sm-6">
                        @php $rem = optional($budget)->remaining() ?? 0; @endphp
                        <span class="{{ $rem < 0 ? 'text-danger font-weight-bold' : 'text-success' }}">
                            {{ number_format($rem) }} XAF
                        </span>
                    </dd>
                    <dt class="col-sm-6">Labour mode (event)</dt>
                    <dd class="col-sm-6">{{ $event->labour_mode === 'budget' ? 'Fixed labour budget' : 'Individual rates' }}</dd>
                </dl>
                @if($budget && $budget->variance() > 0)
                    <div class="alert alert-warning mt-3 mb-0">Over budget by {{ number_format($budget->variance()) }} XAF. Record an override reason when saving.</div>
                @endif
            </div>
        </div>
    </div>
</div>
