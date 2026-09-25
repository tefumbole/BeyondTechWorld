@extends('beyond.memorial.remember-layout')

@section('title', 'Hymns · Pa Ngwayu Francis')

@section('styles')
        .hymn-hero { margin: 0 0 28px; }
        .hymn-card {
            background: #fffcf7;
            border: 1px solid #e9e5d9;
            box-shadow: 0 9px 29px rgba(49, 58, 46, .03);
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
            color: #b88f4b;
            text-decoration: none;
            display: inline-block;
        }
        .hymn-card[open] summary h2::after { content: " –"; }
        .hymn-body { padding: 0 22px 18px; }
        .hymn-label {
            display: inline-block;
            margin: 0 0 8px;
            color: #b88f4b;
            font-size: 15.6px;
            font-weight: 800;
            letter-spacing: .16em;
            text-transform: uppercase;
        }
        .hymn-also {
            margin: 0 0 18px;
            color: #667066;
            font-size: 18px;
        }
        .hymn-verse {
            margin: 0 0 10px;
            white-space: pre-wrap;
            color: #49544b;
            font-size: 21.6px;
            line-height: 1.7;
        }
        .hymn-chorus {
            margin: 0 0 22px;
            white-space: pre-wrap;
            color: #3c5141;
            font-size: 20.4px;
            line-height: 1.7;
            font-style: italic;
        }
        .hymn-player {
            position: static;
            min-width: 0;
            width: 100%;
            margin: 14px 0 4px;
            background: #f3f0e7;
            border: 1px solid #e6e0d1;
            box-shadow: none;
            color: #29352d;
        }
        .hymn-player .music-title { color: #405444; }
        .hymn-player .music-time { color: #8e7b59; }
        .hymn-player .music-bar { background: rgba(64,84,68,.12); }
        .hymn-player .music-controls button {
            color: #6d613f;
            border-color: #c8ad76;
            background: #eee9db;
        }
        .hymn-chorus strong {
            display: block;
            font-style: normal;
            font-size: 14.4px;
            letter-spacing: .16em;
            text-transform: uppercase;
            margin-bottom: 4px;
            color: #b88f4b;
        }
            color: #b88f4b;
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
            <div class="music-player hymn-player" data-src="{{ asset('public/memorial/pangwayu/audio/when-the-roll-piano.mp3') }}">
                <div class="music-controls">
                    <button type="button" class="hymn-play" aria-label="Play piano">
                        <svg class="icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="icon-pause" viewBox="0 0 24 24" aria-hidden="true" style="display:none"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                    </button>
                </div>
                <div class="music-meta">
                    <span class="music-title">When the Roll Is Called Up Yonder · piano</span>
                    <div class="music-bar"><div class="music-fill"></div></div>
                    <div class="music-time"><span class="music-cur">0:00</span><span class="music-dur">0:00</span></div>
                </div>
            </div>
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
            <div class="music-player hymn-player" data-src="{{ asset('public/memorial/pangwayu/audio/farther-along-piano.mp3') }}">
                <div class="music-controls">
                    <button type="button" class="hymn-play" aria-label="Play piano">
                        <svg class="icon-play" viewBox="0 0 24 24" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                        <svg class="icon-pause" viewBox="0 0 24 24" aria-hidden="true" style="display:none"><path d="M6 5h4v14H6zm8 0h4v14h-4z"/></svg>
                    </button>
                </div>
                <div class="music-meta">
                    <span class="music-title">Farther Along · piano</span>
                    <div class="music-bar"><div class="music-fill"></div></div>
                    <div class="music-time"><span class="music-cur">0:00</span><span class="music-dur">0:00</span></div>
                </div>
            </div>
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

@section('scripts')
<script>
(function () {
    function fmt(t) {
        if (!isFinite(t) || t < 0) return '0:00';
        var m = Math.floor(t / 60);
        var s = Math.floor(t % 60);
        return m + ':' + (s < 10 ? '0' : '') + s;
    }
    var current = null;
    Array.prototype.forEach.call(document.querySelectorAll('.hymn-player'), function (box) {
        var audio = new Audio(box.getAttribute('data-src'));
        audio.preload = 'metadata';
        audio.volume = 0.5;
        var btn = box.querySelector('.hymn-play');
        var play = box.querySelector('.icon-play');
        var pause = box.querySelector('.icon-pause');
        var fill = box.querySelector('.music-fill');
        var bar = box.querySelector('.music-bar');
        var cur = box.querySelector('.music-cur');
        var dur = box.querySelector('.music-dur');
        function ui(on) {
            play.style.display = on ? 'none' : 'block';
            pause.style.display = on ? 'block' : 'none';
            btn.setAttribute('aria-label', on ? 'Pause piano' : 'Play piano');
        }
        function sync() {
            var d = audio.duration || 0;
            var c = audio.currentTime || 0;
            fill.style.width = (d ? Math.min(100, (c / d) * 100) : 0) + '%';
            cur.textContent = fmt(c);
            dur.textContent = fmt(d);
        }
        box.addEventListener('click', function (e) { e.stopPropagation(); });
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (current && current !== audio) { current.pause(); }
            if (audio.paused) {
                current = audio;
                audio.play();
            } else {
                audio.pause();
            }
        });
        bar.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var r = bar.getBoundingClientRect();
            if (audio.duration) audio.currentTime = Math.max(0, Math.min(1, (e.clientX - r.left) / r.width)) * audio.duration;
            sync();
        });
        audio.addEventListener('timeupdate', sync);
        audio.addEventListener('loadedmetadata', sync);
        audio.addEventListener('play', function () { ui(true); });
        audio.addEventListener('pause', function () { ui(false); });
        audio.addEventListener('ended', function () { ui(false); });
    });
})();
</script>
@endsection
