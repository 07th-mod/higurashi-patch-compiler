<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\DuplicateDetector;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compare extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:compare')
            ->setDescription('Compresses the compiled patch.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to clean.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        if (! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $detector = new DuplicateDetector();

        $processDirectory = function ($subdirectory) use ($detector, $directory, $output) {
            foreach ($detector->processDirectory($directory . '/' . $subdirectory) as list($image1, $image2)) {
                $output->writeln(
                    sprintf(
                        'Images "%s" and "%s" are the same.',
                        $subdirectory . '/' . $image1,
                        $subdirectory . '/' . $image2
                    )
                );
            }
        };

        $processDirectory('CG');
        foreach (glob($directory . '/CG/*' , GLOB_ONLYDIR) as $subdirectory) {
            $processDirectory('CG/' . pathinfo($subdirectory, PATHINFO_BASENAME));
        }

        return 0;
    }
}
