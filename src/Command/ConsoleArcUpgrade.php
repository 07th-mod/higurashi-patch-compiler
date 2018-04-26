<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class FullUpgrade extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:full')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to update.')
            ->setDescription('Fully upgrades a chapter.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        if (! isset(Constants::PATCHES[$chapter]) && ! isset(Constants::CONSOLE_ARCS[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $this->runCommand(
            'higurashi:adventure',
            [
                'chapter' => $chapter,
                '--force' => true,
            ],
            $output
        );

        $filesystem = new Filesystem();
        $filesystem->remove(sprintf('%s/unpack/%s', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/adv/%s/Update', TEMP_DIR, $chapter), sprintf('%s/unpack/%s/Update', TEMP_DIR, $chapter));

        $this->runCommand(
            'higurashi:dll-update',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/dll/%s/Update', TEMP_DIR, $chapter), sprintf('%s/unpack/%s/Update', TEMP_DIR, $chapter));

        $this->runCommand(
            'higurashi:lip-sync',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/lip/%s/Update', TEMP_DIR, $chapter), sprintf('%s/full/%s', TEMP_DIR, $chapter));

        return 0;
    }

    private function runCommand(string $name, array $arguments, OutputInterface $output): void
    {
        $code = $this
            ->getApplication()
            ->find($name)
            ->run(new ArrayInput($arguments), $output);

        if ($code !== 0) {
            throw new \Exception(sprintf('Command "%s" exited with error code "%d".', $name, $code));
        }
    }
}
