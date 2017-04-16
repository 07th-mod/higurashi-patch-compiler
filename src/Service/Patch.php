<?php

declare(strict_types=1);

namespace Higurashi\Service;

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

    /**
     * @var string
     */
    private $gameDirectory;

    public function __construct(Filesystem $filesystem, string $chapter, string $directory, string $gameDirectory)
    {
        $this->filesystem = $filesystem;
        $this->chapter = $chapter;
        $this->directory = $directory;
        $this->gameDirectory = $gameDirectory;
    }

    public function initialize(): void
    {
        $this->filesystem->remove($this->directory);
        $this->filesystem->mkdir($this->directory);
    }

    public function copyGraphics(): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'graphics'), $this->directory);
    }

    public function copyVoicePatch(): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'patch'), $this->directory);
        $this->delete('README.md');
    }

    public function copyVoices(string $voices): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/voices/%s', TEMP_DIR, $voices), sprintf('%s/SE/%s', $this->directory, $voices));
    }

    public function delete(string $file): void
    {
        $this->filesystem->remove(sprintf('%s/%s', $this->directory, $file));
    }

    public function renameGraphicsDirectory(): void
    {
        $this->filesystem->rename(sprintf('%s/CGAlt', $this->directory), sprintf('%s/CG', $this->directory));
    }

    public function copyGameCG(): void
    {
        $this->mergeDirectory(sprintf('%s/%s', $this->gameDirectory, 'CG'), sprintf('%s/CGAlt', $this->directory));
    }

    public function copyGameCGAlt(): void
    {
        $this->mergeDirectory(sprintf('%s/%s', $this->gameDirectory, 'CGAlt'), sprintf('%s/CGAlt', $this->directory));
    }

    public function copySteamPatch(): void
    {
        $this->mergeDirectory(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'steam'), $this->directory);
        $this->delete('README.txt');
    }

    public function useAlternativeChieSprites(string $directory): void
    {
        // The directory name is slightly different for different chapters.
        $this->mergeDirectory(sprintf('%s/CGAlt/%s', $this->directory, $directory), sprintf('%s/CGAlt', $this->directory));
        $this->delete(sprintf('CGAlt/%s', $directory));
    }

    public function compressImages(string $directory): void
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(sprintf('%s/%s', $this->directory, $directory)));

        foreach ($iterator as $file) {
            if ($file->isDir() || !Strings::endsWith($file->getPathname(), '.png')) {
                continue;
            }

            $image = Image::fromFile($file->getPathname());
            $image->save($file->getPathname());
            $image->destroy();
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
