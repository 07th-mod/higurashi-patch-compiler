<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

class SizeDifferenceDetector implements SimilarPictureDetector
{
    public function accepts(Picture $picture): bool
    {
        return true;
    }

    public function arePicturesSimilar(Picture $picture1, Picture $picture2): bool
    {
        return $picture1->getWidth() === $picture2->getWidth() && $picture1->getHeight() === $picture2->getHeight();
    }
}