@extends('beyond.memorial.remember-layout')

@section('title', 'Funeral program · Pa Ngwayu Francis')

@section('styles')
        .program-hero { margin: 0 0 28px; }
        .program-hero h1 { margin-bottom: 10px; }
        .program-meta {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin: 0 0 8px;
        }
        .chip {
            display: inline-flex;
            align-items: center;
            border: 1px solid #e9e5d9;
            background: #fffcf7;
            color: #8e7b59;
            border-radius: 999px;
            padding: 6px 12px;
            font-size: 13px;
            font-weight: 700;
        }
        .timeline {
            position: relative;
            display: grid;
            gap: 22px;
            padding-left: 8px;
        }
        .timeline:before {
            content: "";
            position: absolute;
            left: 27px;
            top: 28px;
            bottom: 28px;
            width: 2px;
            background: #d7d4c8;
        }
        .part {
            position: relative;
            display: grid;
            grid-template-columns: 56px 1fr;
            gap: 16px;
            align-items: start;
        }
        .part-num {
            width: 56px;
            height: 56px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: "Cormorant Garamond", serif;
            font-size: 22px;
            font-weight: 700;
            color: #6d613f;
            background: #eee9db;
            border: 1px solid #c8ad76;
            box-shadow: none;
            z-index: 1;
        }
        .part-card {
            background: #fffcf7;
            border: 1px solid #e9e5d9;
            padding: 22px 22px 20px;
            box-shadow: 0 9px 29px rgba(49, 58, 46, .03);
        }
        .part-card h2 {
            font-size: clamp(26px, 4vw, 34px);
            margin: 0 0 8px;
        }
        .part-time {
            display: inline-block;
            margin: 0 0 10px;
            color: #b88f4b;
            font-size: 15px;
            font-weight: 800;
            letter-spacing: .08em;
            text-transform: uppercase;
        }
        .part-card p {
            margin: 0;
            color: #49544b;
            font-size: 18px;
            line-height: 1.55;
        }
        .venue {
            margin-top: 12px;
            color: #405444;
            font-weight: 700;
            font-size: 15px;
        }
        .service-list {
            margin: 18px 0 0;
            display: grid;
            gap: 8px;
        }
        .service-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 12px;
            border-radius: 14px;
            background: #f3f2eb;
            border: 1px solid #e8e3d8;
            color: #29352d;
            font-size: 16px;
        }
        .service-row b {
            flex: 0 0 28px;
            width: 28px;
            height: 28px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #eee9db;
            color: #6d613f;
            font-size: 12px;
        }
        .service-row span {
            flex: 1;
            min-width: 0;
            line-height: 1.45;
        }
        .service-row a { color: #405444; }
        .program-quote {
            margin: 16px 0 0;
            color: #405444;
            font-family: "Cormorant Garamond", serif;
            font-size: 22px;
            line-height: 1.4;
        }
        .program-quote span {
            display: block;
            margin-top: 6px;
            font-family: "Source Sans Pro", sans-serif;
            font-size: 13px;
            letter-spacing: .04em;
            color: var(--muted);
        }
        .after-parts { margin-top: 28px; display: grid; gap: 18px; }
        .rsvp-list { margin: 12px 0 0; display: grid; gap: 6px; }
        .rsvp-list a { color: #405444; font-weight: 700; text-decoration: none; }
        @media (max-width: 520px) {
            .timeline:before { left: 23px; }
            .part { grid-template-columns: 48px 1fr; gap: 12px; }
            .part-num { width: 48px; height: 48px; font-size: 18px; }
            .part-card { padding: 16px 14px 14px; border-radius: 18px; }
        }
@endsection

@section('content')
    <header class="program-hero">
        <p class="kicker">In loving memory</p>
        <h1>Funeral program</h1>
        <p class="meta">Pa Ngwayu Nchinda Francis, “Wisest” · July 5, 1953 – August 17, 2026</p>
        <div class="program-meta">
            <span class="chip">Saturday 26 September 2026</span>
            <span class="chip">Nkwen Baptist Church, Bamenda</span>
            <span class="chip">Burial at Upstation</span>
        </div>
        <p class="lead">Funeral programme of our father, husband, brother, grandfather Daddy Ngwayu Nchinda Francis, who passed on to glory on Monday, August 17, 2026, at the Nkwen Baptist Hospital. Church service at Nkwen Baptist Church, Bamenda, followed by burial at the family compound, Upstation, Bamenda.</p>
        <p class="program-quote">“I have fought the good fight, I have finished the race, I have kept the faith.”<span>2 Timothy 4:7</span></p>
    </header>

    <div class="timeline">
        <article class="part">
            <div class="part-num">01</div>
            <div class="part-card">
                <span class="part-time">7:00 – 8:00 a.m.</span>
                <h2>Removal of the mortal remains</h2>
                <p>Removal of the mortal remains from St. Blaise Hospital Mortuary, Bamenda, and transportation to Nkwen Baptist Church. Led by the men.</p>
            </div>
        </article>

        <article class="part">
            <div class="part-num">02</div>
            <div class="part-card">
                <span class="part-time">8:00 a.m.</span>
                <h2>Church service</h2>
                <p>Order of service at Nkwen Baptist Church.</p>
                <div class="venue">Nkwen Baptist Church, Bamenda</div>
                <div class="service-list">
                    <div class="service-row"><b>01</b><span>Call to order</span></div>
                    <div class="service-row"><b>02</b><span>Procession with the casket</span></div>
                    <div class="service-row"><b>03</b><span>Call to worship / Invocation</span></div>
                    <div class="service-row"><b>04</b><span>Opening hymn — <a href="{{ route('funeral.pangwayu.hymns') }}">When the Roll Is Called Up Yonder</a></span></div>
                    <div class="service-row"><b>05</b><span>Pastoral prayer</span></div>
                    <div class="service-row"><b>06</b><span>Choir number: Joseph Merrick Vernacular Choir</span></div>
                    <div class="service-row"><b>07</b><span>Biography</span></div>
                    <div class="service-row"><b>08</b><span>Eulogies</span></div>
                    <div class="service-row"><b>09</b><span>The Church (His Christian experience)</span></div>
                    <div class="service-row"><b>10</b><span>The Men</span></div>
                    <div class="service-row"><b>11</b><span>The Youths</span></div>
                    <div class="service-row"><b>12</b><span>The Women</span></div>
                    <div class="service-row"><b>13</b><span>Friend(s)</span></div>
                    <div class="service-row"><b>14</b><span>Family</span></div>
                    <div class="service-row"><b>15</b><span>Youth choir</span></div>
                    <div class="service-row"><b>16</b><span>Scripture reading and message</span></div>
                    <div class="service-row"><b>17</b><span>Choir number: Immanuel Choir</span></div>
                    <div class="service-row"><b>18</b><span>Praise and offering</span></div>
                    <div class="service-row"><b>19</b><span>Prayer time for the family</span></div>
                    <div class="service-row"><b>20</b><span>Announcements</span></div>
                    <div class="service-row"><b>21</b><span>Closing hymn — <a href="{{ route('funeral.pangwayu.hymns') }}">Farther Along</a></span></div>
                    <div class="service-row"><b>22</b><span>Benediction</span></div>
                    <div class="service-row"><b>23</b><span>Recession</span></div>
                    <div class="service-row"><b>24</b><span>Refreshment</span></div>
                </div>
            </div>
        </article>

        <article class="part">
            <div class="part-num">03</div>
            <div class="part-card">
                <span class="part-time">10:30 a.m. · 11:00 a.m.</span>
                <h2>Military honours</h2>
                <p>10:30 a.m. Departure to Upstation. 11:00 a.m. Military honours at the Bamenda Central Prison.</p>
                <div class="venue">Bamenda Central Prison, Upstation</div>
            </div>
        </article>

        <article class="part">
            <div class="part-num">04</div>
            <div class="part-card">
                <span class="part-time">12:00 p.m. · 12:30 p.m.</span>
                <h2>Committal</h2>
                <p>12:00 p.m. Procession to the family compound. 12:30 p.m. Brief laying in state at the Up-station home.</p>
                <div class="venue">Family compound, Upstation</div>
                <div class="service-list">
                    <div class="service-row"><b>01</b><span>Prayer and committal</span></div>
                    <div class="service-row"><b>02</b><span>Filling of the grave</span></div>
                    <div class="service-row"><b>03</b><span>Placing of wreaths</span></div>
                    <div class="service-row"><b>04</b><span>Refreshment</span></div>
                    <div class="service-row"><b>05</b><span>Departure</span></div>
                </div>
            </div>
        </article>
    </div>

    <div class="after-parts">
        <article class="part-card">
            <p class="kicker">Appreciation</p>
            <h2>For the family</h2>
            <p>The family of the late Pa Ngwayu Francis Nchinda wishes to express its heartfelt gratitude to the Baptist Hospital Nkwen, Mbingo Baptist Hospital, and the entire staff of St. Blaise Hospital for the care given to Pa during his final months.</p>
            <p style="margin-top:12px;">We are deeply thankful to the youth, women and men of both Nkwen Baptist Church and Mile 1 Baptist Church, the CBC Men’s Fellowship as a whole, family, friends, colleagues, and every well-wisher for your calls, visits, prayers, and support during this difficult season.</p>
            <p style="margin-top:12px;">Your love carried us. May God bless you all abundantly.</p>
            <div class="venue">Ngwayu Richard Fonjo</div>
        </article>
        <article class="part-card">
            <p class="kicker">RSVP</p>
            <h2>Till we meet again</h2>
            <div class="rsvp-list">
                <a href="tel:+237677318405">677 318 405 — Richard Fonjo Ngwayu</a>
                <a href="tel:+237677387275">677 387 275 — Mercy epse Francis Ngwayu</a>
                <a href="tel:+237677124575">677 124 575 — Ngwayu Victor Nkol</a>
                <a href="tel:+237677674000">677 674 000 — Alex Ndi</a>
            </div>
        </article>
    </div>

    <div class="qr-card">
        <img src="{{ asset('public/memorial/pangwayu/qr-remember.png') }}" alt="QR code for Pa Ngwayu Francis memorial page">
        <div>
            <strong>Scan to open</strong>
            <p>Opens the memorial page: program, hymns, and eulogies.</p>
            <a href="{{ asset('public/memorial/pangwayu/qr-remember.png') }}" download="pangwayu-remember-qr.png">Download QR</a>
        </div>
    </div>
@endsection
