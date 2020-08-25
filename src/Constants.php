<?php

declare(strict_types=1);

namespace Higurashi;

class Constants
{
    public const VOICES = [
        'download/voices_1.zip' => 'https://07th-mod.com/archive/HigurashiPS3-Voices01.zip',
        'download/voices_2.zip' => 'https://07th-mod.com/archive/HigurashiPS3-Voices02.zip',
        'download/voices_3.zip' => 'https://07th-mod.com/archive/HigurashiPS3-Voices03.zip',
        'download/voices_4.zip' => 'https://07th-mod.com/archive/HigurashiPS3-Voices04.zip',
    ];

    public const VOICES_PS2 = [
        'download/ps2-voices_1.zip' => 'https://07th-mod.com/archive/HigurashiPS2-Voices01.zip',
        'download/ps2-voices_2.zip' => 'https://07th-mod.com/archive/HigurashiPS2-Voices02.zip',
    ];

    public const SPECTRUM = 'https://07th-mod.com/archive/Higurashi-Spectrum-the-compiler-needs-this-one-but-it-is-literally-the-same-as-the-other-one-except-it-is-a-zip.zip';

    public const PATCHES = [
        'console' => [
            // Change to adv-mode when running DllUpdate. Change to dll-update when running LipSync.
            'console_patch' => 'https://github.com/07th-mod/higurashi-console-arcs/archive/master.zip',
            //'console_patch' => 'https://github.com/07th-mod/higurashi-console-arcs/archive/adv-mode.zip',
        ],
        'onikakushi' => [
            'onikakushi_patch' => 'https://github.com/07th-mod/onikakushi/archive/master.zip',
            //'onikakushi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Onikakushi-CG.zip',
            //'onikakushi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Onikakushi-CGAlt.zip',
        ],
        'watanagashi' => [
            'watanagashi_patch' => 'https://github.com/07th-mod/watanagashi/archive/master.zip',
            //'watanagashi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Watanagashi-CG.zip',
            //'watanagashi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Watanagashi-CGAlt.zip',
        ],
        'tatarigoroshi' => [
            'tatarigoroshi_patch' => 'https://github.com/07th-mod/tatarigoroshi/archive/master.zip',
            //'tatarigoroshi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Tatarigoroshi-CG.zip',
            //'tatarigoroshi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Tatarigoroshi-CGAlt.zip',
        ],
        'himatsubushi' => [
            'himatsubushi_patch' => 'https://github.com/07th-mod/himatsubushi/archive/master.zip',
            //'himatsubushi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Himatsubushi-CG.zip',
            //'himatsubushi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Himatsubushi-CGAlt.zip',
        ],
        'meakashi' => [
            'meakashi_patch' => 'https://github.com/07th-mod/meakashi/archive/master.zip',
            //'meakashi_graphics' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Meakashi-CG.zip',
            //'meakashi_steam' => 'https://github.com/07th-mod/resources/releases/download/Nipah/Meakashi-CGAlt.zip',
        ],
        'tsumihoroboshi' => [
            'tsumihoroboshi_patch' => 'https://github.com/07th-mod/tsumihoroboshi/archive/master.zip',
        ],
        'minagoroshi' => [
            'minagoroshi_patch' => 'https://github.com/07th-mod/minagoroshi/archive/master.zip',
        ],
        'matsuribayashi' => [
            'matsuribayashi_patch' => 'https://github.com/07th-mod/matsuribayashi/archive/master.zip'
        ],
        'someutsushi' => [],
        'kageboushi' => [],
        'tsukiotoshi' => [],
        'taraimawashi' => [],
        'tokihogushi' => [],
        'yoigoshi' => [],
        'omote' => [],
        'ura' => [],
        'kakera' => [],
        'kotogohushi' => [],
        'hajisarashi' => [],
        'prologue' => [],
    ];

    public const CONSOLE_ARCS = [
        'someutsushi' => 'some',
        'kageboushi' => 'kage',
        'tsukiotoshi' => 'tsuk',
        'taraimawashi' => 'tara',
        'yoigoshi' => 'yoig',
        'tokihogushi' => 'toki',
        'kotogohushi' => 'koto',
        'omote' => 'omo',
        'ura' => 'ura',
        'kakera' => 'kake',
        'hajisarashi' => 'haji',
        'prologue' => 'prol',
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

    /**
     * PS2 character name => PS3 character name
     *
     * PS3 only characters are not included.
     */
    public const CHARACTER_NUMBERS = [
        '00' => '00',
        '01' => '02',
        '02' => '03',
        '03' => '06',
        '04' => '05',
        '05' => '04',
        '06' => '01',
        '07' => '07',
        '08' => '08',
        '09' => '09',
        '10' => '11',
        '11' => '10',
        '12' => '36',
        '13' => '22',
        '14' => '15',
        '15' => '13',
        '16' => '20',
        '17' => '21',
        '18' => '12',
        '19' => '16',
        '20' => '14',
        '21' => '18',
        '22' => '19',
    ];
}
