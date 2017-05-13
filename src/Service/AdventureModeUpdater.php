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

        FileSystem::copy(__DIR__ . '/../../data/init.txt', sprintf('%s/Update/init.txt', $this->directory));
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
        $previousLine = fgets($file);

        while (!feof($file) && ($line = fgets($file)) !== false) {
            if ($match = Strings::match($line, '~^\\s++PlaySE\\([^,]++,\\s*+"([sS][^_][^"]++)"~')) {
                $japanese = $this->connection->query('SELECT [name] FROM [voices] WHERE [voice] = %s', $match[1])->fetchSingle();
                if ($japanese) {
                    $name = $this->connection->query('SELECT * FROM [names] WHERE [japanese] = %s', $japanese)->fetch()->toArray();
                }
            } elseif ($name && Strings::match($previousLine, '~^\\s++OutputLine\\(NULL,~') && Strings::match($line, '~^\\s++NULL,~')) {
                $previousLine = str_replace('NULL', $this->formatName($name, 'japanese'), $previousLine);
                $line = str_replace('NULL', $this->formatName($name, 'english'), $line);
                $name = null;
            } elseif (Strings::match($line, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\\\n",\\s*+Line_ContinueAfterTyping\\);$~')) {
                $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                $line = "\t" . 'if (AdvMode) { ClearMessage(); } OutputLineAll(NULL, "\\n", Line_ContinueAfterTyping);' . "\n";
            } elseif (Strings::match($line, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\\\n\\\\n",\\s*+Line_ContinueAfterTyping\\);$~')) {
                $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                $line = "\t" . 'if (AdvMode) { ClearMessage(); OutputLineAll(NULL, "", Line_ContinueAfterTyping); } else { OutputLineAll(NULL, "\\n\\n", Line_ContinueAfterTyping); }' . "\n";
            } elseif (Strings::match($line, '~^\\s++ClearMessage\\(\\);\\s++$~')) {
                $line = "\t" . 'ClearMessage(); if (AdvMode) { OutputLineAll(NULL, "", Line_ContinueAfterTyping); }' . "\n";
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
            $name['japanese'],
            $name['color'] ? '</color>' : ''
        );
    }
}
