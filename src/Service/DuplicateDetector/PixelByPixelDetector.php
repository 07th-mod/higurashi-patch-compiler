<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

use Nette\Utils\Image;

class PixelByPixelDetector implements SimilarPictureDetector
{
    public function accepts(Picture $picture): bool
    {
        return true;
    }

    public function arePicturesSimilar(Picture $picture1, Picture $picture2): bool
    {
        $image1 = Image::fromFile($picture1->getPath());
        $image2 = Image::fromFile($picture2->getPath());

        $difference = 0;

        for ($x = 0; $x < $picture1->getWidth(); ++$x) {
            for ($y = 0; $y < $picture2->getHeight(); ++$y) {
                $colors1 = $image1->colorsforindex($image1->colorat($x, $y));
                $colors2 = $image2->colorsforindex($image2->colorat($x, $y));

                $difference += abs($colors1['red'] - $colors2['red']);
                $difference += abs($colors1['green'] - $colors2['green']);
                $difference += abs($colors1['blue'] - $colors2['blue']);
                $difference += abs($colors1['alpha'] - $colors2['alpha']);

                if ($difference > 1000) {
                    return false;
                }
            }
        }

        return true;
    }
}
