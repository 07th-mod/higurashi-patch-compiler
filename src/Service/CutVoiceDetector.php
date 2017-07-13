<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class CutVoiceDetector
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

    private function updateLines($file, string $filename): iterable
    {
        $name = null;
        $lines = [];
        $previousLine = str_replace("\xEF\xBB\xBF", '', rtrim(fgets($file))) . "\n";
        $voices = [];

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            if ($match = Strings::match($line, '~^\\s++PlaySE\\(\\s*+([0-9]++)\\s*+,\\s*+"([sS][^_][^"]++)"~')) {
                if (array_key_exists($match[1], $voices)) {
                    echo 'Detected cut voice: ' . $voices[$match[1]] . PHP_EOL;
                }

                $voices[$match[1]] = $match[2];
            } elseif (Strings::match($line, '~Line_(?:Normal|WaitForInput|ModeSpecific)~')) {
                $voices = [];
            }

            $lines[] = $previousLine;
            $previousLine = $line;
        }

        $lines[] = $previousLine;

        return $lines;
    }
}
