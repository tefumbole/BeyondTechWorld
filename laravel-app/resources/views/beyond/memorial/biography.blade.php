@extends('beyond.memorial.remember-layout')

@section('title', 'Biography · Late Pa Ngwayu Nchinda Francis')

@section('styles')
        body.is-biography .main {
            background: #f3f2eb;
            color: #28312b;
        }
        body.is-biography .foot { color: #5d635c; }
        body.is-biography .foot a { color: #405444; }
        .bio-toc {
            max-width: 980px;
            margin: 0 auto;
            padding: 28px 4px 4px;
        }
        .bio-toc p {
            margin: 0 0 12px;
            color: #b78e4b;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
        }
        .bio-toc ol {
            list-style: none;
            margin: 0;
            padding: 0;
            display: grid;
            grid-template-columns: 1fr 1fr;
            column-gap: 36px;
            counter-reset: toc;
        }
        .bio-toc li {
            counter-increment: toc;
            border-top: 1px solid #ddd9cc;
        }
        .bio-toc a {
            display: flex;
            gap: 14px;
            align-items: baseline;
            padding: 11px 10px;
            border-radius: 6px;
            color: #28312b;
            text-decoration: none;
            font-family: "Cormorant Garamond", serif;
            font-size: 20px;
            line-height: 1.25;
            transition: background .15s ease, color .15s ease;
        }
        .bio-toc a:before {
            content: counter(toc, decimal-leading-zero);
            flex: 0 0 auto;
            color: #b78e4b;
            font-family: "Source Sans Pro", sans-serif;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .12em;
        }
        .bio-toc a:hover {
            color: #28312b;
            background: rgba(183, 142, 75, .18);
        }
        .bio-intro {
            max-width: 980px;
            margin: 0 auto;
            padding: 36px 4px 8px;
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 28px;
            text-align: left;
        }
        .bio-intro h1,
        .bio-intro .meta,
        .bio-intro .lead { grid-column: 2; }
        .bio-intro .kicker {
            grid-column: 1;
            grid-row: 1;
            align-self: start;
            color: #b78e4b;
            letter-spacing: .12em;
            font-size: 11px;
            line-height: 1.45;
            display: block;
            margin: 0;
            padding-top: 16px;
            text-align: left;
        }
        .bio-intro h1 { grid-row: 1; }
        .bio-intro h1 {
            color: #28312b;
            font-size: clamp(40px, 5vw, 64px);
            letter-spacing: -.03em;
            line-height: 1.12;
            margin: 14px 0 12px;
            text-align: left;
        }
        .bio-intro .meta {
            color: #405444;
            font-weight: 700;
            letter-spacing: .12em;
            text-transform: uppercase;
            font-size: 13px;
            text-align: left;
            margin: 0;
        }
        .bio-intro .lead {
            margin: 22px 0 0;
            max-width: none;
            color: #515950;
            font-size: 17px;
            line-height: 1.95;
            text-align: left;
        }
        .chapters {
            max-width: 980px;
            margin: 0 auto;
            padding: 10px 4px 20px;
            counter-reset: chapter;
        }
        .chapter {
            counter-increment: chapter;
            display: grid;
            grid-template-columns: 110px minmax(0, 1fr);
            gap: 28px;
            padding: 42px 0;
            border-top: 1px solid #ddd9cc;
            scroll-margin-top: 84px;
        }
        .chapter-meta {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            padding-top: 14px;
            color: #b78e4b;
            font-size: 12px;
            font-weight: 700;
            letter-spacing: .14em;
        }
        .chapter-meta span:before { content: counter(chapter, decimal-leading-zero) " / 11"; }
        .chapter-line { flex: 1; height: 1px; background: #c9b383; margin-top: 8px; }
        .chapter-body h2 {
            margin: 0 0 18px;
            color: #28312b;
            font-family: "Cormorant Garamond", serif;
            font-size: clamp(30px, 3.4vw, 44px);
            font-weight: 600;
            letter-spacing: -.02em;
            line-height: 1.15;
        }
        .chapter-body p {
            margin: 0 0 18px;
            color: #4f5650;
            font-size: 17px;
            line-height: 1.95;
        }
        .chapter-body p:last-child { margin-bottom: 0; }
        .bio-quote {
            margin: 28px 0 0;
            padding: 24px 28px;
            border-left: 3px solid #b78e4b;
            border-radius: 0;
            background: #f3f0e7;
            color: #405444;
            font-family: "Cormorant Garamond", serif;
            font-size: 24px;
            line-height: 1.5;
            text-align: left;
        }
        .bio-quote span {
            display: block;
            margin-top: 12px;
            font-family: "Source Sans Pro", sans-serif;
            font-size: 11px;
            font-weight: 700;
            letter-spacing: .2em;
            text-transform: uppercase;
            color: #9c7e48;
        }
        @media (max-width: 700px) {
            .bio-toc ol { grid-template-columns: 1fr; }
            .bio-toc a { font-size: 18px; }
            .bio-intro,
            .chapter { display: block; padding: 28px 0; }
            .bio-intro .kicker,
            .bio-intro h1,
            .bio-intro .meta,
            .bio-intro .lead { grid-column: auto; grid-row: auto; padding-top: 0; }
            .chapter-meta { margin-bottom: 10px; padding-top: 0; }
            .bio-intro h1 { font-size: 36px; }
            .bio-quote { padding: 18px 16px; font-size: 20px; }
        }
@endsection

@section('content')
    <nav class="bio-toc" aria-label="Biography contents">
        <p>Contents</p>
        <ol>
            <li><a href="#early-life">Early Life</a></li>
            <li><a href="#academic-journey">Academic Journey &amp; Educational Achievements</a></li>
            <li><a href="#professional-career">Distinction &amp; Professional Career</a></li>
            <li><a href="#gifted-hand">A Gifted Hand and a Resourceful Soul</a></li>
            <li><a href="#generosity">A Life of Generosity</a></li>
            <li><a href="#enterprise">A Spirit of Enterprise and Resourcefulness</a></li>
            <li><a href="#wisest">“Wisest” — A Father, Mentor and Friend to a Generation</a></li>
            <li><a href="#family-life">Family Life</a></li>
            <li><a href="#christian-life">His Christian Life and Faith</a></li>
            <li><a href="#final-journey">His Final Journey</a></li>
            <li><a href="#legacy">A Legacy That Lives On</a></li>
        </ol>
    </nav>

    <header class="bio-intro">
        <p class="kicker">In loving memory</p>
        <h1>Late Pa Ngwayu Nchinda Francis</h1>
        <p class="meta">The biography of “Wisest Ngwayu”</p>
        <p class="lead">Fondly called “Ageyi,” “Ba Timende,” “Wisest” and by the wide circle of friends, siblings, children, grandchildren, nieces, nephews, and mentees who sought his counsel and company, Pa Ngwayu Nchinda Francis lived seventy-three years marked by discipline, faith, generosity, and an unmistakable devotion to family. To the nation he served, he was a decorated officer of the Cameroon Prisons Administration, honoured for a career built on order, integrity, and quiet excellence. To his household and to everyone who ever called him for advice and regarded him as a father, he was something even greater: a wellspring of wisdom, warmth, and unwavering love, a man whose words seemed to arrive exactly when they were needed most.</p>
    </header>

    <div class="chapters">
    <section class="chapter" id="early-life">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>Early Life</h2>
        <p>Pa Ngwayu Nchinda Francis was born on July 5, 1953, in Lang Kevu Oku, Bui Division of the North West Region to Pa Ngwayu wan Kefih and Timende Rose wan Taatah, both of blessed memory.</p>
        </div>
    </section>

    <section class="chapter" id="academic-journey">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>Academic Journey &amp; Educational Achievements</h2>
        <p>Pa Ngwayu Francis Nchinda’s pursuit of knowledge was defined by determination and a commitment to lifelong learning. His formal education began at the Cameroon Baptist Mission School (CBM), today CBC Primary School Kevu, Oku, where he earned his First School Leaving Certificate in June 1967.</p>
        <p>He did a 3-month intensive course as a short-hand typist in Bamenda at Progressive Typing Institute, owned by late Pa Yong Francis (which today would be called documentation) in 1969.</p>
        <p>Driven to advance his studies, without the benefit of formal classroom instruction, he undertook the rigorous task of self-study, mastering the curriculum independently to write and pass his London GCE Ordinary Level in April 1977 and his GCE Advanced Level examination in July 1978 (first batch of the Cameroon GCE).</p>
        <p>This extraordinary display of discipline opened the doors to the University of Yaoundé in 1978, where he formally enrolled to study Law, solidifying his legal acumen and expanding his intellectual horizons.</p>
        <p>However, his path shifted when he chose to pursue specialized professional training in prison administration. He earned his Diploma of Prison Superintendents from the National Prison Training School (CNFRAP) in Buea in July 1980, and completed an extension program at ENAM Yaoundé from September 1980 to June 1981. Demonstrating an ongoing dedication to professional growth, he later earned a Diploma of Prison Administrators from the National Prison School (ENAP) in Buea in December 1997.</p>
        <p>Even after completing his distinguished public service career, his intellectual drive remained vibrant; in March 2015, he achieved a Postgraduate Diploma in Business Administration from the MIT School of Distance Education in Pune, India. This achievement marked the fulfillment of a deeply held personal dream — to proudly earn and bear the title of Master degree holder, Ngwayu Francis, MA, reflecting a lifetime dream by determination, resilience, and an unwavering love for learning.</p>
        </div>
    </section>

    <section class="chapter" id="professional-career">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>Distinction &amp; Professional Career</h2>
        <p>In 1968, at a time when opportunities were scarce and the road ahead was uncertain, he made a courageous move to Buea, seeking a better future and willing to begin from the very bottom. He took up work as a houseboy, embracing the humble assignment not as a reflection of his worth, but as an opportunity to learn, grow, and build a life of dignity.</p>
        <p>In 1969, after completion of a 3-month intensive training at Progressive Typing Institute, he was appointed as an instructor at the same Institute in Bamenda.</p>
        <p>In 1970, he was employed to serve as a Secretary-Typist at Lawyer Layo’s Chambers in Bamenda. There, he continued to distinguish himself through professionalism, accuracy, commitment, and an unwavering sense of responsibility.</p>
        <p>Four years later, in 1974, another chapter of his professional journey began when he joined the Baptist Centre, Bamenda, as a Secretary-Typist. His career was steadily taking shape, but more importantly, he was establishing a reputation as a dependable, disciplined, and capable worker.</p>
        <p>Then, in 1977, his professional journey took yet another significant step forward. He moved into public service, joining the Ministry of Territorial Administration as a Secretary-Typist. This marked an important milestone in a journey that had begun nine years earlier in the most humble of circumstances.</p>
        <p>Ngwayu Francis Nchinda built an exemplary, decades-long career as a high-ranking Prison Administrator across Cameroon. His leadership journey spanned numerous key posts. Due to his outstanding performance in the training school, he was posted as Régisseur at Principal Prison Poli (1982–1983) exceptionally.</p>
        <p>Over the course of his distinguished public service, he held major managerial and operational leadership roles, serving as Chargé de Discipline and Chief of Service at Central Prisons in Bamenda, Yaoundé, Bafoussam, and Douala. His administrative expertise was also recognized at the national policy level, serving as an Office Operative under the Director of Prisons at the MINAT ministry headquarters from 1987 to 1990.</p>
        <p>He commanded several detention facilities as Régisseur (Head of Establishment), leading Principal Prisons in Mbouda, Mbengwi, Kaélé, and Wum, as well as Central Prison Bafoussam with the rank of Assistant Director.</p>
        <p>Beyond prison administration, he served as an Instructor at ENAP Buea from 1999 to 2001, imparting his knowledge by teaching Administrative Writing and Penitentiary Text to the next generation of officers.</p>
        <p>Throughout his career, he remained deeply dedicated to staff discipline, maintenance of order, and the socio-cultural education and rehabilitation of inmates. In recognition of his honorable service to the nation, he was decorated with national honors, including the Knight of the Order of Merit and the Médaille de Vaillance, before his official retirement in July 2008.</p>
        <p>Due to his resourcefulness, he was coopted to serve as the chairman of the tenders’ board of the Elak Council, Oku. A post he handled diligently and faithfully from 2016 to 2019.</p>
        </div>
    </section>

    <section class="chapter" id="gifted-hand">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>A Gifted Hand and a Resourceful Soul</h2>
        <p>Beyond his professional career, he was a man blessed with remarkably gifted hands and an extraordinary ability to solve problems. Without receiving formal training, he developed remarkable skills in plumbing, electrical works, building, tiling, wood carving, and mechanics.</p>
        <p>He was the kind of man who could look at a technical fault and, through patience, wisdom, and practical ingenuity, find a way to fix it. Where others saw a difficult problem, he saw a challenge that could be overcome. His hands were always ready to build, repair, improve, and restore.</p>
        <p>These abilities were more than mere skills — they were a reflection of his character: resourceful, hardworking, curious, determined, and always willing to help. He did not need a classroom to teach him everything; life itself became his workshop, and experience became his teacher.</p>
        <p>Through his hands, many things were repaired; through his wisdom, many problems were solved; and through his willingness to help, many lives were touched.</p>
        </div>
    </section>

    <section class="chapter" id="generosity">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>A Life of Generosity</h2>
        <p>He lived a life marked by generosity, compassion, and selfless service to others. His kindness extended far beyond his immediate family, as he quietly paid school fees and supported the education of many people who might otherwise have struggled to continue their studies.</p>
        <p>For him, giving was never a burden or an obligation — it was a joy. He was always ready to lend a helping hand, and nothing made him happier than knowing that his support had brought hope, relief, or a better opportunity to someone else. Through his generosity, he invested not only in people’s needs, but also in their dreams and futures.</p>
        </div>
    </section>

    <section class="chapter" id="enterprise">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>A Spirit of Enterprise and Resourcefulness</h2>
        <p>Beyond his professional life, he was a man of remarkable entrepreneurial spirit, resourcefulness, and vision. After obtaining his First School Leaving Certificate in 1967, he ventured into business alongside his cousin, Mr. Yow Henry, now retired Superintendent of Prisons, buying palm kernels from Babungo and transporting them to Oku for sale. This early venture revealed the determination, courage, and business instinct that would characterize his life.</p>
        <p>His entrepreneurial journey continued through poultry farming, goat and sheep rearing, and his involvement as a shareholder in Gap Bridge Enterprise, an initiative established with the vision of helping bridge food shortages in prisons.</p>
        <p>Following his retirement, he turned his attention to real estate, investing wisely and building assets that reflected his foresight and commitment to securing a lasting legacy.</p>
        </div>
    </section>

    <section class="chapter" id="wisest">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>“Wisest” — A Father, Mentor and Friend to a Generation</h2>
        <p>Long before it became his most enduring nickname, Pa had already earned the title “Wisest” many times over. It was not a name he gave himself; it was one his children and their cousins settled on him because no conversation with him ever ended without a person leaving a little wiser, a little calmer, or a little more hopeful than they came.</p>
        <p>He handed out nicknames the way other men hand out advice: “Big V,” “Shiam-san,” “Baby-brother,” “Wan se meh,” “Biggeh,” “Chop-chair,” “Namee,” “Constant Victory,” “Baam,” “Alexie,” “Zee,” “Lexie,” “Take care of you,” “Son of Adam,” “KEB-U-H,” “wan,” “Noh Baaba,” “Doctor,” “Wain wom,” “Bread treasurer,” and others — a way of telling a child exactly how much they mattered to him.</p>
        <p>He met bad news with prayer and crying, good news with dancing and crying, and ordinary days with a joke, and in doing so, he taught an entire generation of children, grandchildren, nieces, and nephews what it looks like to walk through life unafraid, unhurried, and full of faith.</p>
        </div>
    </section>

    <section class="chapter" id="family-life">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>Family Life</h2>
        <p>In 1982, he entered into holy matrimony with Miss Nteff Margaret Bih. Their union was blessed with two daughters. Though the marriage ultimately ended ten years later (1992), his love and devotion to his children remained steadfast. Tragically, in April 2023, he endured the profound heartbreak of losing his firstborn daughter, who is survived by her beloved husband, Mr. Alex Ndi, and their two beautiful daughters, Zoe Ndi and Lexi Ndi.</p>
        <p>Following a period of nine years of singlehood, he found love once again and was united in marriage with Miss Nsakse Mercy Nyuylai in 2001. Together, they built a loving, enduring home and were blessed with two children. She remained his faithful companion and dedicated partner throughout the rest of his life’s journey.</p>
        <p>His home was never a small or quiet one. It was, by every account, the family compound, and a household where siblings, nieces, nephews, cousins, and mentees came and went as freely as his own children, all of them under his watchful, affectionate eye, all of them calling him, one way or another, “Pa.” Worth noting is that he served as the Family Head of the entire Ngwayu’s Family.</p>
        </div>
    </section>

    <section class="chapter" id="christian-life">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>His Christian Life and Faith</h2>
        <p>He was baptized on May 7, 1967, at CBC Kevu by Rev. Bah Noah, marking the beginning of a lifelong walk with Christ.</p>
        <p>He was among the founding youth leaders of Nkwen Baptist Church, where he played an important role in nurturing the faith and fellowship of young believers. He remained an active “iron” in the men’s group CBCMF of Nkwen Baptist Church. His commitment to the work of God extended beyond the walls of the church. He generously supported gospel crusades and student pastors in training, contributing quietly but meaningfully to the spread of the Gospel.</p>
        <p>From around the year 2000, his consciousness of eternity became especially evident. He lived with a deep awareness that life on earth is temporary and that his ultimate destination was eternity with God. This conviction shaped his character, his relationships, and the way he served others.</p>
        <p>He did not only support the preaching of the Gospel; he personally shared the Good News, particularly with people of his own age bracket. He spoke about Christ with sincerity, seeking to encourage others to know God and prepare their hearts for eternity.</p>
        <p>His faith was therefore not merely something he professed — it was a life he lived, through his service, generosity, encouragement, and personal witness. He lived conscious of eternity, served God faithfully, and finished his race with the hope of meeting his Saviour.</p>
        <p class="bio-quote">“I have fought the good fight, I have finished the race, I have kept the faith.”<span>2 Timothy 4:7</span></p>
        </div>
    </section>

    <section class="chapter" id="final-journey">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>His Final Journey</h2>
        <p>It all began in May 2026, when a noticeable loss of weight raised concerns about his health. He underwent several medical examinations at Baptist Hospital Nkwen, but the results were inconclusive. He was subsequently referred to Mbingo Baptist Hospital for a CT scan, which revealed a mass in his pancreas.</p>
        <p>He commenced chemotherapy and, after about five sessions, his condition improved considerably. For approximately six weeks, he regained his strength and was up and about, giving the family renewed hope, as he would tell everyone who came around that the prayer point should be that of thanksgiving rather than praying for healing, as he was healed. He was already mobilizing the family for a thanksgiving service in church for his healing.</p>
        <p>However, on Friday, August 14, 2026, his condition suddenly took a turn for the worse. He began experiencing persistent vomiting and loss of appetite. These symptoms continued through the weekend until the early hours of Monday, August 17, 2026, when he was rushed to Baptist Hospital Nkwen at about 4:30 a.m.</p>
        <p>He was attended to at the emergency unit and later admitted to the ward, as his condition was initially not considered very serious. Sadly, at about 11:00 a.m. that morning, he peacefully rested in the Lord.</p>
        <p>Worth noting that he had his first hospitalization at 73.</p>
        </div>
    </section>

    <section class="chapter" id="legacy">
        <div class="chapter-meta"><span></span><div class="chapter-line"></div></div>
        <div class="chapter-body">
        <h2>A Legacy That Lives On</h2>
        <p>He leaves behind, too, unfinished conversations, questions he promised to answer “in a more relaxed manner,” a graduation gown he will not get to see worn, a white coat ceremony he will not attend, weddings he did not live to bless, and more. But he also leaves behind the certainty, in the hearts of everyone who loved him, that he ran his race well, that he is now farther along, and that, as he himself believed and taught, “we’ll understand it all by and by.”</p>
        <p>His legacy lives on.</p>
        </div>
    </section>
    </div>
@endsection
