<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Nette\Utils\Finder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class ConsoleArcUpgrade extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:console-arc-upgrade')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to update.')
            ->setDescription('Fully upgrades a chapter.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        if (! isset(Constants::CONSOLE_ARCS[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $this->runCommand(
            'higurashi:download',
            [
                'chapter' => 'console',
                '--force' => true,
            ],
            $output
        );

        $this->runCommand(
            'higurashi:unpack',
            [
                'chapter' => 'console',
                '--force' => true,
            ],
            $output
        );

        $filesystem = new Filesystem();
        $filesystem->mkdir(sprintf('%s/unpack/%s_patch/Update', TEMP_DIR, $chapter));

        foreach (Finder::findFiles(Constants::CONSOLE_ARCS[$chapter] . '_*.txt')->in(sprintf('%s/unpack/console_patch/Update', TEMP_DIR)) as $file => $fileInfo) {
            $filesystem->copy($file, sprintf('%s/unpack/%s_patch/Update/%s', TEMP_DIR, $chapter, $fileInfo->getFilename()));
        }

        $this->runCommand(
            'higurashi:adv',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s_patch', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/adv/%s/Update', TEMP_DIR, $chapter), sprintf('%s/unpack/%s_patch/Update', TEMP_DIR, $chapter));

        $this->runCommand(
            'higurashi:dll-update',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s_patch', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/dllupdate/%s/Update', TEMP_DIR, $chapter), sprintf('%s/unpack/%s_patch/Update', TEMP_DIR, $chapter));

        $this->runCommand(
            'higurashi:lip-sync',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s_patch', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/lipsync/%s/Update', TEMP_DIR, $chapter), sprintf('%s/console/%s', TEMP_DIR, $chapter));

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
