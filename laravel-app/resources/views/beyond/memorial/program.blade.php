@extends('beyond.memorial.remember-layout')

@section('title', 'Funeral program · Pa Ngwayu Francis')

@section('content')
    <p class="kicker">Order of service</p>
    <h1>Funeral program</h1>
    <p class="meta">Pa Ngwayu Francis · 1952 — 2025 · Aged 73 years<br>Saturday 26 September 2026</p>
    <p class="lead">Cameroon Baptist Convention · Men’s Fellowship<br>Motto: Strong, Firm and Steadfast<br><em>Well done, good and faithful servant — Matthew 25:21</em></p>

    <div class="order" style="margin-top:22px;">
        <p class="verse">Service of thanksgiving</p>
        <h2>At the church</h2>
        <p class="lead" style="margin:0 0 8px;font-size:16px;">Venue and exact hour as announced by the family.</p>
        <ol>
            <li>Prelude and processional</li>
            <li>Opening prayer</li>
            <li>Hymn — see the <a href="{{ route('funeral.pangwayu.hymns') }}" style="color:#f0d57a;">hymns page</a></li>
            <li>Scripture reading</li>
            <li>Biography and tributes</li>
            <li>Eulogies — you may also <a href="{{ route('funeral.pangwayu.remember') }}#eulogies" style="color:#f0d57a;">write one here</a></li>
            <li>Sermon</li>
            <li>Hymn</li>
            <li>Closing prayer and benediction</li>
            <li>Recessional</li>
        </ol>
    </div>

    <div class="order">
        <p class="verse">Committal</p>
        <h2>At the graveside</h2>
        <ol>
            <li>Scripture and committal prayer</li>
            <li>Hymn</li>
            <li>Laying of wreaths, as announced</li>
            <li>Benediction</li>
        </ol>
    </div>

    <div class="order">
        <p class="verse">In his honour</p>
        <p class="lead" style="margin:0;">Pa Ngwayu Francis served with the Cameroon Baptist Convention Men’s Fellowship. The family welcomes your presence, your prayers, and a written eulogy. A quiet gift may be given from the home page without viewing family contribution totals.</p>
    </div>
@endsection
