<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\Finder;
use Nette\Utils\Image;

class Compressor
{
    /**
     * @var string
     */
    private $directory;

    public function __construct(string $directory)
    {
        $this->directory = $directory;
    }

    public function compressImages(string $directory): \Generator
    {
        $files = Finder::findFiles('*.png')->from(sprintf('%s/%s', $this->directory, $directory));
        $count = count($files);

        $i = 0;
        foreach ($files as $file) {
            set_time_limit(300);

            $image = Image::fromFile($file->getPathname());
            $image->save($file->getPathname());
            $image->destroy();

            yield [++$i, $count];
        }
    }
}
