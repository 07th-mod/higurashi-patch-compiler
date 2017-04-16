<?php

declare(strict_types=1);

namespace Higurashi\Command\Compile;

use Higurashi\Constants;
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
            ->setName('higurashi:compile:tatarigoroshi')
            ->setDescription('Compiles patch for Tatarigoroshi.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $chapter = strtolower((new \ReflectionClass($this))->getShortName());
        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $patch = new Patch(new Filesystem(), $chapter, $directory, Constants::GAMES[$chapter]);
        $patch->initialize();

        // 1. Copy graphics patch.
        $patch->copyGameCG();
        $patch->copyGraphics();

        // 2. Copy voices.
        $patch->copyVoices('s03');
        $patch->copyVoices('s19');
        $patch->copyVoices('s20');

        // 3. Copy voice patch.
        $patch->copyVoicePatch();

        // 4. Make PS3 sprites default.
        $patch->renameGraphicsDirectory();

        // 5. Copy CGAlt directory from game files.
        $patch->copyGameCGAlt();

        // 6. Copy Steam patch.
        $patch->copySteamPatch();
        $patch->useAlternativeChieSprites('Alternate Chie-sensei sprites');

        // 7. Load and save all PNG images to reduce size.
        $patch->compressImages('CG');
        $patch->compressImages('CGAlt');
        
        return 0;
    }
}
