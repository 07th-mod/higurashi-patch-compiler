<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Make extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:make')
            ->setDescription('Runs all other commands to compile the patch.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to download.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Redownload all resources.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $start = microtime(true);

        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');

        /** @var bool $force */
        $force = $input->getOption('force');

        if (! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $this->runCommand(
            'higurashi:download',
            [],
            $output
        );

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
            [],
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

        $this->runCommand(
            'higurashi:compile:' . $chapter,
            [],
            $output
        );

        $this->runCommand(
            'higurashi:clean',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $time = microtime(true) - $start;

        $output->writeln(sprintf("%d hours, %d minutes and %d seconds", floor($time / 3600), ($time / 60) % 60, $time % 60));

        return 0;
    }

    private function runCommand(string $name, array $arguments, OutputInterface $output): void
    {
        $code = $this
            ->getApplication()
            ->find($name)
            ->run(new ArrayInput($arguments), $output);

        if ($code !== 0) {
            throw new \Exception(sprintf('Command "%s" exited with error code "%d".', $name, $code));
        }
    }
}
