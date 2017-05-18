<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Dibi\Connection;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class AdventureModeUpdater
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
     * @var Connection
     */
    private $connection;

    public function __construct(string $chapter, string $directory, Connection $connection)
    {
        $this->chapter = $chapter;
        $this->directory = $directory;
        $this->connection = $connection;
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

        FileSystem::delete(sprintf('%s/SE', $this->directory));
        FileSystem::delete(sprintf('%s/dev', $this->directory));
        FileSystem::delete(sprintf('%s/README.md', $this->directory));
        FileSystem::copy(__DIR__ . '/../../data/init.txt', sprintf('%s/Update/init.txt', $this->directory));
        FileSystem::copy(__DIR__ . '/../../data/windo_filter.png', sprintf('%s/CG/windo_filter.png', $this->directory));
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

    private function updateLines($file, string $filename): \Generator
    {
        $name = null;
        $clear = false;
        $previousLine = str_replace("\xEF\xBB\xBF", '', fgets($file));

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            if ($match = Strings::match($line, '~^\\s++PlaySE\\([^,]++,\\s*+"([sS][^_][^"]++)"~')) {
                $japanese = $this->connection->query('SELECT [name] FROM [voices] WHERE [voice] = %s', $match[1])->fetchSingle();
                if ($japanese) {
                    $name = $this->connection->query('SELECT * FROM [names] WHERE [japanese] = %s', $japanese)->fetch()->toArray();
                }
            } elseif (Strings::match($previousLine, '~^\\s++OutputLine\\(NULL,~') && Strings::match($line, '~^\\s++NULL,~')) {
                if ($name && $clear) {
                    $previousLine = sprintf(
                        "\t" . 'if (AdvMode) { OutputLine(%s, NULL, %s, NULL, Line_ContinueAfterTyping); }' . "\n" . $previousLine,
                        $this->formatName($name, 'japanese'),
                        $this->formatName($name, 'english')
                    );
                    $name = null;
                } elseif ($clear) {
                    $previousLine = "\t" . 'if (AdvMode) { OutputLineAll("", NULL, Line_ContinueAfterTyping); }' . "\n" . $previousLine;
                }
                $clear = false;
            } elseif (Strings::match($line, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\\\n",\\s*+Line_ContinueAfterTyping\\);$~')) {
                if ($clear) {
                    $line = "\t" . 'if (AdvMode == 0) { OutputLineAll(NULL, "\\n", Line_ContinueAfterTyping); }' . "\n";
                } else {
                    $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                    $line = "\t" . 'if (AdvMode) { ClearMessage(); } else { OutputLineAll(NULL, "\\n", Line_ContinueAfterTyping); }' . "\n";
                    $clear = true;
                    $name = null;
                }
            } elseif (Strings::match($line, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\\\n\\\\n",\\s*+Line_ContinueAfterTyping\\);$~')) {
                if ($clear) {
                    $line = "\t" . 'if (AdvMode == 0) { OutputLineAll(NULL, "\\n\\n", Line_ContinueAfterTyping); }' . "\n";
                } else {
                    $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                    $line = "\t" . 'if (AdvMode) { ClearMessage(); } else { OutputLineAll(NULL, "\\n\\n", Line_ContinueAfterTyping); }' . "\n";
                    $clear = true;
                    $name = null;
                }
            } elseif (Strings::match($line, '~^\\s++ClearMessage\\(\\);$~')) {
                if ($clear) {
                    $line = "\t" . 'if (AdvMode == 0) { ClearMessage(); }' . "\n";
                } else {
                    $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                    $clear = true;
                    $name = null;
                }
            } elseif ($match = Strings::match($line, '~^\\s++(SetDrawingPointOfMessage\\([0-9, ]++\\);)$~')) {
                $line = "\t" . 'if (AdvMode == 0) { ' . $match[1] . ' }' . "\n";
            } elseif ($previousLine === 'void main()' . "\n" && $line === '{' . "\n") {
                $line .= "\t" . 'int AdvMode;' . "\n";
                $line .= "\t" . 'AdvMode = 1;' . "\n";
                $line .= "\t" . 'int Line_ModeSpecific;' . "\n";
                $line .= "\t" . 'if (AdvMode) {' . "\n";
                $line .= "\t\t" . 'Line_ModeSpecific = Line_Normal;' . "\n";
                $line .= "\t\t" . 'OutputLineAll(NULL, "", Line_ContinueAfterTyping);' . "\n";
                $line .= "\t" . '} else {' . "\n";
                $line .= "\t\t" . 'Line_ModeSpecific = Line_WaitForInput;' . "\n";
                $line .= "\t" . '}' . "\n";
            }

            yield $previousLine;
            $previousLine = $line;
        }

        yield $previousLine;
    }

    private function formatName(array $name, string $language): string
    {
        if (! $name['color']) {
            return '"' . $name[$language] . '"';
        }

        return sprintf(
            '"%s%s%s"',
            $name['color'] ? '<color=' . $name['color'] . '>' : '',
            $name[$language],
            $name['color'] ? '</color>' : ''
        );
    }
}
