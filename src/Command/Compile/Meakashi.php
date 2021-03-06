<?php

declare(strict_types=1);

namespace Higurashi\Command\Compile;

use Higurashi\Service\Patch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Meakashi extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:compile:meakashi')
            ->setDescription('Compiles patch for Meakashi.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $chapter = strtolower((new \ReflectionClass($this))->getShortName());
        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $patch = new Patch(new Filesystem(), $chapter, $directory);
        $patch->initialize();

        // 1. Copy graphics patch.
        $output->writeln('Copying graphics patch.');
        $patch->copyGraphics();

        // 2. Copy voices.
        $output->writeln('Copying voices.');
        $patch->copyVoices('s06');
        $patch->copyVoices('s20');

        // 3. Copy voice patch.
        $output->writeln('Copying voice patch.');
        $patch->copyVoicePatch();

        // 4. Copy Steam sprites patch.
        $output->writeln('Copying Steam sprites patch.');
        $patch->copySteamPatch();

        return 0;
    }
}
