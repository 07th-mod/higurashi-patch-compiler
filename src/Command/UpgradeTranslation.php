<?php

namespace Higurashi\Command;

use Dibi\Connection;
use Higurashi\Constants;
use Higurashi\Helpers;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class UpgradeTranslation extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:translation:upgrade')
            ->setDescription('Upgrades higurashi translation to newer version.')
            ->addArgument('chapter', InputArgument::REQUIRED)
            ->addArgument('path', InputArgument::REQUIRED);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        if ($chapter) {
            $chapter = Helpers::guessChapter($chapter);
        }

        if ($chapter && ! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        /** @var string $path */
        $path = $input->getArgument('path');

        if (! is_dir($path)) {
            $output->writeln('Path is not a directory.');

            return 2;
        }

        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../config/local.yml'));

        $options = $config['database'];
        $options['driver'] = 'pdo';
        $options['charset'] = 'utf8';
        $connection = new Connection($options);

        $connection->query('TRUNCATE TABLE translation RESTART IDENTITY');

        $this->fillTranslationsDatabase($connection, $path);

        return 0;
    }

    private function fillTranslationsDatabase(Connection $connection, string $path): void
    {
        $files = glob(sprintf('%s/*.txt', $path));

        foreach ($files as $file) {
            $lines = iterator_to_array($this->generateLines($file));
            $order = 1;
            foreach ($lines as $i => $line) {
                $order = $this->loadTranslationFromLine($connection, $file, $i, $order, $line, $lines);
            }
        }
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

    private function loadTranslationFromLine(Connection $connection, string $file, int $i, int $order, string $line, array $lines)
    {
        if ($match = Strings::match($line, '~^\\s++OutputLine\\(NULL,\\s++"((?:\\\\"|[^"])*+)"~')) {
            $japanese = $match[1];
            $match = Strings::match($lines[$i+1], '~^\\s++NULL,\\s++"((?:\\\\"|[^"])*+)"~');

            if (! $match) {
                throw new \Exception($file . ':' . $i);
            }

            $translated = $match[1];

            $connection->insert(
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

        return $order;
    }
}
