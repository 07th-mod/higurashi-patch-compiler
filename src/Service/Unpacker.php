<?php

declare(strict_types=1);

namespace Higurashi\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use function GuzzleHttp\Promise\unwrap;
use GuzzleHttp\RequestOptions;
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
        if (count($extracted) === 1) {
            $files = glob(sprintf('%s/*', $extracted[0]));
            foreach ($files as $file) {
                $this->filesystem->rename($file, sprintf('%s/%s', $target, pathinfo($file, PATHINFO_BASENAME)));
            }
            $this->filesystem->remove($extracted[0]);
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
