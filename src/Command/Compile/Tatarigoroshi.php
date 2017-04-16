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
        $output->writeln('Copying graphics patch.');
        $patch->copyGameCG();
        $patch->copyGraphics('tatarigoroshi_graphics');

        // 2. Copy voices.
        $output->writeln('Copying voices.');
        $patch->copyVoices('s03');
        $patch->copyVoices('s19');
        $patch->copyVoices('s20');

        // 3. Copy voice patch.
        $output->writeln('Copying voice patch.');
        $patch->copyVoicePatch();

        // 4. Copy Steam sprites patch.
        $output->writeln('Copying Steam sprites patch.');
        $patch->renameGraphicsDirectory();
        $patch->copyGameCGAlt();
        $patch->copySteamPatch();
        $patch->useAlternativeChieSprites('Alternate Chie-sensei sprites');

        // 5. Load and save all PNG images to reduce size.
        $output->write('Compressing images in CG directory.');
        foreach ($patch->compressImages('CG') as list($done, $total)) {
            $output->write("\x0D");
            $output->write(sprintf('Compressing images in CG directory (%d/%d).', $done, $total));
        }
        $output->writeln('');
        $output->write('Compressing images in CGAlt directory.');
        foreach ($patch->compressImages('CGAlt') as list($done, $total)) {
            $output->write("\x0D");
            $output->write(sprintf('Compressing images in CGAlt directory (%d/%d).', $done, $total));
        }
        $output->writeln('');
        
        return 0;
    }
}
