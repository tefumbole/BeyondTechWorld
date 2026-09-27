@extends('layout.main')

@section('content')
<section class="forms">
    <div class="container-fluid">
        <h1>Event Packages & Pricing</h1>
        <p class="text-muted">Configurable commercial packages for Mbole AI (MAI). Changes here apply without code edits.</p>

        @if(session('message'))
            <div class="alert alert-success">{{ session('message') }}</div>
        @endif

        <div class="card mb-4">
            <div class="card-header">Pricing rules</div>
            <div class="card-body table-responsive">
                <table class="table table-sm">
                    <thead><tr><th>Key</th><th>Label</th><th>Amount (CFA)</th><th>Unit</th><th>Active</th><th></th></tr></thead>
                    <tbody>
                    @foreach($rules as $rule)
                        <tr>
                            <form method="post" action="{{ route('event-packages.rules.update', $rule->id) }}">
                                @csrf
                                <td><code>{{ $rule->key }}</code></td>
                                <td><input class="form-control form-control-sm" name="label" value="{{ $rule->label }}"></td>
                                <td><input class="form-control form-control-sm" name="amount" type="number" step="1" value="{{ $rule->amount }}"></td>
                                <td>{{ $rule->unit }}</td>
                                <td><input type="checkbox" name="active" value="1" {{ $rule->active ? 'checked' : '' }}></td>
                                <td><button class="btn btn-sm btn-primary">Save</button></td>
                            </form>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header">Packages</div>
            <div class="card-body">
                @foreach($packages as $pkg)
                    <div class="border rounded p-3 mb-3">
                        <form method="post" action="{{ route('event-packages.update', $pkg->id) }}" class="mb-2">
                            @csrf
                            <div class="row">
                                <div class="col-md-2"><strong>{{ $pkg->category }} / {{ $pkg->code }}</strong></div>
                                <div class="col-md-3"><input class="form-control form-control-sm" name="name" value="{{ $pkg->name }}"></div>
                                <div class="col-md-2"><input class="form-control form-control-sm" name="base_price" type="number" step="1" value="{{ $pkg->base_price }}"></div>
                                <div class="col-md-1"><input class="form-control form-control-sm" name="icon" value="{{ $pkg->icon }}"></div>
                                <div class="col-md-1"><input type="checkbox" name="active" value="1" {{ $pkg->active ? 'checked' : '' }}> Active</div>
                                <div class="col-md-2"><button class="btn btn-sm btn-primary">Save package</button></div>
                            </div>
                            <textarea class="form-control form-control-sm mt-2" name="description" rows="2">{{ $pkg->description }}</textarea>
                        </form>
                        <div class="small text-muted mb-1">Components (inventory mapping)</div>
                        <ul class="mb-2">
                            @foreach($pkg->components as $c)
                                <li>{{ $c->category_key }} × {{ $c->qty }} — query “{{ $c->search_query }}” @if($c->product_id) (product #{{ $c->product_id }}) @endif</li>
                            @endforeach
                        </ul>
                        <form method="post" action="{{ route('event-packages.components.store', $pkg->id) }}" class="form-inline">
                            @csrf
                            <input class="form-control form-control-sm mr-1" name="category_key" placeholder="SPEAKER" required>
                            <input class="form-control form-control-sm mr-1" name="search_query" placeholder="speaker">
                            <input class="form-control form-control-sm mr-1" name="qty" type="number" value="1" min="1" style="width:70px">
                            <input class="form-control form-control-sm mr-1" name="product_id" type="number" placeholder="product id" style="width:100px">
                            <button class="btn btn-sm btn-outline-secondary">Add component</button>
                        </form>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</section>
@endsection
