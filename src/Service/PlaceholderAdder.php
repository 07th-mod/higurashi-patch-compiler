<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class PlaceholderAdder
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
        $i = 0;
        $quote = false;
        $ignoreNext = false;

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            if (strpos($line, 'OutputLine(') !== false) {
                if ($match = Strings::match($line, '~^\\s++OutputLine\\(NULL,\\s++"([^"]++)"~')) {
                    $text = Strings::trim($match[1]);

                    if (Strings::startsWith($text, '「')) {
                        $quote = true;
                    }

                    if (
                        $quote
                        && ! Strings::match($previousLine, '~^\\s++PlaySE\\([^,]++,\\s*+"[sS][^_][^"]++"~')
                        && ! Strings::match($lines[$i - 1], '~^\\s++PlaySE\\([^,]++,\\s*+"[sS][^_][^"]++"~')
                        && ! (
                            Strings::match($lines[$i - 2], '~^\\s++PlaySE\\([^,]++,\\s*+"[sS][^_][^"]++"~')
                            && Strings::match($lines[$i - 1], '~ClearMessage~')
                        )
                        && ! $ignoreNext
                    ) {
                        $line = "\tPlaySE(4, \"\", 128, 64);\n" . $line;
                    }

                    if (Strings::endsWith($text, '」')) {
                        $quote = false;
                    }
                }

                $ignoreNext = false;
            }

            if (
                strpos($previousLine, 'OutputLine(') !== false
                && strpos($line, 'Line_Continue') !== false
            ) {
                $ignoreNext = true;
            }

            $lines[] = $previousLine;
            $previousLine = $line;
            ++$i;
        }

        $lines[] = $previousLine;

        return $lines;
    }
}