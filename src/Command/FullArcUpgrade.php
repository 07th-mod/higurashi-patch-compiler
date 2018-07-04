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
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;

class FullArcUpgrade extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:full-arc-upgrade')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to update.')
            ->setDescription('Fully upgrades a chapter.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Redownload all resources.');
    }

    /**
     * @var bool
     */
    private $isConsoleArc;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        $this->isConsoleArc = isset(Constants::CONSOLE_ARCS[$chapter]);

        /** @var bool $force */
        $force = $input->getOption('force');

        $this->runCommand(
            'higurashi:download',
            [
                'chapter' => $this->isConsoleArc ? 'console' : $chapter,
                '--force' => $force,
            ],
            $output
        );

        $this->runCommand(
            'higurashi:unpack',
            [
                'chapter' => $this->isConsoleArc ? 'console' : $chapter,
                '--force' => $force,
            ],
            $output
        );

        $filesystem = new Filesystem();
        $filesystem->mkdir(sprintf('%s/unpack/%s_patch/Update', TEMP_DIR, $chapter));

        if ($this->isConsoleArc) {
            $files = Finder::findFiles(Constants::CONSOLE_ARCS[$chapter] . '_*.txt')->in(sprintf('%s/unpack/console_patch/Update', TEMP_DIR));
            foreach ($files as $file => $fileInfo) {
                $filesystem->copy($file, sprintf('%s/unpack/%s_patch/Update/%s', TEMP_DIR, $chapter, $fileInfo->getFilename()));
            }
        }

        $this->runCommand(
            'higurashi:combine',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s_patch', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/combine/%s/Update', TEMP_DIR, $chapter), sprintf('%s/unpack/%s_patch/Update', TEMP_DIR, $chapter));

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
        $filesystem->mirror(sprintf('%s/lipsync/%s/Update', TEMP_DIR, $chapter), sprintf('%s/unpack/%s_patch/Update', TEMP_DIR, $chapter));

        $this->runCommand(
            'higurashi:voice-pack',
            [
                'chapter' => $chapter,
            ],
            $output
        );

        $filesystem->remove(sprintf('%s/unpack/%s_patch', TEMP_DIR, $chapter));
        $filesystem->mirror(sprintf('%s/voicepack/%s/Update', TEMP_DIR, $chapter), sprintf('%s/console/%s', TEMP_DIR, $chapter));

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
