<?php

namespace Higurashi\Command;

use Dibi\Connection;
use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Utils\LineProcessorTrait;
use Higurashi\Utils\LineStorage;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class UpgradeTranslation extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:translation:upgrade')
            ->setDescription('Upgrades higurashi translation to newer version.')
            ->addArgument('chapter', InputArgument::REQUIRED)
            ->addArgument('path', InputArgument::REQUIRED)
            ->addOption('force', null, InputOption::VALUE_NONE, 'Redownload all resources.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {

        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        /** @var bool $force */
        $force = $input->getOption('force');

        if (! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $this->runCommand(
            'higurashi:download',
            [
                'chapter' => $chapter,
                '--force' => $force,
            ],
            $output
        );

        $this->runCommand(
            'higurashi:unpack',
            [
                'chapter' => $chapter,
                '--force' => $force,
            ],
            $output
        );

        /** @var string $path */
        $path = $input->getArgument('path');

        if (! is_dir($path)) {
            $output->writeln('Path is not a directory.');

            return 2;
        }

        $options = [];
        $options['dsn'] = 'sqlite:' . __DIR__ . '/../../temp/higurashi.sdb';
        $options['driver'] = 'pdo';
        $options['charset'] = 'utf8';
        $this->connection = new Connection($options);

        $this->connection->query('DROP TABLE IF EXISTS translation');
        $this->connection->query(
            <<<'newdoc'
            CREATE TABLE [translation] (
              [id] INTEGER NOT NULL PRIMARY KEY AUTOINCREMENT,
              [file] CHAR(63) NOT NULL,
              [line] INTEGER NOT NULL,
              [order] INTEGER NOT NULL,
              [japanese] TEXT NOT NULL,
              [translated] TEXT NOT NULL
            );
newdoc
        );

        $this->fillTranslationsDatabase($path);

        $directory = sprintf('%s/%s/%s', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $chapter);

        $this->update($chapter, $directory);

        $output->writeln(sprintf('Missing %d translations.', $this->missing));

        return 0;
    }

    /**
     * @var Connection
     */
    private $connection;

    /**
     * @var int
     */
    private $order;

    /**
     * @var int
     */
    private $lastOrder;

    /**
     * @var int
     */
    private $missing = 0;

    /**
     * @var string
     */
    private $names;

    private function initFile(): void
    {
        $this->order = 0;
    }

    private function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if ($lineNumber === 1) {
            return $line;
        }

        $previousLine = $lines->get(-1);

        if ($match = Strings::match($previousLine, '~^\\s++OutputLine\\(NULL,\\s++"((?:\\\\"|[^"])*+)"~')) {
            $japanese = $match[1];
            $line = Strings::replace(
                $line,
                '~^(\\s++NULL,\\s++")((?:\\\\"|[^"])*+)"~',
                function (array $match) use ($filename, $lineNumber, $japanese) {
                    return $match[1] . $this->findTranslation(pathinfo($filename, PATHINFO_FILENAME), $lineNumber, $japanese) . '"';
                }
            );
        }

        if ($this->names && $match = Strings::match($line, '~OutputLine\\("((?:<color=#[a-f0-9]++>(?:[^<]++)</color>(?:＆)?)*)", NULL, "((?:<color=#[a-f0-9]++>(?:[^<]++)</color>(?: & )?)*)"~')) {
            $japaneseNames = array_column(Strings::matchAll($match[1], '~>([^<]+)</~'), 1);
            $englishNames = array_column(Strings::matchAll($match[2], '~>([^<]+)</~'), 1);

            if (count($japaneseNames) !== count($englishNames)) {
                throw new \Exception('Names count does not match: ' . $line);
            }

            $i = 0;
            foreach ($japaneseNames as $name) {
                $english = $englishNames[$i];

                if (! isset($this->names[$name])) {
                    throw new \Exception('Translation for name not found: ' . $name);
                }

                $translated = $this->names[$name];

                $line = str_replace('>' . $english . '<', '>' . $translated . '<', $line);

                ++$i;
            }
        }

        return $line;
    }

    private function findTranslation(string $filename, int $lineNumber, string $japanese): string
    {
        $this->order += 1;

        $files = [$filename];

        if ($match = Strings::match($filename, '~^z([a-z0-9_]+)_vm~')) {
            $files[] = $match[1];
        }

        $result = $this->connection->query(
            'SELECT [translated], [file], [order] FROM [translation] WHERE [file] IN (%s) AND [japanese] = %s ORDER BY [order]',
            $files,
            $japanese
        )->fetchAll();

        $expectedOrder = $this->lastOrder + 1;

        if (count($result) > 0) {
            if (count($result) === 1) {
                $this->lastOrder = $result[0]->order;

                return $result[0]->translated;
            }

            foreach ($result as $row) {
                if ($row->order === $expectedOrder && $row->file === $filename) {
                    $this->lastOrder = $row->order;

                    return $row->translated;
                }
            }
        }

        $row = $this->connection->query(
            'SELECT [japanese], [translated] FROM [translation] WHERE [file] = %s AND [order] = %i',
            $filename,
            $expectedOrder
        )->fetch();

        if ($row) {
            if (Strings::length($row->japanese) > 5 && levenshtein($row->japanese, $japanese) <= 6) {
                $this->lastOrder = $expectedOrder;

                return $row->translated;
            }
        }

        ++$this->missing;

        return '<missing translation>';
    }

    private function fillTranslationsDatabase(string $path): void
    {
        $files = glob(sprintf('%s/*.txt', $path));

        foreach ($files as $file) {
            $lines = iterator_to_array($this->generateLines($file));
            $order = 1;
            foreach ($lines as $i => $line) {
                $order = $this->loadTranslationFromLine($file, $i, $order, $line, $lines);
            }
        }

        if (! $files) {
            die('No txt files found in directory.');
        }

        printf('Loaded translations from %d files.' . PHP_EOL, count($files));
    }

    private function generateLines(string $filename): \Generator
    {
        $file = fopen($filename, 'r');

        $i = 1;
        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = str_replace("\xEF\xBB\xBF", '', rtrim($line)) . "\n";
            yield $i => $line;
            ++$i;
        }

        fclose($file);
    }

    private function loadTranslationFromLine(string $file, int $i, int $order, string $line, array $lines)
    {
        if ($match = Strings::match($line, '~^\\s++OutputLine\\(NULL,\\s++"((?:\\\\"|[^"])*+)"~')) {
            $japanese = $match[1];
            $match = Strings::match($lines[$i+1], '~^\\s++NULL,\\s++"((?:\\\\"|[^"])*+)"~');

            if (! $match) {
                throw new \Exception($file . ':' . $i);
            }

            $translated = $match[1];

            $this->connection->insert(
                'translation',
                [
                    'file' => pathinfo($file, PATHINFO_FILENAME),
                    'line' => $i,
                    'order' => $order,
                    'japanese' => $japanese,
                    'translated' => $translated,
                ]
            )->execute();

            return $order + 1;
        }

        if ($match = Strings::match($line, '~OutputLine\\("((?:<color=#[a-f0-9]++>(?:[^<]++)</color>(?:＆)?)*)", NULL, "((?:<color=#[a-f0-9]++>(?:[^<]++)</color>(?: & )?)*)"~')) {
            $japaneseNames = array_column(Strings::matchAll($match[1], '~>([^<]+)</~'), 1);
            $translatedNames = array_column(Strings::matchAll($match[2], '~>([^<]+)</~'), 1);

            if (count($japaneseNames) !== count($translatedNames)) {
                throw new \Exception('Names count does not match: ' . $line);
            }

            $i = 0;
            foreach ($japaneseNames as $name) {
                $translated = $translatedNames[$i];

                if (isset($this->names[$name]) && $this->names[$name] !== $translated) {
                    throw new \Exception('Name translation mismatch: ' . $name);
                }

                $this->names[$name] = $translated;

                ++$i;
            }
        }

        return $order;
    }
}
