@extends('nbc.layout')
@section('title', 'Quotations · Praise Team')
@section('content')
    <p class="kicker">Quotations</p>
    <h1>Quotations</h1>
    <form class="card" method="POST" action="{{ route('nbc.quotations.store') }}">
        @csrf
        <label for="client_name">Client</label>
        <input id="client_name" name="client_name" required>
        <label for="amount">Amount</label>
        <input id="amount" name="amount" type="number" min="0" step="0.01">
        <label for="body">Details</label>
        <textarea id="body" name="body"></textarea>
        <button type="submit">Save quotation</button>
    </form>
    @foreach($quotations as $quote)
        <div class="card">
            <strong>{{ $quote->number }} · {{ $quote->client_name }}</strong>
            <div class="sub">{{ number_format($quote->amount, 0) }} · {{ $quote->status }}</div>
            <div>{{ $quote->body }}</div>
        </div>
    @endforeach
@endsection
