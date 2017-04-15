<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Service\Patch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Tatarigoroshi extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:tatarigoroshi')
            ->setDescription('Compiles patch for Tatarigoroshi.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $chapter = strtolower((new \ReflectionClass($this))->getShortName());
        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $patch = new Patch(new Filesystem(), $chapter, $directory);

        // 1. Copy graphics patch.
        $patch->copyGraphics();

        // 2. Copy voice directories.
        $patch->copyVoices('s03');
        $patch->copyVoices('s19');
        $patch->copyVoices('s20');

        // 3. Copy patch.
        $patch->copyPatch();

        // 4. Rename CGAlt to CG.
        $patch->renameGraphicsDirectory();

        return 0;
    }
}
