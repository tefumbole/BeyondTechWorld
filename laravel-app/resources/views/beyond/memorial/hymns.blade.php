@extends('beyond.memorial.remember-layout')

@section('title', 'Hymns · Pa Ngwayu Francis')

@section('styles')
        .hymn-hero { margin: 0 0 28px; }
        .hymn-card {
            background: linear-gradient(180deg, #221a12, #16110c);
            border: 1px solid #4a3b1c;
            border-radius: 22px;
            padding: 22px 22px 20px;
            box-shadow: 0 18px 40px rgba(0,0,0,.28);
            margin-bottom: 18px;
        }
        .hymn-label {
            display: inline-block;
            margin: 0 0 8px;
            color: var(--gold);
            font-size: 13px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .hymn-card h2 {
            font-size: clamp(28px, 4vw, 38px);
            margin: 0 0 14px;
        }
        .hymn-card p {
            margin: 0;
            white-space: pre-wrap;
            color: #e8dcc0;
            font-size: 18px;
            line-height: 1.7;
        }
@endsection

@section('content')
    <header class="hymn-hero">
        <p class="kicker">Nkwen Baptist Church</p>
        <h1>Hymns</h1>
    </header>

    <article class="hymn-card">
        <span class="hymn-label">Opening hymn</span>
        <h2>Amazing Grace</h2>
        <p>Amazing grace! How sweet the sound
That saved a wretch like me.
I once was lost, but now am found;
Was blind, but now I see.

’Twas grace that taught my heart to fear,
And grace my fears relieved.
How precious did that grace appear
The hour I first believed.

When we’ve been there ten thousand years,
Bright shining as the sun,
We’ve no less days to sing God’s praise
Than when we’d first begun.</p>
    </article>

    <article class="hymn-card">
        <span class="hymn-label">Hymn</span>
        <h2>It Is Well With My Soul</h2>
        <p>When peace, like a river, attendeth my way,
When sorrows like sea billows roll;
Whatever my lot, Thou hast taught me to say,
It is well, it is well with my soul.

It is well with my soul,
It is well, it is well with my soul.

And Lord, haste the day when the faith shall be sight,
The clouds be rolled back as a scroll;
The trump shall resound, and the Lord shall descend,
Even so, it is well with my soul.</p>
    </article>
@endsection
