<?php

declare(strict_types=1);

namespace Higurashi\Command\Compile;

use Higurashi\Constants;
use Higurashi\Service\Patch;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class Himatsubushi extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:compile:himatsubushi')
            ->setDescription('Compiles patch for Himatsubushi.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $chapter = strtolower((new \ReflectionClass($this))->getShortName());
        $directory = sprintf('%s/patch/%s', TEMP_DIR, $chapter);

        $patch = new Patch(new Filesystem(), $chapter, $directory, Constants::GAMES[$chapter]);
        $patch->initialize();

        // 1. Copy graphics patch.
        $output->writeln('Copying graphics patch.');
        if (! $patch->copyGameCG()) {
            $output->writeln('Game files missing, you may get false-positive results about missing sprites.');
        }
        $patch->copyGraphics();

        // 2. Copy voices.
        $output->writeln('Copying voices.');
        $patch->copyVoices('s05');
        $patch->copyVoices('s20');

        // 3. Copy voice patch.
        $output->writeln('Copying voice patch.');
        $patch->copyVoicePatch();

        // 4. Copy Steam sprites patch.
        $output->writeln('Copying Steam sprites patch.');
        $patch->renameGraphicsDirectory();
        if (! $patch->copyGameCGAlt()) {
            $output->writeln('Game files missing, you may get false-positive results about missing sprites.');
        }
        $patch->copySteamPatch($chapter . '_steam');
        $patch->useAlternativeChieSprites('Alternate Chie-sensei Sprites');

        return 0;
    }
}
