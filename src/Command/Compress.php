<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Service\Compressor;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Compress extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:compress')
            ->setDescription('Compresses the compiled patch.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to clean.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');

        if (! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $compressor = new Compressor($directory);

        // 5. Load and save all PNG images to reduce size.
        $output->write('Compressing images in CG directory.');
        foreach ($compressor->compressImages('CG') as list($done, $total)) {
            $output->write("\x0D");
            $output->write(sprintf('Compressing images in CG directory (%d/%d).', $done, $total));
        }
        $output->writeln('');
        $output->write('Compressing images in CGAlt directory.');
        foreach ($compressor->compressImages('CGAlt') as list($done, $total)) {
            $output->write("\x0D");
            $output->write(sprintf('Compressing images in CGAlt directory (%d/%d).', $done, $total));
        }
        $output->writeln('');
        
        return 0;
    }
}
