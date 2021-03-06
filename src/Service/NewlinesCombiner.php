<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class NewlinesCombiner
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

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            if (
                ($match = Strings::match($line, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\s*+((?:\\\\n)++)",\\s*+Line_ContinueAfterTyping\\);$~'))
                && ($matchPrevious = Strings::match($previousLine, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\s*+((?:\\\\n)++)",\\s*+Line_ContinueAfterTyping\\);$~'))
            ) {
                $previousLine = "\t" . 'OutputLineAll(NULL, "' . $match[1] . $matchPrevious[1] . '", Line_ContinueAfterTyping);' . "\n";
            } else {
                $lines[] = $previousLine;
                $previousLine = $line;
            }
        }

        $lines[] = $previousLine;

        return $lines;
    }
}
