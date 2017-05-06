<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

class TeenSpriteDetector extends SpriteDifferenceDetector
{
    protected function getCropArguments(): array
    {
        return [570, 270, 140, 140];
    }
}
