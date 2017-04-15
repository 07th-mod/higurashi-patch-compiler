<?php

declare(strict_types=1);

namespace Higurashi;

class Constants
{
    const VOICES = [
        'download/voices_1.zip' => 'https://github.com/07th-mod/resources/releases/download/Nipah/HigurashiPS3-Voices01.zip',
        'download/voices_2.zip' => 'https://github.com/07th-mod/resources/releases/download/Nipah/HigurashiPS3-Voices02.zip',
    ];

    const PATCHES = [
        'onikakushi' => [
            'graphics' => 'https://gitlab.com/07th-mod/onikakushi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/onikakushi/archive/master.zip',
        ],
        'watanagashi' => [
            'graphics' => 'https://gitlab.com/07th-mod/watanagashi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/watanagashi/archive/master.zip',
        ],
        'tatarigoroshi' => [
            'graphics' => 'https://gitlab.com/07th-mod/tatarigoroshi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/tatarigoroshi/archive/master.zip',
        ],
        'himatsubushi' => [
            'graphics' => 'https://gitlab.com/07th-mod/himatsubushi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/himatsubushi/archive/master.zip',
        ],
    ];
}