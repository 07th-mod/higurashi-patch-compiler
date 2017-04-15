<?php

declare(strict_types=1);

namespace Higurashi\Service;

use GuzzleHttp\Client;
use GuzzleHttp\Promise\Promise;
use function GuzzleHttp\Promise\unwrap;
use GuzzleHttp\RequestOptions;
use Symfony\Component\Filesystem\Filesystem;

class Downloader
{
    /**
     * @var Client
     */
    private $client;

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

    /**
     * @var Promise[]
     */
    private $promises = [];

    public function __construct(Client $client, Filesystem $filesystem, bool $force, \Generator $writer)
    {
        $this->client = $client;
        $this->filesystem = $filesystem;
        $this->force = $force;
        $this->writer = $writer;
    }

    public function startDownloadIfNeeded(string $url, string $path): void
    {
        if ($this->filesystem->exists($path) && ! $this->force) {
            $this->writer->send(sprintf('Skipping "%s", file already exists.', $url));

            return;
        }

        $this->filesystem->mkdir(dirname($path));
        $this->filesystem->remove($path);

        $progress = 0;

        $this->promises[] = $this->client->getAsync(
            $url,
            [
                RequestOptions::SINK => $path,
                RequestOptions::PROGRESS => function ($downloadSizeTotal, $downloadSize) use ($url, &$progress) {
                    if ($downloadSizeTotal === 0) {
                        return;
                    }

                    $newProgress = round($downloadSize / $downloadSizeTotal * 100);

                    if ($newProgress === $progress) {
                        return;
                    }

                    $progress = $newProgress;

                    $this->writer->send(sprintf('Downloading (%d%%): %s.', $progress, $url));
                }
            ]
        );

        $this->writer->send(sprintf('Downloading (0%%): %s.', $url));
    }

    public function save(): void
    {
        unwrap($this->promises);

        $this->writer->send('Finished downloading.');
    }
}
