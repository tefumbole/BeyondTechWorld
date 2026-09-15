@extends('layout.main')
@section('content')
@include('wealth.partials.styles')
<section class="forms">
    <div class="container-fluid wm-shell">
        <h1 class="wm-title">{{ $row->title ?: $row->reference }}</h1>
        @include('wealth.partials.nav')
        <div class="wm-card">
            <p><strong>Source:</strong> {{ strtoupper($row->source_type) }} · {{ $row->status }}</p>
            <p><strong>Amount:</strong> {{ number_format($row->amount, 2) }} {{ $row->currency }}</p>
            <p><strong>Date:</strong> {{ $row->occurred_at }}</p>
            <p><strong>Customer:</strong> {{ optional($row->customer)->name ?: '—' }}</p>
            <p><strong>Company:</strong> {{ optional($row->biller)->name ?: '—' }}</p>
            <p><strong>Staff:</strong> {{ optional($row->user)->name ?: '—' }}</p>
            <p><strong>Program:</strong> {{ optional($row->program)->name ?: '—' }}</p>
            <p><strong>Method:</strong> {{ $row->payment_method ?: '—' }}</p>
            <p>{{ $row->description }}</p>
            @if($row->isAutomatic())
                <p class="text-muted">This income comes from a sale or POS payment. Amounts cannot be edited here.</p>
                @if($row->sourceUrl())
                    <a class="wm-btn" href="{{ $row->sourceUrl() }}">View source transaction</a>
                @endif
            @else
                <form method="POST" action="{{ route('wealth.income.update', $row->id) }}">
                    @csrf
                    <div class="row">
                        <div class="col-md-4 mb-2"><label class="wm-label">Title</label><input class="wm-field" name="title" value="{{ $row->title }}"></div>
                        <div class="col-md-2 mb-2"><label class="wm-label">Amount</label><input class="wm-field" name="amount" value="{{ $row->amount }}"></div>
                        <div class="col-md-3 mb-2"><label class="wm-label">Date</label><input class="wm-field" type="date" name="occurred_at" value="{{ \Carbon\Carbon::parse($row->occurred_at)->toDateString() }}"></div>
                        <div class="col-md-3 mb-2"><button class="wm-btn" type="submit">Save</button></div>
                    </div>
                </form>
            @endif
            <a class="wm-btn wm-btn-out" href="{{ route('wealth.income') }}">Back</a>
        </div>
    </div>
</section>
@endsection
