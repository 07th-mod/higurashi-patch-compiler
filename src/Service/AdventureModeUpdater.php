<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Dibi\Connection;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class AdventureModeUpdater
{
    public const SCREEN_LIMIT = 215;

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
            if (in_array(pathinfo($file, PATHINFO_BASENAME), ['chapterselect.txt', 'dummy.txt', 'flow.txt', 'init.txt'], true)) {
                continue;
            }

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

    private function updateLines($file, string $filename): iterable
    {
        $name = null;
        $clear = true;
        $characters = 0;
        $lines = [];
        $i = 0;
        $lastOutputLineIndex = null;
        $longLines = [];
        $previousLine = str_replace("\xEF\xBB\xBF", '', rtrim(fgets($file))) . "\n";
        $firstOutput = true;

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            if ($match = Strings::match($line, '~^\\s++PlaySE\\([^,]++,\\s*+"([sS][^_][^"]++)"~')) {
                $japanese = $this->connection->query('SELECT [name] FROM [voices] WHERE [voice] = %s', $match[1])->fetchSingle();
                if ($japanese) {
                    $name = $this->connection->query('SELECT * FROM [names] WHERE [japanese] = %s', $japanese)->fetch()->toArray();
                } else {
                    $japanese = $this->connection->query('SELECT [name] FROM [voices] WHERE [voice] LIKE %s', '%' . $match[1] . '%')->fetchSingle();
                    if ($japanese) {
                        $japaneseNames = explode("\u{FF06}", $japanese);
                        $name = [];
                        foreach ($japaneseNames as $search) {
                            $row = $this->connection->query('SELECT * FROM [names] WHERE [japanese] = %s', $search)->fetch();
                            if (! $row) {
                                $name = [$this->connection->query('SELECT * FROM [names] WHERE [japanese] = %s', $japanese)->fetch()->toArray()];
                                break;
                            }
                            $name[] = $row->toArray();
                        }
                    }
                }
            } elseif (Strings::match($previousLine, '~^\\s++OutputLine\\(NULL,~') && $match = Strings::match($line, '~^\\s++NULL,\\s++"((?:\\\\"|[^"])*+)"~')) {
                $englishText = $match[1];
                $length = Strings::length($englishText);

                if ($length > self::SCREEN_LIMIT) {
                    $longLines[] = $i;
                }

                if (
                    $characters > 0
                    && ($characters + $length) > self::SCREEN_LIMIT
                    && $match = Strings::match($lines[$lastOutputLineIndex + 1], '~("\\s*+,\\s++Line_WaitForInput)~')
                ) {
                    $lines[$lastOutputLineIndex + 1] = str_replace($match[1], ' ", Line_ModeSpecific', $lines[$lastOutputLineIndex + 1]);
                    $line = str_replace($englishText, ltrim($englishText), $line);
                    $characters = 0;
                    $clear = true;
                }
                $characters += $length;

                if ($name && $clear) {
                    $previousLine = sprintf(
                        "\t" . 'if (AdvMode) { OutputLine(%s, NULL, %s, NULL, Line_ContinueAfterTyping); }' . "\n" . $previousLine,
                        '"' . ColorsUpdater::formatName($name, 'japanese') . '"',
                        '"' . ColorsUpdater::formatName($name, 'english') . '"'
                    );
                    $name = null;
                } elseif ($clear) {
                    $previousLine = "\t" . 'if (AdvMode) { OutputLineAll("", NULL, Line_ContinueAfterTyping); }' . "\n" . $previousLine;
                }

                if ($firstOutput) {
                    $previousLine = "\t" . 'ClearMessage();' . "\n" . $previousLine;
                    $firstOutput = false;
                }

                $lastOutputLineIndex = $i;
                $clear = false;
            } elseif ($match = Strings::match($line, '~^\\s++OutputLineAll\\(NULL,\\s*+"\\s*+((?:\\\\n)++)",\\s*+Line_ContinueAfterTyping\\);$~')) {
                if ($clear) {
                    $line = "\t" . 'if (AdvMode == 0) { OutputLineAll(NULL, "' . $match[1] . '", Line_ContinueAfterTyping); }' . "\n";
                } else {
                    if (strpos($previousLine, 'Line_') !== false) {
                        $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                    } else {
                        $lines[$lastOutputLineIndex + 1] = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $lines[$lastOutputLineIndex + 1]);
                    }
                    $line = "\t" . 'if (AdvMode) { ClearMessage(); } else { OutputLineAll(NULL, "' . $match[1] . '", Line_ContinueAfterTyping); }' . "\n";
                    $clear = true;
                    $characters = 0;
                    $name = null;
                }
            } elseif (Strings::match($line, '~^\\s++ClearMessage\\(\\);$~')) {
                if ($clear) {
                    $line = "\t" . 'if (AdvMode == 0) { ClearMessage(); }' . "\n";
                } else {
                    $previousLine = str_replace('Line_WaitForInput', 'Line_ModeSpecific', $previousLine);
                    $clear = true;
                    $characters = 0;
                    $name = null;
                }
            } elseif ($match = Strings::match($line, '~^\\s++(SetDrawingPointOfMessage\\([0-9, ]++\\);)$~')) {
                $line = "\t" . 'if (AdvMode == 0) { ' . $match[1] . ' }' . "\n";
            } elseif ($previousLine === 'void main()' . "\n" && $line === '{' . "\n" && pathinfo($filename, PATHINFO_BASENAME) !== 'flow.txt') {
                $line .= "\t" . 'int AdvMode;' . "\n";
                $line .= "\t" . 'AdvMode = 1;' . "\n";
                $line .= "\t" . 'int Line_ModeSpecific;' . "\n";
                $line .= "\t" . 'if (AdvMode) {' . "\n";
                $line .= "\t\t" . 'Line_ModeSpecific = Line_Normal;' . "\n";
                $line .= "\t" . '} else {' . "\n";
                $line .= "\t\t" . 'Line_ModeSpecific = Line_WaitForInput;' . "\n";
                $line .= "\t" . '}' . "\n";
            }

            $lines[] = $previousLine;
            ++$i;
            $previousLine = $line;
        }

        foreach ($longLines as $index) {
            list($prefixLine, $japaneseLine) = explode("\n", $lines[$index]);
            $prefixLine = substr($prefixLine, 16, -2);
            $englishLine = $lines[$index + 1];
            $nvlLine = "\t" . $japaneseLine . "\n\t" . $englishLine;
            $lines[$index] = "\t" . 'if (AdvMode) {' . "\n\t\t" . $prefixLine . "\n\t" . $japaneseLine . "\n";
            $match = Strings::match($englishLine, '~^\\s++NULL,\\s++"((?:\\\\"|[^"])*+)"~');
            $englishLine = "\t" . str_replace($match[1], '<size=-2>' . $match[1] . '</size>', $englishLine);
            $lines[$index + 1] = $englishLine . "\t" . '} else {' . "\n" . $nvlLine . "\t" . '}' . "\n";
        }

        $lines[] = $previousLine;

        return $lines;
    }
}
