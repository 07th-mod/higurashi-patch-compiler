<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

class AdultSpriteDetector extends SpriteDifferenceDetector
{
    protected function getCropArguments(): array
    {
        return [570, 230, 140, 140];
    }
}
