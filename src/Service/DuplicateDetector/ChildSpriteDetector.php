<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

class ChildSpriteDetector extends SpriteDifferenceDetector
{
    protected function getCropArguments(): array
    {
        return [570, 360, 140, 140];
    }
}
