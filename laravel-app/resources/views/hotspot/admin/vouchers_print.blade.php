@extends('layout.main')
@section('content')
<section class="forms"><div class="container-fluid">
    <h3>Voucher batch {{ $batch->id }}</h3>
    <p>{{ optional($package)->name }} · {{ \App\Hotspot\HotspotLimits::humanDuration(optional($package)->validity_seconds) }}</p>
    <button class="btn btn-primary mb-3" onclick="window.print()">Print</button>
    <div class="row">
        @foreach($rows as $row)
            <div class="col-md-3 mb-3"><div class="border p-3 text-center"><div>{{ optional($package)->name }}</div><strong style="font-size:1.3rem;letter-spacing:.08em">{{ $row->code }}</strong></div></div>
        @endforeach
    </div>
</div></section>
@endsection
