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
            'onikakushi_patch' => 'https://github.com/07th-mod/onikakushi/archive/master.zip',
            'onikakushi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Onikakushi-CG.zip',
            'onikakushi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Onikakushi-CGAlt.zip',
        ],
        'watanagashi' => [
            'watanagashi_patch' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Watanagashi-CG.zip',
            'watanagashi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Watanagashi-CGAlt.zip',
            'watanagashi_steam' => 'https://github.com/jwgrlrrajn/higurashi-steam-sprite-mods/releases/download/1.1.1/watanagashi-steam-sprites.zip',
        ],
        'tatarigoroshi' => [
            'tatarigoroshi_patch' => 'https://github.com/07th-mod/tatarigoroshi/archive/master.zip',
            'tatarigoroshi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Tatarigoroshi-CG.zip',
            'tatarigoroshi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Tatarigoroshi-CGAlt.zip',
        ],
        'himatsubushi' => [
            'himatsubushi_patch' => 'https://github.com/07th-mod/himatsubushi/archive/master.zip',
            'himatsubushi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Himatsubushi-CG.zip',
            'himatsubushi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Himatsubushi-CGAlt.zip',
        ],
        'meakashi' => [
            'meakashi_patch' => 'https://github.com/07th-mod/meakashi/archive/master.zip',
            'meakashi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Meakashi-CG.zip',
            'meakashi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Meakashi-CGAlt.zip',
        ],
    ];

    public const MG_SPRITE_PREFIXES = [
        'aka_',
        'chibimion_',
        'ir_',
        'iri',
        'iri',
        'me_',
        'me1a_',
        'me1b_',
        'me2_',
        'kasa',
        'kei_',
        'kei1_',
        'kei2_',
        'oi_',
        'oisi',
        're_',
        're1a_',
        're1b_',
        're2a_',
        're2b_',
        'ri_',
        'ri1_',
        'ri2_',
        'ri5_',
        'rim_',
        'sa_',
        'sa1a_',
        'sa1b_',
        'sa2a_',
        'sa2b_',
        'sa5_',
        'sato_',
        'sato1_',
        'sato2_',
        'si_',
        'si3_',
        'ta_',
        'ta1_',
        'ti_',
        'tie',
        'tm_',
        'tomi_',
        'tomi1_',
    ];

    public const PS_ONLY_SPRITE_PREFIXES = [
        'kameda2a_',
        'ki_',
        'kuma_',
        'oka1_',
        'oka2_',
        'oryou_',
        'tetu_',
        'tomita1_',
        'tomita2_',
    ];

    public const OTHER_DELETABLE_PREFIXES = [
        'bg_',
        'oni_',
        '634a47b7-',
        'ryuuketu',
    ];
}