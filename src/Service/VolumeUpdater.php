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

    // Disabled for now because current patches are not using different volume for ps2 voices anyway.
    private const VOLUMES = [
        // Rena is lauder than other characters on PS2.
        //'ps2/s03/02' => 220,
        // But strangely enough she has correct volume in PS2 omake.
        //'ps2/s20/02' => 128,
        // PS2 voices are much quieter than PS3 so we need to boost the volume.
        //'ps2/s' => 240,
    ];

    private function getVolume(string $audio): int
    {
        foreach (self::VOLUMES as $directory => $volume) {
            if (Strings::startsWith($audio, $directory)) {
                return $volume;
            }
        }

        return 256;
    }

    private function updateLines($file, string $filename): iterable
    {
        $name = null;
        $lines = [];
        $previousLine = str_replace("\xEF\xBB\xBF", '', rtrim(fgets($file))) . "\n";

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            $line = Strings::replace(
                $line,
                '~Play(BGM|SE)\\(\\s*+([0-9]++)\\s*+,\\s*+"([^"]++)",\\s*+[0-9]++\\s*+,\\s*+([^;]*);~',
                static function (array $matches) {
                    return sprintf(
                        'Play%s( %d, "%s", %d, %d );',
                        $matches[1],
                        $matches[2],
                        $matches[3],
                        56,
                        $matches[4]
                    );
                }
            );

            if ($match = Strings::match($line, '~^(\s++)ModPlayVoiceLS\\(\\s*+([0-9]++)\\s*+,\\s*+([0-9]++)\\s*+,\\s*+"([^"]++)"\\s*+,\\s*+[0-9]++\\s*+,\\s*+(.*)$~')) {
                $line = $match[1] . sprintf('ModPlayVoiceLS(%d, %d, "%s", %d, %s', $match[2], $match[3], $match[4], $this->getVolume($match[4]), $match[5]) . "\n";
            }

            $lines[] = $previousLine;
            $previousLine = $line;
        }

        $lines[] = $previousLine;

        return $lines;
    }
}
