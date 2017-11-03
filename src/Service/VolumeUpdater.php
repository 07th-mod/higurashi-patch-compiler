<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class VolumeUpdater
{
    /**
     * @var string
     */
    private $chapter;

    /**
     * @var string
     */
    private $directory;

    public function __construct(string $chapter, string $directory)
    {
        $this->chapter = $chapter;
        $this->directory = $directory;
    }

    public function copyFiles(): void
    {
        FileSystem::delete($this->directory);
        FileSystem::copy(sprintf('%s/unpack/%s_%s', TEMP_DIR, $this->chapter, 'patch'), $this->directory);
    }

    public function update(): void
    {
        $scriptsDirectory = sprintf('%s/Update', $this->directory);

        $files = glob(sprintf('%s/*.txt', $scriptsDirectory));

        foreach ($files as $file) {
            $this->processScript($file);
        }
    }

    private function processScript(string $filename): void
    {
        $file = fopen($filename, 'r');
        @unlink($filename . '.new');
        $output = fopen($filename . '.new', 'w');
        foreach ($this->updateLines($file, $filename) as $line) {
            fwrite($output, $line);
        }
        fclose($file);
        fclose($output);
        unlink($filename);
        rename($filename . '.new', $filename);
    }

    private const VOLUMES = [
        // Rena is lauder than other characters on PS2.
        'ps2/s03/02' => 220,
        // But strangely enough she has correct volume in PS2 omake.
        'ps2/s20/02' => 128,
        // PS2 voices are much quiter than PS3 so we need to boost the volume.
        'ps2/s' => 240,
        's0' => 128,
        's1' => 128,
    ];

    private function getVolume(string $audio): int
    {
        foreach (self::VOLUMES as $directory => $volume) {
            if (Strings::startsWith($audio, $directory)) {
                return $volume;
            }
        }

        return 128;
    }

    private function updateLines($file, string $filename): iterable
    {
        $name = null;
        $lines = [];
        $previousLine = str_replace("\xEF\xBB\xBF", '', rtrim(fgets($file))) . "\n";

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            if ($match = Strings::match($line, '~^\\s++PlaySE\\(\\s*+([0-9]++)\\s*+,\\s*+"((?:ps2\\/)?[sS][^_][^"]++)",\\s*+[0-9]++,\\s*+([^)]+)\\);$~')) {
                $line = "\t" . sprintf('PlaySE(%d, "%s", %d, %s);', $match[1], $match[2], $this->getVolume($match[2]), $match[3]) . "\n";
            }

            $lines[] = $previousLine;
            $previousLine = $line;
        }

        $lines[] = $previousLine;

        return $lines;
    }
}
