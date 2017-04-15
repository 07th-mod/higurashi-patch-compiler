<?php

declare(strict_types=1);

namespace Higurashi\Service;

class Links
{
    const VOICES = [
        'download/voices_1.zip' => 'https://github.com/07th-mod/resources/releases/download/Nipah/HigurashiPS3-Voices01.zip',
        'download/voices_2.zip' => 'https://github.com/07th-mod/resources/releases/download/Nipah/HigurashiPS3-Voices02.zip',
    ];

    const PATCHES = [
        'tatarigoroshi' => [
            'graphics' => 'https://gitlab.com/07th-mod/tatarigoroshi-graphics/repository/archive.zip?ref=master',
            'patch' => 'https://github.com/07th-mod/tatarigoroshi/archive/master.zip',
        ],
    ];
}