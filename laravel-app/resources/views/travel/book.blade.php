@extends('travel.layout')
@section('content')
<section class="hero">
    <small>Epic Travels · Cameroon</small>
    <h1>Your next stop starts here.</h1>
    <p>Book a seat, choose your departure and travel across Cameroon.</p>
</section>
<form class="panel search-card" method="get" action="{{ route('travel.book') }}">
    <label>From
        <div class="combo">
            <input class="combo-input" type="text" placeholder="Search a station" autocomplete="off" role="combobox" value="{{ $from }}">
            <input type="hidden" name="from" value="{{ $from }}">
            <div class="combo-list" hidden></div>
        </div>
    </label>
    <label>To
        <div class="combo">
            <input class="combo-input" type="text" placeholder="Search a station" autocomplete="off" role="combobox" value="{{ $to }}">
            <input type="hidden" name="to" value="{{ $to }}">
            <div class="combo-list" hidden></div>
        </div>
    </label>
    <label>Travel date
        <input type="date" name="date" required value="{{ $date }}">
    </label>
    <button class="primary">Find buses</button>
</form>
<div>
    @forelse($trips as $trip)
        <article class="trip">
            <div>
                <span class="tag">{{ $trip->class_label }} · {{ ucfirst($trip->shift) }}</span>
                <h3>{{ $trip->depart_local }}</h3>
                <p class="route">{{ $from }} → {{ $to }}</p>
                <p class="muted">
                    @if($trip->duration_label){{ $trip->duration_label }}@endif
                    @if($trip->via) · Stops at {{ $trip->via }}@endif
                    · {{ \App\Travel\TravelClock::passengerLine($trip) }}
                </p>
            </div>
            <div class="side">
                <p class="price">{{ \App\Travel\TravelCatalog::money($trip->fare_xaf) }}</p>
                <p class="muted">{{ $trip->available }} seats left</p>
                <a class="primary" style="text-decoration:none;border-radius:10px;padding:11px 16px" href="{{ route('travel.departure', $trip->id) }}">Choose seat</a>
            </div>
        </article>
    @empty
        <p class="notice">{{ $from === $to ? 'Choose different stations.' : 'No departures for this route and date.' }}</p>
    @endforelse
</div>
<script type="application/json" id="station-data">@json($stationChoices)</script>
<script>
(function () {
    var stations = [];
    try { stations = JSON.parse(document.getElementById('station-data').textContent); } catch (e) { stations = []; }
    document.querySelectorAll('.combo').forEach(function (root) {
        var hidden = root.querySelector('input[type=hidden]');
        var box = root.querySelector('.combo-input');
        var list = root.querySelector('.combo-list');
        if (hidden.value) root.setAttribute('data-picked', hidden.value);
        function paint(query) {
            var q = query.trim().toLowerCase();
            var groups = [
                {label: '', items: stations.filter(function (station) { return station.region !== 'South West'; })},
                {label: 'South West', items: stations.filter(function (station) { return station.region === 'South West'; })}
            ];
            var html = '';
            groups.forEach(function (group) {
                var items = group.items.filter(function (station) {
                    return q === '' || station.name.toLowerCase().indexOf(q) !== -1 || group.label.toLowerCase().indexOf(q) !== -1;
                });
                if (!items.length) return;
                if (group.label) html += '<div class="combo-group">' + group.label + '</div>';
                items.forEach(function (station) {
                    html += '<button type="button" class="combo-option">' + station.name + '</button>';
                });
            });
            list.innerHTML = html || '<div class="combo-empty">No station matches.</div>';
            list.hidden = false;
        }
        box.addEventListener('focus', function () { paint(box.value === root.getAttribute('data-picked') ? '' : box.value); });
        box.addEventListener('input', function () {
            hidden.value = '';
            root.removeAttribute('data-picked');
            paint(box.value);
        });
        list.addEventListener('click', function (event) {
            var choice = event.target.closest('.combo-option');
            if (!choice) return;
            hidden.value = choice.textContent;
            box.value = choice.textContent;
            root.setAttribute('data-picked', choice.textContent);
            list.hidden = true;
        });
        document.addEventListener('click', function (event) {
            if (!root.contains(event.target)) list.hidden = true;
        });
    });
    var form = document.querySelector('.search-card');
    if (form) {
        form.addEventListener('submit', function (event) {
            if (!form.from.value || !form.to.value) event.preventDefault();
        });
    }
})();
</script>
@endsection
