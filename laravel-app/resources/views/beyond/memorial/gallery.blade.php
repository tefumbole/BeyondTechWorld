@extends('beyond.memorial.remember-layout')

@section('title', 'Gallery · Pa Ngwayu Francis')

@section('styles')
        .gallery-lead { margin: 0 0 22px; max-width: 36em; }
        .gallery-grid {
            columns: 3;
            column-gap: 14px;
        }
        .gallery-grid button {
            display: block;
            width: 100%;
            margin: 0 0 14px;
            padding: 0;
            border: 1px solid #e9e5d9;
            overflow: hidden;
            background: #fffcf7;
            cursor: zoom-in;
            break-inside: avoid;
        }
        .gallery-grid img {
            width: 100%;
            height: auto;
            display: block;
        }
        .lightbox {
            position: fixed;
            inset: 0;
            z-index: 80;
            display: none;
            align-items: center;
            justify-content: center;
            background: rgba(5,4,3,.92);
            padding: 24px;
        }
        .lightbox.open { display: flex; }
        .lightbox img {
            max-width: min(1100px, 100%);
            max-height: calc(100vh - 48px);
            width: auto;
            height: auto;
            object-fit: contain;
            border-radius: 8px;
        }
        .lightbox .close {
            position: absolute;
            top: 16px;
            right: 16px;
            border: 1px solid var(--gold);
            background: #14100c;
            color: #fff8e8;
            border-radius: 999px;
            padding: 10px 16px;
            font-weight: 700;
            cursor: pointer;
        }
        @media (max-width: 900px) {
            .gallery-grid { columns: 2; }
        }
        @media (max-width: 560px) {
            .gallery-grid { columns: 1; }
        }
@endsection

@section('content')
    <header class="gallery-lead">
        <p class="kicker">Photographs</p>
        <h1>Gallery</h1>
        <p class="lead">A life in pictures. Tap any photograph to see it larger.</p>
    </header>
    <div class="gallery-grid">
        @foreach($gallery as $src)
            <button type="button" data-full="{{ $src }}" aria-label="Open photograph">
                <img src="{{ $src }}" alt="Pa Ngwayu Francis">
            </button>
        @endforeach
    </div>
    <div class="lightbox" id="galleryLight" hidden>
        <button type="button" class="close">Close</button>
        <img alt="Pa Ngwayu Francis">
    </div>
@endsection

@section('scripts')
<script>
(function () {
    var box = document.getElementById('galleryLight');
    if (!box) return;
    var img = box.querySelector('img');
    document.querySelectorAll('.gallery-grid button').forEach(function (btn) {
        btn.addEventListener('click', function () {
            img.src = btn.getAttribute('data-full');
            box.hidden = false;
            box.classList.add('open');
        });
    });
    function close() {
        box.classList.remove('open');
        box.hidden = true;
        img.removeAttribute('src');
    }
    box.querySelector('.close').addEventListener('click', close);
    box.addEventListener('click', function (e) {
        if (e.target === box) close();
    });
})();
</script>
@endsection
