<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\Finder;
use Nette\Utils\Image;
use Nette\Utils\Strings;
use Symfony\Component\Filesystem\Filesystem;

class Patch
{
    /**
     * @var Filesystem
     */
    private $filesystem;
    
    /**
     * @var string
     */
    private $chapter;

    /**
     * @var string
     */
    private $directory;

    public function __construct(Filesystem $filesystem, string $chapter, string $directory)
    {
        $this->filesystem = $filesystem;
        $this->chapter = $chapter;
        $this->directory = $directory;
    }

    public function initialize(): void
    {
        $this->filesystem->remove($this->directory);
        $this->filesystem->mkdir($this->directory);
    }

    public function copyGraphics(): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'graphics'), $this->directory . '/CG');
    }

    public function copyVoicePatch(): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'patch'), $this->directory);
        $this->delete('README.md');
        $this->delete('CHANGELOG.md');
        $this->delete('Extras');
        $this->delete('Screenshots');
        $this->delete('dev');
        $this->filesystem->remove(sprintf('%s/Update/init.txt', $this->directory));
        //$this->filesystem->copy(__DIR__ . '/../../data/init.txt', sprintf('%s/Update/init.txt', $this->directory));
    }

    public function copyVoices(string $voices): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/voices/%s', TEMP_DIR, $voices), sprintf('%s/SE/%s', $this->directory, $voices));
    }

    public function delete(string $file): void
    {
        $this->filesystem->remove(sprintf('%s/%s', $this->directory, $file));
    }

    public function copySteamPatch(): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'steam'), $this->directory);
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

    private function mergeDirectory(string $source, string $dest): void
    {
        if (! is_dir($source)) {
            $this->filesystem->remove($dest);
            $this->filesystem->copy($source, $dest);

            return;
        }

        $this->filesystem->mkdir($dest);

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                $source,
                \RecursiveDirectoryIterator::SKIP_DOTS
            ),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($iterator as $item) {
            if ($item->isDir()) {
                $this->filesystem->mkdir($dest . '/' . $iterator->getSubPathName());
            } else {
                $this->mergeDirectory($item->getPathname(), $dest . '/' . $iterator->getSubPathName());
            }
        }
    }
}
