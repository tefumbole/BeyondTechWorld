{{--
  Usage: @include('contracts.partials.person_picker', [
    'prefix' => 'party_a',   // form field prefix
    'label' => 'Party A',
    'roleDefault' => 'Employer',
    'requiredName' => false,
    'showRole' => true,
  ])
--}}
@php
    $prefix = $prefix ?? 'party';
    $label = $label ?? 'Person';
    $roleDefault = $roleDefault ?? '';
    $requiredName = ! empty($requiredName);
    $showRole = $showRole ?? true;
    $roleField = $roleField ?? ($prefix.'_role');
@endphp
<div class="ct-person-picker" data-prefix="{{ $prefix }}">
    @if($showRole)
        <div class="mb-3">
            <label class="ct-label">Role label</label>
            <input type="text" name="{{ $roleField }}" class="ct-field" value="{{ old($roleField, $roleDefault) }}">
        </div>
    @endif

    <div class="d-flex flex-wrap mb-2" style="gap:8px;align-items:center;">
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-primary ct-pp-filter active" data-filter="all">All</button>
            <button type="button" class="btn btn-outline-primary ct-pp-filter" data-filter="customers">Customers</button>
            <button type="button" class="btn btn-outline-primary ct-pp-filter" data-filter="staff">Users / Staff</button>
        </div>
        <button type="button" class="btn btn-sm btn-primary ct-pp-create" data-toggle="modal" data-target="#ct-add-customer-modal" data-prefix="{{ $prefix }}">
            <i class="dripicons-plus"></i> Create customer
        </button>
    </div>

    <div class="mb-2">
        <input type="search" class="ct-field ct-pp-search" placeholder="Search name, phone, email, company…">
    </div>
    <div class="ct-pp-list border rounded mb-3" style="max-height:220px;overflow:auto;background:#f8fbff;"></div>

    <div class="ct-pp-selected alert alert-light border mb-3 d-none">
        <strong>Selected:</strong> <span class="ct-pp-selected-label"></span>
        <button type="button" class="btn btn-link btn-sm ct-pp-clear">Clear</button>
    </div>

    <div class="row">
        <div class="col-md-6 mb-2">
            <label class="ct-label">Name @if($requiredName)*@endif</label>
            <input type="text" name="{{ $prefix }}[name]" class="ct-field ct-pp-name" @if($requiredName) required @endif value="{{ old($prefix.'.name') }}">
        </div>
        <div class="col-md-6 mb-2">
            <label class="ct-label">Organization / Company</label>
            <input type="text" name="{{ $prefix }}[organization]" class="ct-field ct-pp-org" value="{{ old($prefix.'.organization') }}">
        </div>
        <div class="col-md-6 mb-2">
            <label class="ct-label">Email</label>
            <input type="email" name="{{ $prefix }}[email]" class="ct-field ct-pp-email" value="{{ old($prefix.'.email') }}">
        </div>
        <div class="col-md-6 mb-2">
            <label class="ct-label">Phone</label>
            <input type="text" name="{{ $prefix }}[phone]" class="ct-field ct-pp-phone phone-sanitize" value="{{ old($prefix.'.phone') }}">
        </div>
        <div class="col-md-12 mb-2">
            <label class="ct-label">Address</label>
            <textarea name="{{ $prefix }}[address]" class="ct-field ct-pp-address" rows="2">{{ old($prefix.'.address') }}</textarea>
        </div>
        <input type="hidden" name="{{ $prefix }}[subject_type]" class="ct-pp-stype" value="{{ old($prefix.'.subject_type') }}">
        <input type="hidden" name="{{ $prefix }}[subject_id]" class="ct-pp-sid" value="{{ old($prefix.'.subject_id') }}">
    </div>
</div>
