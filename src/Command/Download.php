<?php

declare(strict_types=1);

namespace Higurashi\Command;

use GuzzleHttp\Client;
use Higurashi\Service\Downloader;
use Higurashi\Service\Links;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Download extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:download')
            ->setDescription('Download resources needed to compile higurashi patches.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to download.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Redownload all resources.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');

        if (! isset(Links::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $downloader = new Downloader(
            new Client(),
            new Filesystem(),
            $input->getOption('force'),
            (function () use ($output) {
                while (true) {
                    $output->writeln(yield);
                }
            })()
        );

        foreach (Links::VOICES as $path => $url) {
            $downloader->startDownloadIfNeeded(
                $url,
                sprintf('%s/%s', TEMP_DIR, $path)
            );
        }

        foreach (Links::PATCHES[$chapter] as $type => $url) {
            $downloader->startDownloadIfNeeded(
                Links::PATCHES[$chapter][$type],
                sprintf('%s/download/%s_%s.zip', TEMP_DIR, $chapter, $type)
            );
        }

        $downloader->save();

        return 0;
    }
}
