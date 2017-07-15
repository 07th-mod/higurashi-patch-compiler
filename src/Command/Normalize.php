<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\LineNormalizer;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Normalize extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:normalize')
            ->setDescription('Replaces Line_WaitForInput with Line_Normal before each ClearMessage() command.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to update.')
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

        $directory = sprintf('%s/normalize/%s', TEMP_DIR, $chapter);

        $updater = new LineNormalizer($chapter, $directory);

        $updater->copyFiles();
        $updater->update();

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
