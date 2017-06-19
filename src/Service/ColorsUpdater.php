<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Dibi\Connection;
use Nette\Utils\FileSystem;
use Nette\Utils\Strings;

class ColorsUpdater
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
        $i = 0;
        $lastOutputLineIndex = null;
        $previousLine = str_replace("\xEF\xBB\xBF", '', rtrim(fgets($file))) . "\n";

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = rtrim($line) . "\n";

            //if (Strings::match($previousLine, '~^\\s++OutputLine\\(NULL,~') && $match = Strings::match($line, '~^\\s++NULL,\\s++"((?:\\\\"|[^"])*+)"~')) {
            //if (AdvMode) { OutputLine("<color=#b1aab6>大石</color>", NULL, "<color=#b1aab6>Ooishi</color>", NULL, Line_ContinueAfterTyping); }
            if ($match = Strings::match($line, '~OutputLine\\("([^"]*+)",\\s*+NULL,\\s*+"([^"]*+)",\\s*+NULL,\\s*+[a-zA-Z_]++\\);~')) {
                $japaneseNames = Strings::matchAll($match[1], '~<color=#[a-z0-9]{6}>([^<]++)</color>~');
                $englishNames = Strings::matchAll($match[2], '~<color=#[a-z0-9]{6}>([^<]++)</color>~');

                if (count($japaneseNames) !== count($englishNames)) {
                    throw new \Exception();
                }

                foreach ($japaneseNames as $nameMatch) {
                    $name = $this->connection->query('SELECT * FROM [names] WHERE [japanese] = %s', $nameMatch[1])->fetch()->toArray();
                    $line = str_replace($nameMatch[0], self::formatName($name, 'japanese'), $line);
                }

                foreach ($englishNames as $nameMatch) {
                    $name = $this->connection->query('SELECT * FROM [names] WHERE [english] = %s', $nameMatch[1])->fetch()->toArray();
                    $line = str_replace($nameMatch[0], self::formatName($name, 'english'), $line);
                }
            }

            $lines[] = $previousLine;
            ++$i;
            $previousLine = $line;
        }

        $lines[] = $previousLine;

        return $lines;
    }

    public static function formatName(array $name, string $language): string
    {
        if (! isset($name['color'])) {
            $names = [];
            foreach ($name as $value) {
                $names[] = self::formatName($value, $language);
            }
            return implode($language === 'japanese' ? "\u{FF06}" : ' & ', $names);
        }

        if (! $name['color']) {
            return $name[$language];
        }

        if (isset($name['color_mg'])) {
            $color1 = sscanf($name['color'], '#%02x%02x%02x');
            $color2 = sscanf($name['color_mg'], '#%02x%02x%02x');
            $color = sprintf(
                "#%02x%02x%02x",
                round(($color1[0] + $color2[0]) / 2),
                round(($color1[1] + $color2[1]) / 2),
                round(($color1[2] + $color2[2]) / 2)
            );
        } else {
            $color = $name['color'];
        }

        return sprintf(
            '%s%s%s',
            $color ? '<color=' . $color . '>' : '',
            $name[$language],
            $color ? '</color>' : ''
        );
    }
}
