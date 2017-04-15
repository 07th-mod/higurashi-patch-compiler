<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Symfony\Component\Filesystem\Filesystem;

class Unpacker
{
    /**
     * @var Filesystem
     */
    private $filesystem;

    /**
     * @var bool
     */
    private $force;

    /**
     * @var \Generator
     */
    private $writer;

    public function __construct(Filesystem $filesystem, bool $force, \Generator $writer)
    {
        $this->filesystem = $filesystem;
        $this->force = $force;
        $this->writer = $writer;
    }

    public function unpackIfNeeded(string $source, string $target): void
    {
        $file = pathinfo($target, PATHINFO_BASENAME);

        if ($this->filesystem->exists($target) && ! $this->force) {
            $this->writer->send(sprintf('Skipping "%s", directory already exists.', $file));

            return;
        }

        $this->filesystem->remove($target);
        $this->filesystem->mkdir($target);

        $this->unpack($source, $target);

        $extracted = glob(sprintf('%s/*', $target));
        while (count($extracted) === 1 && pathinfo($extracted[0], PATHINFO_BASENAME) !== 'CGAlt') {
            $files = glob(sprintf('%s/*', $extracted[0]));
            foreach ($files as $file) {
                $name = pathinfo($file, PATHINFO_BASENAME);
                if ($name === pathinfo($extracted[0], PATHINFO_BASENAME)) {
                    $name .= '_temp';
                }
                $this->filesystem->rename($file, sprintf('%s/%s', $target, $name));
            }
            $this->filesystem->remove($extracted[0]);
            $extracted = glob(sprintf('%s/*', $target));
        }
    }

    public function unpackMultipleIfNeeded(array $sources, string $target)
    {
        $file = pathinfo($target, PATHINFO_BASENAME);

        if ($this->filesystem->exists($target) && ! $this->force) {
            $this->writer->send(sprintf('Skipping "%s", directory already exists.', $file));

            return;
        }

        $this->filesystem->remove($target);
        $this->filesystem->mkdir($target);

        foreach ($sources as $source) {
            $this->unpack($source, $target);
        }
    }

    private function unpack(string $source, string $target)
    {
        $file = pathinfo($source, PATHINFO_BASENAME);
        $zip = new \ZipArchive();

        $result = $zip->open($source);
        if ($result === true) {
            $this->writer->send(sprintf('Unpacking "%s".', $file));
            $zip->extractTo($target);
            $zip->close();
        } else {
            throw new \Exception(sprintf('Unable to unpack "%s", error code "%s".', $file, $result));
        }
    }
}
