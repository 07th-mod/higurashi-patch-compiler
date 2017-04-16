<?php

declare(strict_types=1);

namespace Higurashi;

class Constants
{
    public const VOICES = [
        'download/voices_1.zip' => 'https://github.com/07th-mod/resources/releases/download/Nipah/HigurashiPS3-Voices01.zip',
        'download/voices_2.zip' => 'https://github.com/07th-mod/resources/releases/download/Nipah/HigurashiPS3-Voices02.zip',
    ];

    public const PATCHES = [
        'onikakushi' => [
            'graphics' => 'https://gitlab.com/07th-mod/onikakushi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/onikakushi/archive/master.zip',
            'steam' => 'https://github.com/jwgrlrrajn/higurashi-steam-sprite-mods/releases/download/1.0.1/onikakushi-steam-sprites.zip',
        ],
        'watanagashi' => [
            'graphics' => 'https://gitlab.com/07th-mod/watanagashi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/watanagashi/archive/master.zip',
            'steam' => 'https://github.com/jwgrlrrajn/higurashi-steam-sprite-mods/releases/download/1.0.1/watanagashi-steam-sprites.zip',
        ],
        'tatarigoroshi' => [
            'graphics' => 'https://gitlab.com/07th-mod/tatarigoroshi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/tatarigoroshi/archive/master.zip',
            'steam' => 'https://github.com/jwgrlrrajn/higurashi-steam-sprite-mods/releases/download/1.0.1/tatarigoroshi-steam-sprites.zip',
        ],
        'himatsubushi' => [
            'graphics' => 'https://gitlab.com/07th-mod/himatsubushi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/himatsubushi/archive/master.zip',
        ],
    ];

    public const GAMES = [
        'onikakushi' => 'C:\Program Files (x86)\Steam\steamapps\common\Higurashi When They Cry\HigurashiEp01_Data\StreamingAssets',
        'watanagashi' => 'C:\Program Files (x86)\Steam\steamapps\common\Higurashi 02 - Watanagashi\HigurashiEp02_Data\StreamingAssets',
        'tatarigoroshi' => 'C:\Program Files (x86)\Steam\steamapps\common\Higurashi 03 - Tatarigoroshi\HigurashiEp03_Data\StreamingAssets',
        'himatsubushi' => 'C:\Program Files (x86)\Steam\steamapps\common\Higurashi 04 - Himatsubushi\HigurashiEp04_Data\StreamingAssets',
    ];

    public const SPRITE_PREFIXES = [
        'chibimion_',
        'ir_',
        'iri2_',
        //'kameda2a_',
        'me_',
        //'kasa_',
        'ki_',
        //'kuma_',
        'oi_',
        'oisi1_',
        'oisi2_',
        //'oka1_',
        //'oka2_',
        //'oryou_',
        're_',
        're2b_',
        'ri_',
        'rim_',
        'sa_',
        'sa1a_',
        'sa5_',
        'si_',
        'ta_',
        'ti_',
        'tie_',
        'tm_',
        //'tomita1_',
        //'tomita2_',
    ];
}