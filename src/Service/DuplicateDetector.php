<?php

namespace Higurashi\Service;

use Higurashi\Service\DuplicateDetector\AdultSpriteDetector;
use Higurashi\Service\DuplicateDetector\ChildSpriteDetector;
use Higurashi\Service\DuplicateDetector\HashDifferenceDetector;
use Higurashi\Service\DuplicateDetector\Picture;
use Higurashi\Service\DuplicateDetector\PixelByPixelDetector;
use Higurashi\Service\DuplicateDetector\SimilarPictureDetector;
use Higurashi\Service\DuplicateDetector\SizeDifferenceDetector;
use Higurashi\Service\DuplicateDetector\TeenSpriteDetector;

class DuplicateDetector
{
    /**
     * @var array
     */
    private $detectors;

    public function __construct()
    {
        $this->detectors = [
            new SizeDifferenceDetector(),
            new HashDifferenceDetector(),
            new TeenSpriteDetector(),
            new ChildSpriteDetector(),
            new AdultSpriteDetector(),
            new PixelByPixelDetector(),
        ];
    }

    public function processDirectory(string $directory): \Generator
    {
        $files = glob($directory . '/*.png');

        $pictures = [];
        foreach ($files as $file) {
            $pictures[] = new Picture($file);
        }

        foreach ($pictures as $picture1) {
            set_time_limit(300);

            foreach ($pictures as $picture2) {
                if ($picture1->getPath() === $picture2->getPath()) {
                    continue;
                }

                if ($this->areImagesSame($picture1, $picture2)) {
                    yield [
                        pathinfo($picture1->getPath(), PATHINFO_BASENAME),
                        pathinfo($picture2->getPath(), PATHINFO_BASENAME)
                    ];
                }
            }

            array_shift($pictures);
        }
    }

    private function areImagesSame(Picture $picture1, Picture $picture2): bool
    {
        /** @var SimilarPictureDetector $detector */
        foreach ($this->detectors as $detector) {
            if (! $detector->accepts($picture1) || ! $detector->accepts($picture2)) {
                continue;
            }

            if (! $detector->arePicturesSimilar($picture1, $picture2)) {
                return false;
            }
        }

        return true;
    }
}
