<?php

/**
 * Structured biography for Pa Ngwayu Francis.
 * Edit here, or override from Admin → Funeral biography.
 *
 * Sources used (do not invent beyond these):
 * - Family eulogy of Engr. Desmond Fonjo Ngwayu (5 Sep 2026)
 * - Published family tributes on /pangwayu/remember
 * - Existing memorial portraits and dates already on the site
 *
 * Fields marked placeholder => true still need family details.
 */

return [
    'meta' => [
        'title' => 'Biography of Pa Ngwayu Francis (1953–2026)',
        'description' => 'The life story of Pa Ngwayu Francis: a man of faith, service, family and legacy. 1953 — 2026.',
        'og_image' => 'memorial/pangwayu/remember-landing.jpg',
    ],
    'hero' => [
        'kicker' => 'In loving memory',
        'name' => 'Pa Ngwayu Francis',
        'years' => '1953 — 2026',
        'line' => 'A Life of Faith • Service • Family • Legacy',
        'faith_line' => 'A faithful servant until the end',
        'quote' => 'The journey is certain. Nothing shall stand in the way.',
        'quote_attr' => 'Pa Ngwayu Francis',
        'portrait' => 'memorial/pangwayu/cbc.jpg',
        'portrait_alt' => 'Pa Ngwayu Francis in Cameroon Baptist Convention Men’s Fellowship attire',
        'companion' => 'memorial/pangwayu/remember-red.jpg',
        'companion_alt' => 'Pa Ngwayu Francis in ceremonial dress',
        'companion_caption' => 'Well done, good and faithful servant. Matthew 25:21',
        'values_rail' => ['Faith', 'Service', 'Family', 'Integrity', 'Legacy', 'Forever'],
    ],
    'intro' => [
        'kicker' => 'A remarkable life',
        'title' => 'A man of faith, service and people',
        'image' => 'memorial/pangwayu/military.jpg',
        'image_caption' => 'Pa Ngwayu Francis',
        'quote' => 'The journey is certain. Nothing shall stand in the way.',
        'quote_attr' => 'Pa Ngwayu Francis',
        'paragraphs' => [
            'Pa Ngwayu Francis, known to those closest to him as Wisest, lived seventy-three years of faith, service, counsel and love of family. He opened his arms, his heart and his ears to anyone who needed encouragement, support, or simply someone to listen.',
            'He was a father who spoke in decrees of hope and a Christian who faced his last days without fear. The words he left his children still travel with them: the journey is certain, and nothing shall stand in the way.',
        ],
    ],
    'nav' => [
        ['id' => 'story', 'label' => 'His Story'],
        ['id' => 'life', 'label' => 'Life'],
        ['id' => 'family', 'label' => 'Family'],
        ['id' => 'service', 'label' => 'Service'],
        ['id' => 'faith', 'label' => 'Faith'],
        ['id' => 'legacy', 'label' => 'Legacy'],
        ['id' => 'gallery', 'label' => 'Gallery'],
        ['id' => 'tributes', 'label' => 'Tributes'],
    ],
    'sections' => [
        [
            'id' => 'story',
            'layout' => 'prose',
            'kicker' => 'His story',
            'title' => 'Wisest',
            'subtitle' => 'The name those who loved him used for him',
            'image' => 'memorial/pangwayu/studio.jpg',
            'image_caption' => 'Pa Ngwayu Francis in traditional attire',
            'placeholder' => false,
            'paragraphs' => [
                'Those who sat under his counsel remember a man who was resourceful, honest, pushful, and careful to uphold the right values. He was generous with time. He was quick with humour. He was slow to turn anyone away.',
                'His son Engr. Desmond Fonjo Ngwayu wrote that Pa Ngwayu had spent his whole life getting ready for the day he would go home. Having a retrospective of his last days and words simply tells the family he was not afraid of what came next.',
                'Every time a child brought him good news, he answered with the same decree: “The journey is certain. Nothing shall stand in the way.” It was not a wish. It was how he taught them to walk.',
            ],
            'quote' => 'The journey is certain. Nothing shall stand in the way.',
            'quote_attr' => 'Pa Ngwayu Francis',
        ],
        [
            'id' => 'life',
            'layout' => 'split-image-left',
            'kicker' => 'Early life',
            'title' => 'Sunrise, 1953',
            'subtitle' => 'The beginning of a long, faithful race',
            'image' => 'memorial/pangwayu/cbc.jpg',
            'image_caption' => 'Pa Ngwayu Francis',
            'placeholder' => true,
            'placeholder_note' => 'Family to supply: place of birth, parents, childhood home, schooling, and the story of his youth.',
            'paragraphs' => [
                'Pa Ngwayu Francis was born in 1953. He lived seventy-three years.',
                'The fuller account of his childhood, his parents, and the home that formed him is being gathered with the family and will be added here.',
            ],
        ],
        [
            'id' => 'family',
            'layout' => 'split-image-right',
            'kicker' => 'Family',
            'title' => 'A devoted father',
            'subtitle' => 'Love spoken in rooms, nicknames and counsel',
            'image' => 'memorial/pangwayu/studio.jpg',
            'image_caption' => 'Pa Ngwayu Francis',
            'placeholder' => true,
            'placeholder_note' => 'Family to supply: full name of his wife, wedding year, and a complete list of children and grandchildren.',
            'paragraphs' => [
                'He was a father whose children still hear his voice. Engr. Desmond Fonjo Ngwayu wrote that he was the only one who ever called him Biggeh, because he knew what the name meant to him. Nobody else may ever do so.',
                'His first son, Dr Claude Ngwayu, remembers being called first son, and the day Pa Ngwayu gave him a room in the house and simply said, “This room is for you alone.” Those words sounded simple. They spoke of belonging.',
                'When Claude-Junior was born, Pa Ngwayu was glad, and he noticed that the child shared a birthday with his sister. With his usual wisdom he said, “Three kids are not a walk in the park.” Only he could say something so ordinary and make it stay.',
                'Around the early 2000s a transfer brought him to live in Mbengwi with family. The children of that house still smile at his “points” — a little discipline he used so they would leave Pidgin and speak English or their mother tongue. One of those children, Dr. Ntaima Claude Kebuh, later carried a love of English through school and university, and still hears the name “KEB-U-H” as Pa Ngwayu used to say it.',
                'His in-law Chii Walter Ndifon remembered him as caring and generous, a man who dedicated himself to God.',
            ],
            'quote' => 'This room is for you alone.',
            'quote_attr' => 'Pa Ngwayu Francis, remembered by Dr Claude Ngwayu',
        ],
        [
            'id' => 'service',
            'layout' => 'prose',
            'kicker' => 'Career & service',
            'title' => 'A life given to duty',
            'subtitle' => 'Work, counsel and the quiet strength of showing up',
            'image' => 'memorial/pangwayu/military.jpg',
            'image_caption' => 'Pa Ngwayu Francis in ceremonial dress',
            'placeholder' => true,
            'placeholder_note' => 'Family to supply: official rank, years and places of service, and other professional or community offices. Do not invent titles from photographs.',
            'paragraphs' => [
                'The family remembers his service, his discipline and the way he made himself available to anyone who needed counsel. His son Desmond thanked him for being resourceful, pushful, honest, and for upholding the right values.',
                'From the prison service he left a saying the children still quote: “In the prison service, no news is good news.” When time passed without a call, that was how he taught them to read the silence.',
                'Portraits the family already placed on this memorial show him in ceremonial dress and in the green of the Cameroon Baptist Convention Men’s Fellowship. The fuller record of his ranks, stations and years of work will be added when the family supplies it.',
            ],
        ],
        [
            'id' => 'faith',
            'layout' => 'split-image-left',
            'kicker' => 'Faith',
            'title' => 'Thy will be done',
            'subtitle' => 'A Christian who had already spent his life getting ready',
            'image' => 'memorial/pangwayu/remember-jesus.jpg',
            'image_caption' => 'Well done, good and faithful servant — Matthew 25:21',
            'placeholder' => false,
            'paragraphs' => [
                'Pa Ngwayu Francis lived in the fear of the Lord. The memorial portraits show him in the attire of the Cameroon Baptist Convention Men’s Fellowship, whose motto is written on the cloth he wore: Strong, Firm and Steadfast.',
                'He did not fear the day of his going. Even the night before his procedure, he was not asking to be spared. He was teaching the family how to let go.',
                'He said, “At 73, we ought to consider that our race is run and look forward to our reward. Jesus taught us to say, Thy will be done.” His son wrote: that was never resignation. That was conviction.',
            ],
            'quote' => 'At 73, we ought to consider that our race is run and look forward to our reward. Jesus taught us to say, Thy will be done.',
            'quote_attr' => 'Pa Ngwayu Francis, the night before his procedure',
        ],
        [
            'id' => 'character',
            'layout' => 'prose',
            'kicker' => 'Character & values',
            'title' => 'The measure of the man',
            'subtitle' => 'What those who knew him asked us to remember',
            'placeholder' => false,
            'paragraphs' => [
                'The family did not have to search for words to describe him. They had lived them. Desmond named the fruit of his father’s life: service, love, counsel, selflessness, support, a sense of humour, faith, discipline, courage, honesty, and the will to uphold what is right.',
                'He was soft-spoken and generous. He was funny in a way that left wisdom behind the joke. He was, as one tribute put it, a true blessing.',
            ],
        ],
        [
            'id' => 'final',
            'layout' => 'split-image-right',
            'kicker' => 'His final years',
            'title' => 'The race is run',
            'subtitle' => 'Teaching his family how to let go',
            'image' => 'memorial/pangwayu/remember-cbc-heaven.jpg',
            'image_caption' => 'In sure and certain hope',
            'placeholder' => false,
            'paragraphs' => [
                'In his last days he spoke of good health and soundness, so much so that those who loved him hoped the next message would say it had all been a scheme to worry them a little, and that Thanksgiving was near.',
                'Two days before he left, an ordinary line went to him: “I hope you’re doing better.” The next day the words grew heavier: “We need you, and you know it. Please, hang in there. You can’t go now. We have a deal.” Then Monday came, and the hardest sentence of all.',
                'He went home in 2026, at seventy-three. The family was left with a hole in a promise, and with the only thing they know how to do: keep talking to him, the way they always have. Farther along, they will understand it all.',
            ],
        ],
        [
            'id' => 'legacy',
            'layout' => 'prose',
            'kicker' => 'Legacy',
            'title' => 'Nothing shall stand in the way',
            'subtitle' => 'What remains in the people he formed',
            'placeholder' => false,
            'paragraphs' => [
                'His children carry his decrees into their work and their homes. One is completing a doctorate Pa Ngwayu wanted almost as much as the student did. Another continues in his legacy of service and faith. Those who grew up under his points still love the English language he pressed into them.',
                'The love he gave cannot die. The counsel remains. The jokes remain. The room he set aside remains. And the sentence he made a decree still stands over every hard road they walk.',
            ],
            'quote' => 'Nothing shall stand in the way.',
            'quote_attr' => 'Pa Ngwayu Francis',
        ],
    ],
    'timeline' => [
        'kicker' => 'Life timeline',
        'title' => 'Key moments',
        'subtitle' => 'Only dates and events the family has already placed on this memorial',
        'events' => [
            [
                'year' => '1953',
                'title' => 'Born',
                'text' => 'Pa Ngwayu Francis was born. The memorial remembers this year as his sunrise.',
            ],
            [
                'year' => 'Early 2000s',
                'title' => 'Mbengwi',
                'text' => 'A transfer brought him to live with family in Mbengwi, where the children still remember his “points” and his laughter.',
            ],
            [
                'year' => '2026',
                'title' => 'Went home to the Lord',
                'text' => 'He finished his race at seventy-three, looking forward to his reward.',
            ],
        ],
    ],
    'values' => [
        'kicker' => 'His values',
        'title' => 'A life of lasting impact',
        'image' => 'memorial/pangwayu/studio.jpg',
        'image_caption' => 'Pa Ngwayu Francis',
        'items' => [
            ['name' => 'Faith', 'note' => 'Thy will be done'],
            ['name' => 'Family', 'note' => 'A devoted father'],
            ['name' => 'Service', 'note' => 'Duty, quietly kept'],
            ['name' => 'Wisdom', 'note' => 'Called Wisest'],
            ['name' => 'Integrity', 'note' => 'He upheld the right values'],
            ['name' => 'Discipline', 'note' => 'A steady, formed life'],
            ['name' => 'Courage', 'note' => 'Unafraid of the last day'],
            ['name' => 'Selflessness', 'note' => 'Arms, heart and ears open'],
        ],
    ],
    'gallery' => [
        'kicker' => 'Photographs',
        'title' => 'Life in pictures',
        'subtitle' => 'Portraits the family has already placed on this memorial. Years will be added when they are known.',
        'items' => [
            [
                'src' => 'memorial/pangwayu/studio.jpg',
                'caption' => 'In traditional attire',
                'year' => '',
            ],
            [
                'src' => 'memorial/pangwayu/cbc.jpg',
                'caption' => 'Cameroon Baptist Convention Men’s Fellowship — Strong, Firm and Steadfast',
                'year' => '',
            ],
            [
                'src' => 'memorial/pangwayu/military.jpg',
                'caption' => 'In ceremonial dress',
                'year' => '',
            ],
            [
                'src' => 'memorial/pangwayu/remember-landing.jpg',
                'caption' => 'In loving memory · Sunrise 1953 · Sunset 2026',
                'year' => '1953 — 2026',
            ],
            [
                'src' => 'memorial/pangwayu/remember-red.jpg',
                'caption' => 'Well done, good and faithful servant',
                'year' => '',
            ],
            [
                'src' => 'memorial/pangwayu/remember-cbc-heaven.jpg',
                'caption' => 'In sure and certain hope',
                'year' => '',
            ],
            [
                'src' => 'memorial/pangwayu/remember-jesus.jpg',
                'caption' => 'Matthew 25:21',
                'year' => '',
            ],
        ],
    ],
    'legacy_close' => [
        'kicker' => 'His legacy lives on',
        'title' => 'His legacy lives on',
        'paragraphs' => [
            'Pa Ngwayu Francis left more than photographs. He left a way of speaking, a way of serving, and a way of trusting God when the race is run.',
            'His influence remains in his children, in the family he counselled, in the church fellowship he wore on his chest, and in everyone who ever sat down and found that he had time to listen.',
        ],
    ],
    'closing' => [
        'name' => 'Pa Ngwayu Francis',
        'years' => '1953 — 2026',
        'lines' => [
            'Forever Loved.',
            'Forever Remembered.',
            'His Legacy Lives On.',
        ],
    ],
];
