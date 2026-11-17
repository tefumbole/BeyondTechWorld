@extends('payroll.cm.layout')
@section('payroll')
@foreach($categories as $category)
    @php $parts = json_decode($category->components_json, true) ?: []; @endphp
    <form method="post" action="{{ route('hr.payroll.categories.save') }}" class="card p-3 mb-3">
        @csrf
        <input type="hidden" name="id" value="{{ $category->id }}">
        <div class="form-row">
            <div class="col-md-6"><input name="name" class="form-control" value="{{ $category->name }}" required></div>
            <div class="col-md-3">
                <select name="is_active" class="form-control">
                    <option value="1" {{ $category->is_active ? 'selected' : '' }}>Active</option>
                    <option value="0" {{ $category->is_active ? '' : 'selected' }}>Inactive</option>
                </select>
            </div>
            <div class="col-md-3"><button class="btn btn-primary">Save</button></div>
        </div>
        <table class="table table-sm mt-2">
            <thead><tr><th>Component</th><th>French</th><th>%</th><th>CNPS</th><th>IRPP</th></tr></thead>
            <tbody>
            @foreach($parts as $part)
                <tr>
                    <td><input type="hidden" name="code[]" value="{{ $part['code'] }}"><input name="label[]" class="form-control" value="{{ $part['label'] }}"></td>
                    <td><input name="label_fr[]" class="form-control" value="{{ $part['label_fr'] ?? $part['label'] }}"></td>
                    <td><input name="percent[]" class="form-control" value="{{ $part['percent'] }}"></td>
                    <td><input type="checkbox" name="cnps[{{ $loop->index }}]" value="1" {{ !empty($part['cnps']) ? 'checked' : '' }}></td>
                    <td><input type="checkbox" name="irpp[{{ $loop->index }}]" value="1" {{ !empty($part['irpp']) ? 'checked' : '' }}></td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </form>
@endforeach
<form method="post" action="{{ route('hr.payroll.categories.save') }}" class="card p-3">
    @csrf
    <h5>New category</h5>
    <input name="name" class="form-control mb-2" placeholder="Category name" required>
    <input type="hidden" name="is_active" value="1">
    <input type="hidden" name="code[]" value="basic"><input type="hidden" name="label_fr[]" value="Salaire de base">
    <div class="form-row mb-2"><div class="col-md-6"><input name="label[]" class="form-control" value="Basic salary"></div><div class="col-md-3"><input name="percent[]" class="form-control" value="70"></div></div>
    <input type="hidden" name="cnps[0]" value="1"><input type="hidden" name="irpp[0]" value="1">
    <button class="btn btn-secondary">Add category</button>
</form>
@endsection
