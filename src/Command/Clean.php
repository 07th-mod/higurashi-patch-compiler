<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\Cleaner;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Clean extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:clean')
            ->setDescription('Cleans compiled patch.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to clean.');
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

        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $cleaner = new Cleaner($chapter, $directory);

        $deletedFiles = 0;
        // Do NOT use iterator_to_array on generators.
        foreach ($cleaner->clean() as $file) {
            if (! Strings::startsWith($file, 'SE/')) {
                $output->writeln(sprintf('Deleted file "%s".', $file));
            }
            ++$deletedFiles;
        }

        $output->writeln(sprintf('Deleted %d unused files.', $deletedFiles));

        $output->writeln(sprintf('Missing files (%d):', count($cleaner->getMissingFiles())));

        foreach ($cleaner->getMissingFiles() as $file) {
            $output->writeln($file);
        }

        return 0;
    }
}
