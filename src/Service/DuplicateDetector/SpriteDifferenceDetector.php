<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

use Nette\Utils\Image;

abstract class SpriteDifferenceDetector extends HashDifferenceDetector
{
    public function accepts(Picture $picture): bool
    {
        return $picture->getWidth() === 1280 && $picture->getHeight() === 960;
    }

    protected function processImage(Image $image): void
    {
        $image->crop(...$this->getCropArguments());
    }

    abstract protected function getCropArguments(): array;
}