<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\Unpacker;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Unpack extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:unpack')
            ->setDescription('Unpacks resources needed to compile higurashi patches.')
            ->addArgument('chapter', InputArgument::OPTIONAL, 'Chapter to unpack. If unspecified the voices will be unpacked.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Redownload all resources.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        if ($chapter && ! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $unpacker = new Unpacker(
            new Filesystem(),
            $input->getOption('force'),
            (function () use ($output) {
                while (true) {
                    $output->writeln(yield);
                }
            })()
        );

        if ($chapter) {
            foreach (Constants::PATCHES[$chapter] as $name => $url) {
                $unpacker->unpackIfNeeded(
                    sprintf('%s/download/%s.zip', TEMP_DIR, $name),
                    sprintf('%s/unpack/%s', TEMP_DIR, $name)
                );
            }
        } else {
            $unpacker->unpackMultipleIfNeeded(
                array_map(
                    function ($path) {
                        return sprintf('%s/%s', TEMP_DIR, $path);
                    },
                    array_keys(Constants::VOICES)
                ),
                sprintf('%s/unpack/voices', TEMP_DIR)
            );
        }

        return 0;
    }
}
