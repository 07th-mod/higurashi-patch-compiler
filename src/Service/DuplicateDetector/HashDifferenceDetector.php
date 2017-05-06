<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

use Jenssegers\ImageHash\ImageHash;
use Jenssegers\ImageHash\Implementations\PerceptualHash;
use Nette\Utils\Image;

class HashDifferenceDetector implements SimilarPictureDetector
{
    /**
     * @var ImageHash
     */
    private $hasher;

    /**
     * @var array
     */
    private $hashes = [];

    public function __construct()
    {
        $this->hasher = new ImageHash(new PerceptualHash(), ImageHash::DECIMAL);
    }

    public function accepts(Picture $picture): bool
    {
        return true;
    }

    public function arePicturesSimilar(Picture $picture1, Picture $picture2): bool
    {
        return $this->hasher->distance($this->getHash($picture1), $this->getHash($picture2)) === 0;
    }

    private function getHash(Picture $picture): int
    {
        if (! array_key_exists($picture->getPath(), $this->hashes)) {
            $image = Image::fromFile($picture->getPath());
            $this->processImage($image);
            $this->hashes[$picture->getPath()] = $this->hasher->hash($image->getImageResource());
        }

        return $this->hashes[$picture->getPath()];
    }

    protected function processImage(Image $image): void
    {
    }
}