<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Dibi\Connection;
use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\ColorsUpdater;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class ListNames extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:list-names')
            ->setDescription('Dumps complete list of name lines.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../config/local.yml'));

        $options = $config['database'];
        $options['driver'] = 'pdo';
        $options['charset'] = 'utf8';
        $connection = new Connection($options);

        $names = $connection->query('SELECT * FROM [names]')->fetchAll();

        foreach ($names as $name) {
            $output->writeln(sprintf('if (AdvMode) { OutputLine("%s", NULL, "%s", NULL, Line_ContinueAfterTyping); }', ColorsUpdater::formatName($name->toArray(), 'japanese'), ColorsUpdater::formatName($name->toArray(), 'english')));
        }

        return 0;
    }
}
