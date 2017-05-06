<?php

declare(strict_types=1);

namespace Higurashi\Service\DuplicateDetector;

class Picture
{
    /**
     * @var string
     */
    private $path;

    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    private $height;

    public function __construct(string $path)
    {
        $this->path = $path;
        list($this->width, $this->height) = getimagesize($path);
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function getWidth(): int
    {
        return $this->width;
    }

    public function getHeight(): int
    {
        return $this->height;
    }
}