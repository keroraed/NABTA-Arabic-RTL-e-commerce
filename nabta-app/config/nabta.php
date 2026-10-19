<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Shop notifications
    |--------------------------------------------------------------------------
    |
    | Where the "new order" email goes. Comma-separate several addresses. Leave
    | empty to switch the email off. Delivery uses the normal MAIL_* settings.
    |
    */

    'admin_notification_email' => env('ADMIN_NOTIFICATION_EMAIL', 'admin@nabta.store'),

    /*
    |--------------------------------------------------------------------------
    | Art direction
    |--------------------------------------------------------------------------
    |
    | The prototype framed every product photo by hand: each pack sits at a
    | different height and width inside its card, and each category tile has its
    | own shelf position, because the source images are cropped differently.
    | Flattening these to one rule visibly breaks the design, so they are kept
    | here keyed by the product's legacy id, with a default for anything added
    | through the admin later.
    |
    */

    'art' => [

        'card_default' => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:100%;width:auto;max-width:94%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',

        'cards' => [
            'masalek'   => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:118%;width:auto;max-width:88%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'indigo'    => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:104%;width:auto;max-width:96%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'pumpkin'   => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:100%;width:auto;max-width:98%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'soap'      => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:96%;width:auto;max-width:88%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'honey'     => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:96%;width:auto;max-width:92%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'cinnamon'  => 'position:absolute;bottom:6%;inset-inline-start:50%;transform:translateX(50%);width:104%;max-width:none;height:auto;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'macadamia' => 'position:absolute;bottom:2%;inset-inline-start:50%;transform:translateX(50%);width:100%;max-width:none;height:auto;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
            'makhtoom'  => 'position:absolute;bottom:0;inset-inline-start:50%;transform:translateX(50%);height:106%;width:auto;max-width:96%;object-fit:contain;filter:drop-shadow(0 18px 24px rgba(14,42,27,.16))',
        ],

        /*
        | The best-seller rail sits on the dark section, where each photo needs a
        | different width to sit on the same optical baseline.
        */
        'best_default' => '90%',

        'best' => [
            'cinnamon'  => '100%',
            'honey'     => '84%',
            'macadamia' => '100%',
            'masalek'   => '62%',
            'soap'      => '88%',
        ],


        /*
        | The categories landing page. Same shelf-breakout as the home tiles, but
        | eight cards on a taller shelf. 'hero' names the product whose photo
        | fronts the card; 'motion' names the entry in TILE_MOTION in nabta.js —
        | the four categories with no entry get the plain lift instead.
        */
        'category_cards' => [
            'herbs'  => ['hero' => 'masalek',   'glow' => 'rgba(23,107,58,.14)', 'normal' => 'bottom:9%;width:76%', 'opened' => 'bottom:9%;width:82%'],
            'spices' => ['hero' => 'cinnamon',  'glow' => 'rgba(107,70,48,.15)', 'normal' => 'bottom:4%;width:92%', 'opened' => 'bottom:2%;width:98%'],
            'nuts'   => ['hero' => 'macadamia', 'glow' => 'rgba(107,70,48,.14)', 'normal' => 'bottom:2%;width:88%', 'opened' => 'bottom:-2%;width:94%'],
            'seeds'  => ['hero' => 'pumpkin',   'glow' => 'rgba(23,107,58,.14)', 'normal' => 'bottom:3%;width:90%', 'opened' => 'bottom:1%;width:96%'],
            'honey'  => ['hero' => 'honey',     'glow' => 'rgba(107,70,48,.16)', 'normal' => 'bottom:5%;width:84%', 'opened' => 'bottom:0%;width:94%'],
            'soap'   => ['hero' => 'soap',      'glow' => 'rgba(107,70,48,.13)', 'normal' => 'bottom:17%;width:80%', 'opened' => null],
            'mixes'  => ['hero' => 'makhtoom',  'glow' => 'rgba(23,107,58,.14)', 'normal' => 'bottom:2%;width:86%', 'opened' => null],
            'care'   => ['hero' => 'indigo',    'glow' => 'rgba(23,80,140,.12)', 'normal' => 'bottom:1%;width:88%', 'opened' => null],
        ],

        'category_card_default' => ['hero' => null, 'glow' => 'rgba(23,107,58,.14)', 'normal' => 'bottom:6%;width:84%', 'opened' => null],

        /*
        | The five shelf tiles on the home page. Each one is a different physical
        | object, so height, radius glow, and both artwork positions are tuned per
        | tile. `motion` names the entry in the TILE_MOTION table in nabta.js.
        */
        'tiles' => [
            [
                'legacy_id' => 'masalek',  'label' => 'أعشاب',  'category' => 'herbs',
                'height' => 'clamp(290px,30vw,380px)',
                'glow' => 'rgba(23,107,58,.14)',
                'normal' => 'bottom:9%;width:70%',
                'opened' => 'bottom:9%;width:76%',
            ],
            [
                'legacy_id' => 'cinnamon', 'label' => 'توابل',  'category' => 'spices',
                'height' => 'clamp(320px,34vw,430px)',
                'glow' => 'rgba(107,70,48,.15)',
                'normal' => 'bottom:4%;width:88%',
                'opened' => 'bottom:2%;width:94%',
            ],
            [
                'legacy_id' => 'macadamia', 'label' => 'مكسرات', 'category' => 'nuts',
                'height' => 'clamp(290px,30vw,380px)',
                'glow' => 'rgba(107,70,48,.14)',
                'normal' => 'bottom:2%;width:84%',
                'opened' => 'bottom:-2%;width:90%',
            ],
            [
                'legacy_id' => 'pumpkin',  'label' => 'بذور',   'category' => 'seeds',
                'height' => 'clamp(320px,34vw,430px)',
                'glow' => 'rgba(23,107,58,.14)',
                'normal' => 'bottom:3%;width:86%',
                'opened' => 'bottom:1%;width:92%',
            ],
            [
                'legacy_id' => 'honey',    'label' => 'عسل',    'category' => 'honey',
                'height' => 'clamp(290px,30vw,380px)',
                'glow' => 'rgba(107,70,48,.16)',
                'normal' => 'bottom:5%;width:80%',
                'opened' => 'bottom:0%;width:90%',
            ],
        ],
    ],

];
