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
            font-size: clamp(26px, 4vw, 36px);
            margin: 0 0 6px;
        }
        .hymn-also {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 15px;
        }
        .hymn-verse {
            margin: 0 0 10px;
            white-space: pre-wrap;
            color: #e8dcc0;
            font-size: 18px;
            line-height: 1.7;
        }
        .hymn-chorus {
            margin: 0 0 22px;
            white-space: pre-wrap;
            color: var(--gold-2);
            font-size: 17px;
            line-height: 1.7;
            font-style: italic;
        }
        .hymn-chorus strong {
            display: block;
            font-style: normal;
            font-size: 12px;
            letter-spacing: .16em;
            text-transform: uppercase;
            margin-bottom: 4px;
            color: var(--gold);
        }
@endsection

@section('content')
    <header class="hymn-hero">
        <p class="kicker">Nkwen Baptist Church</p>
        <h1>Hymns</h1>
    </header>

    <article class="hymn-card">
        <span class="hymn-label">Opening hymn</span>
        <h2>When the Trumpet of the Lord Shall Sound</h2>
        <p class="hymn-also">When the Roll Is Called Up Yonder</p>

        <p class="hymn-verse">When the trumpet of the Lord shall sound, and time shall be no more,
And the morning breaks, eternal, bright and fair;
When the saved of earth shall gather over on the other shore,
And the roll is called up yonder, I’ll be there.</p>
        <p class="hymn-chorus"><strong>Chorus</strong>When the roll is called up yonder,
When the roll is called up yonder,
When the roll is called up yonder,
When the roll is called up yonder, I’ll be there.</p>

        <p class="hymn-verse">On that bright and cloudless morning when the dead in Christ shall rise,
And the glory of His resurrection share;
When His chosen ones shall gather to their home beyond the skies,
And the roll is called up yonder, I’ll be there.</p>
        <p class="hymn-chorus"><strong>Chorus</strong>When the roll is called up yonder,
When the roll is called up yonder,
When the roll is called up yonder,
When the roll is called up yonder, I’ll be there.</p>

        <p class="hymn-verse">Let us labor for the Master from the dawn till setting sun,
Let us talk of all His wondrous love and care;
Then when all of life is over, and our work on earth is done,
And the roll is called up yonder, I’ll be there.</p>
        <p class="hymn-chorus"><strong>Chorus</strong>When the roll is called up yonder,
When the roll is called up yonder,
When the roll is called up yonder,
When the roll is called up yonder, I’ll be there.</p>
    </article>

    <article class="hymn-card">
        <span class="hymn-label">Closing hymn</span>
        <h2>It Is Well With My Soul</h2>

        <p class="hymn-verse">When peace, like a river, attendeth my way,
When sorrows like sea billows roll;
Whatever my lot, Thou hast taught me to say,
It is well, it is well with my soul.</p>
        <p class="hymn-chorus"><strong>Chorus</strong>It is well with my soul,
It is well, it is well with my soul.</p>

        <p class="hymn-verse">Though Satan should buffet, though trials should come,
Let this blest assurance control,
That Christ hath regarded my helpless estate,
And hath shed His own blood for my soul.</p>
        <p class="hymn-chorus"><strong>Chorus</strong>It is well with my soul,
It is well, it is well with my soul.</p>

        <p class="hymn-verse">My sin—oh, the bliss of this glorious thought—
My sin, not in part but the whole,
Is nailed to the cross, and I bear it no more,
Praise the Lord, praise the Lord, O my soul!</p>
        <p class="hymn-chorus"><strong>Chorus</strong>It is well with my soul,
It is well, it is well with my soul.</p>

        <p class="hymn-verse">And Lord, haste the day when the faith shall be sight,
The clouds be rolled back as a scroll;
The trump shall resound, and the Lord shall descend,
Even so, it is well with my soul.</p>
        <p class="hymn-chorus"><strong>Chorus</strong>It is well with my soul,
It is well, it is well with my soul.</p>
    </article>
@endsection
