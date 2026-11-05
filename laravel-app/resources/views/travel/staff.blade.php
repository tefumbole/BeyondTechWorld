@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Station workspace</h1>
    <p>Choose the desk you need.</p>
</section>
<div class="tiles staff-tiles">
    <a class="tile" href="{{ route('travel.parcels') }}"><span class="symbol">@include('travel.icon', ['icon' => 'box'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Parcels</b><span class="copy">Accept, dispatch and release parcels.</span></a>
    <a class="tile" href="{{ route('travel.counter') }}"><span class="symbol">@include('travel.icon', ['icon' => 'counter'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Counter</b><span class="copy">Sell tickets and record payments.</span></a>
    <a class="tile" href="{{ route('travel.dispatch') }}"><span class="symbol">@include('travel.icon', ['icon' => 'route'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Dispatch</b><span class="copy">Assign buses, drivers and routes.</span></a>
    <a class="tile" href="{{ route('travel.reconcile') }}"><span class="symbol">@include('travel.icon', ['icon' => 'wallet'])</span>@include('travel.icon', ['icon' => 'arrow', 'class' => 'arrow'])<b>Reconciliation</b><span class="copy">Review collections and close the till.</span></a>
</div>
@endsection
