@extends('beyond.memorial.remember-layout')

@section('title', 'Hymns · Pa Ngwayu Francis')

@section('styles')
        .hymn-hero { margin: 0 0 28px; }
        .hymn-card {
            background: linear-gradient(180deg, #221a12, #16110c);
            border: 1px solid #4a3b1c;
            border-radius: 22px;
            margin-bottom: 14px;
            overflow: hidden;
        }
        .hymn-card summary {
            list-style: none;
            cursor: pointer;
            padding: 18px 22px;
            display: block;
        }
        .hymn-card summary::-webkit-details-marker { display: none; }
        .hymn-card summary h2 {
            margin: 0;
            font-size: clamp(26.4px, 3.6vw, 38.4px);
            text-decoration: underline;
            text-underline-offset: 5px;
        }
        .hymn-card summary h2::after {
            content: " +";
            color: var(--gold);
            text-decoration: none;
            display: inline-block;
        }
        .hymn-card[open] summary h2::after { content: " –"; }
        .hymn-body { padding: 0 22px 18px; }
        .hymn-label {
            display: inline-block;
            margin: 0 0 8px;
            color: var(--gold);
            font-size: 15.6px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .hymn-also {
            margin: 0 0 18px;
            color: var(--muted);
            font-size: 18px;
        }
        .hymn-verse {
            margin: 0 0 10px;
            white-space: pre-wrap;
            color: #e8dcc0;
            font-size: 21.6px;
            line-height: 1.7;
        }
        .hymn-chorus {
            margin: 0 0 22px;
            white-space: pre-wrap;
            color: var(--gold-2);
            font-size: 20.4px;
            line-height: 1.7;
            font-style: italic;
        }
        .hymn-chorus strong {
            display: block;
            font-style: normal;
            font-size: 14.4px;
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

    <details class="hymn-card">
        <summary>
            <span class="hymn-label">Opening hymn</span>
            <h2>When the Trumpet of the Lord Shall Sound</h2>
        </summary>
        <div class="hymn-body">
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
        </div>
    </details>

    <details class="hymn-card">
        <summary>
            <span class="hymn-label">Closing hymn</span>
            <h2>Farther Along</h2>
        </summary>
        <div class="hymn-body">

        <p class="hymn-verse">Tempted and tried, we’re oft made to wonder
Why it should be thus all the day long;
While there are others living about us,
Never molested, though in the wrong.</p>
        <p class="hymn-chorus"><strong>Refrain</strong>Farther along we’ll know more about it,
Farther along we’ll understand why;
Cheer up, my brother, live in the sunshine,
We’ll understand it all by and by.</p>

        <p class="hymn-verse">Sometimes I wonder why I must suffer,
Go in the rain, the cold, and the snow,
When there are many living in comfort,
Giving no heed to all I can do.</p>
        <p class="hymn-chorus"><strong>Refrain</strong>Farther along we’ll know more about it,
Farther along we’ll understand why;
Cheer up, my brother, live in the sunshine,
We’ll understand it all by and by.</p>

        <p class="hymn-verse">Tempted and tried, how often we question
Why we must suffer year after year,
Being accused by those of our loved ones,
E’en though we’ve walked in God’s holy fear.</p>
        <p class="hymn-chorus"><strong>Refrain</strong>Farther along we’ll know more about it,
Farther along we’ll understand why;
Cheer up, my brother, live in the sunshine,
We’ll understand it all by and by.</p>

        <p class="hymn-verse">Often when death has taken our loved ones,
Leaving our home so lone and so drear,
Then do we wonder why others prosper,
Living so wicked year after year.</p>
        <p class="hymn-chorus"><strong>Refrain</strong>Farther along we’ll know more about it,
Farther along we’ll understand why;
Cheer up, my brother, live in the sunshine,
We’ll understand it all by and by.</p>

        <p class="hymn-verse">Faithful till death, saith our loving Master;
Short is our time to labor and wait;
Then will our toiling seem to be nothing,
When we shall pass the heavenly gate.</p>
        <p class="hymn-chorus"><strong>Refrain</strong>Farther along we’ll know more about it,
Farther along we’ll understand why;
Cheer up, my brother, live in the sunshine,
We’ll understand it all by and by.</p>

        <p class="hymn-verse">Soon we will see our dear, loving Savior,
Hear the last trumpet sound through the sky;
Then we will meet those gone on before us,
Then we shall know and understand why.</p>
        <p class="hymn-chorus"><strong>Refrain</strong>Farther along we’ll know more about it,
Farther along we’ll understand why;
Cheer up, my brother, live in the sunshine,
We’ll understand it all by and by.</p>
        </div>
    </details>
@endsection
