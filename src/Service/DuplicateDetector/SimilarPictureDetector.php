<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

interface SimilarPictureDetector
{
    public function accepts(Picture $picture): bool;

    public function arePicturesSimilar(Picture $picture1, Picture $picture2): bool;
}
