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

class ParseScript extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:parse')
            ->setDescription('Parses the PS3 script.')
            ->addArgument('script', InputArgument::REQUIRED, 'Path to the script.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $script */
        $script = $input->getArgument('script');

        $parser = new ScriptParser($script);

        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../config/local.yml'));

        $options = $config['database'];
        $options['driver'] = 'pdo';
        $options['charset'] = 'utf8';
        $connection = new Connection($options);

        $connection->query('TRUNCATE [voices]');

        $count = 0;
        foreach ($parser->parse() as [$name, $voice, $text, $line]) {
            ++$count;
            $connection
                ->insert(
                    'voices',
                    [
                        'name' => $name,
                        'voice' => $voice,
                        'text' => $text,
                        'file' => substr($voice, 0, 3),
                        'line' => $line,
                    ]
                )
                ->execute();
        }

        $output->writeln(sprintf('Inserted %d voices.', $count));

        return 0;
    }
}
