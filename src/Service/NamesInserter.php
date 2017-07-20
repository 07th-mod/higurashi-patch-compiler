<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Dibi\Connection;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class NamesInserter
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
            $filename = pathinfo($file, PATHINFO_BASENAME);

            if (
                in_array($filename, ['chapterselect.txt', 'dummy.txt', 'flow.txt', 'init.txt'], true)
                || substr($filename, 0, 1) === '&'
            ) {
                continue;
            }

            $this->processScript($file);
        }

        FileSystem::delete(sprintf('%s/SE', $this->directory));
        FileSystem::delete(sprintf('%s/dev', $this->directory));
        FileSystem::delete(sprintf('%s/README.md', $this->directory));
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

    private const DIRECTORIES = [
        'Keiichi' => [
            's03/01',
        ],
        'Rena' => [
            's03/02',
        ],
        'Mion' => [
            's03/03',
        ],
        'Satoko' => [
            's03/04',
        ],
        'Rika' => [
            's03/06',
        ],
        'Irie' => [
            's03/10',
        ],
    ];

    private function updateLines($file, string $filename): iterable
    {
        $name = null;
        $lines = [];
        $i = 0;
        $lastOutputLineIndex = null;
        $previousLine = str_replace("\xEF\xBB\xBF", '', rtrim(fgets($file))) . "\n";

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
                    } else {
                        $directory = substr($match[1], 0, 6);
                        $english = null;
                        foreach (self::DIRECTORIES as $english => $directories) {
                            if (in_array($directory, $directories, true)) {
                                $name = $this->connection->query('SELECT * FROM [names] WHERE [english] = %s', $english)->fetch()->toArray();
                                break;
                            }
                        }
                    }
                }

                if ($name && $previousLine === "\t" . 'if (AdvMode) { OutputLineAll("", NULL, Line_ContinueAfterTyping); }' . "\n") {
                    $previousLine = sprintf(
                        "\t" . 'if (AdvMode) { OutputLine(%s, NULL, %s, NULL, Line_ContinueAfterTyping); }' . "\n",
                        '"' . ColorsUpdater::formatName($name, 'japanese') . '"',
                        '"' . ColorsUpdater::formatName($name, 'english') . '"'
                    );
                }

                $name = null;
            }

            $lines[] = $previousLine;
            ++$i;
            $previousLine = $line;
        }

        $lines[] = $previousLine;

        return $lines;
    }
}
