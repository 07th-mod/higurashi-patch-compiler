<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Dibi\Connection;
use Higurashi\Service\ScriptParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class Names extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:names')
            ->setDescription('Loads names into database.')
            ->addArgument('script', InputArgument::REQUIRED, 'Path to the names file.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $script */
        $script = $input->getArgument('script');

        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../config/local.yml'));

        $options = $config['database'];
        $options['driver'] = 'pdo';
        $options['charset'] = 'utf8';
        $connection = new Connection($options);

        $connection->query('TRUNCATE [names]');

        $file = fopen($script, 'r');
        while (!feof($file) && ($line = fgets($file)) !== false) {
            if (strpos($line, ' ') === false) {
                continue;
            }
            list($japanese, $english) = explode(' ', $line, 2);
            $english = trim($english);
            $id = $connection->query('SELECT [id] FROM [names] WHERE [japanese] = %s', $japanese)->fetchSingle();
            if ($id) {
                $connection
                    ->update(
                        'names',
                        [
                            'ensglish' => $english,
                        ]
                    )
                    ->where('id = %i', $id)
                    ->execute();
            } else {
                $connection
                    ->insert(
                        'names',
                        [
                            'japanese' => $japanese,
                            'english' => $english,
                            'color' => '',
                        ]
                    )
                    ->execute();
            }
        }
        fclose($file);

        return 0;
    }
}
