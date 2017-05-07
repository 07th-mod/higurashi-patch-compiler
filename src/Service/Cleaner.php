<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Higurashi\Constants;
use Nette\Utils\Strings;

class Cleaner
{
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
    private $fredericaPrefix;

    /**
     * @var string[]
     */
    private $usedFiles = [];

    /**
     * @var string[]
     */
    private $missingFiles = [];

    public function __construct(string $chapter, string $directory)
    {
        $this->chapter = $chapter;
        $this->directory = $directory;
        $this->fredericaPrefix = 'si_' . str_replace('shi', 'si', str_replace('himatsu', 'himatu', $chapter));
    }

    public function clean(): \Generator
    {
        $scriptsDirectory = sprintf('%s/Update', $this->directory);

        $files = glob(sprintf('%s/*.txt', $scriptsDirectory));

        foreach ($files as $file) {
            $this->processScript($file);
        }

        $this->usedFiles = array_unique($this->usedFiles);

        $prefixes = array_merge(Constants::MG_SPRITE_PREFIXES, Constants::PS_ONLY_SPRITE_PREFIXES, ['bg_', '634a47b7-', 'ryuuketu']);

        $filter = function ($filename) use ($prefixes) {
            $filename = strtolower($filename);

            if ($this->isFredericaImage($filename) || Strings::endsWith($filename, '_j.png')) {
                return false;
            }

            foreach ($prefixes as $prefix) {
                if (Strings::startsWith($filename, $prefix)) {
                    return true;
                }
            }

            return false;
        };

        yield from $this->deleteUnusedFiles(sprintf('%s/SE', $this->directory));
        yield from $this->deleteUnusedFiles(sprintf('%s/CG', $this->directory), $filter);
        yield from $this->deleteUnusedFiles(sprintf('%s/CGAlt', $this->directory), $filter);
    }

    public function getMissingFiles(): array
    {
        $files = array_unique($this->missingFiles);
        sort($files);
        return $files;
    }

    private function deleteUnusedFiles(string $directory, ?callable $filter = null): \Generator
    {
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory), \RecursiveIteratorIterator::CHILD_FIRST);
        $prefixLength = strlen($this->directory) + 1;

        foreach ($iterator as $file) {
            if ($file->isDir()) {
                @rmdir($file->getPathname());
                continue;
            }

            if ($filter && !$filter($file->getFilename())) {
                continue;
            }

            $path = str_replace('\\', '/', substr($file->getPathname(), $prefixLength));

            if (! in_array($path, $this->usedFiles, true)) {
                unlink($file->getPathname());
                yield $path;
            }
        }
    }

    private function processScript(string $filename): void
    {
        $file = fopen($filename, 'r');
        @unlink($filename . '.new');
        $output = fopen($filename . '.new', 'w');
        while (!feof($file)) {
            $line = fgets($file);
            if ($line === false) {
                break;
            }
            $line = $this->processLine($line);
            fwrite($output, $line);
        }
        fclose($file);
        fclose($output);
        unlink($filename);
        rename($filename . '.new', $filename);
    }

    private function processLine(string $line): string
    {
        if (preg_match('~^\\s++PlaySE\\([^,]++,\\s*+"([sS][^"]++)"~', $line, $match)) {
            $fixed = $this->processFile($match[1], 'SE/', '.ogg');
            $line = str_replace(sprintf('"%s"', $match[1]), sprintf('"%s"', $fixed), $line);
        } elseif (preg_match('~^\\s++DrawBG\\(\\s*+"([^"]++)"~', $line, $match)) {
            $line = $this->processCGFile($line, $match[1]);
        } elseif (preg_match('~^\\s++DrawScene\\(\\s*+"([^"]++)"~', $line, $match)) {
            $line = $this->processCGFile($line, $match[1]);
        } elseif (preg_match('~^\\s++DrawSceneWithMask\\(\\s*+"([^"]++)"~', $line, $match)) {
            $line = $this->processCGFile($line, $match[1]);
        } elseif (preg_match('~^\\s++DrawBustshot\\([^,]++,\\s*+"([^"]++)"~', $line, $match)) {
            $line = $this->processCGFile($line, $match[1]);
        } elseif (preg_match('~^\\s++DrawBustshotWithFiltering\\([^,]++,\\s*+"([^"]++)"~', $line, $match)) {
            $line = $this->processCGFile($line, $match[1]);
        }

        return $line;
    }

    private function processFile(string $argument, string $prefix, string $suffix, bool $ignoreMissing = false): ?string
    {
        $file = sprintf('%s/%s%s%s', $this->directory, $prefix, $argument, $suffix);

        if (! file_exists($file)) {
            if ($ignoreMissing) {
                return null;
            }
            $this->missingFiles[] = $prefix . substr($file, strlen($this->directory) + 1 + strlen($prefix), - strlen($suffix));
            return $argument;
        }

        $path = str_replace('\\', '/', realpath($file));

        if (strlen($path) !== strlen($file)) {
            throw new \Exception();
        }

        $this->usedFiles[] = substr($path, strlen($this->directory) + 1);

        return substr($path, strlen($this->directory) + 1 + strlen($prefix), strlen($argument));
    }

    private function processCGFile(string $line, string $asset)
    {
        $fixed = $this->processFile($asset, 'CG/', '.png');
        $fixedAlt = $this->processFile($asset, 'CGAlt/', '.png', ! $this->shouldHaveSteamSprite($asset));
        if ($fixedAlt !== null && $fixedAlt !== $fixed) {
            throw new \Exception(sprintf('Filename mismatch "CG/%s" and "CGAlt/%s".', $fixed, $fixedAlt));
        }
        return str_replace(sprintf('"%s"', $asset), sprintf('"%s"', $fixed), $line);
    }

    private function shouldHaveSteamSprite(string $asset): bool
    {
        if (strpos($asset, '/') !== false) {
            $asset = substr($asset, strrpos($asset, '/') + 1);
        }

        $asset = strtolower($asset);

        if ($this->isFredericaImage($asset)) {
            return false;
        }

        foreach (Constants::MG_SPRITE_PREFIXES as $prefix) {
            if (Strings::startsWith($asset, $prefix)) {
                return true;
            }
        }

        return false;
    }

    private function isFredericaImage(string $asset): bool
    {
        return Strings::startsWith(strtolower($asset), $this->fredericaPrefix);
    }
}
