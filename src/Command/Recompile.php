<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;
use Symfony\Component\Yaml\Yaml;

class Recompile extends Command
{
    protected function configure()
    {
        $this
            ->setName('higurashi:recompile')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to update.')
            ->setDescription('Recompile ADV mode, merge and push the result, apply it to local game.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');

        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../config/local.yml'));

        if (! isset(Constants::PATCHES[$chapter]) || ! isset($config['chapters'][$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $repository = $config['chapters'][$chapter]['repository'];

        if (! is_dir($repository . '/.git')) {
            $output->writeln(sprintf('Repository "%s" not found.', $repository));

            return 2;
        }

        $local = $config['chapters'][$chapter]['local'];

        if (! is_dir($local . '/StreamingAssets')) {
            $output->writeln(sprintf('Local directory "%s" not found.', $local));

            return 3;
        }

        $this->runCommand(
            'higurashi:adventure',
            [
                'chapter' => $chapter,
                '--force' => true,
            ],
            $output
        );

        $process = new Process('git checkout master');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $process = new Process('git push');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $process = new Process('git checkout adv-mode-auto');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $process = new Process('git merge master');
        $process->setWorkingDirectory($repository);
        // This command is expected to fail because of conflicts. Conflicts are resolved below using the recompiled version of ADV-mode.
        $process->run();

        $filesystem = new Filesystem();
        $filesystem->remove($repository . '/Update');
        $filesystem->mirror(sprintf('%s/adv/%s/Update', TEMP_DIR, $chapter), $repository . '/Update');

        $process = new Process('git add --all');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $process = new Process('git status --porcelain');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        if ($process->getOutput()) {
            $process = new Process('git commit --message "Merge master"');
            $process->setWorkingDirectory($repository);
            $process->mustRun();

            $process = new Process('git push');
            $process->setWorkingDirectory($repository);
            $process->mustRun();
        }

        $process = new Process('git checkout adv-mode');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $process = new Process('git merge adv-mode-auto');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $process = new Process('git push');
        $process->setWorkingDirectory($repository);
        $process->mustRun();

        $filesystem->remove($local . '/StreamingAssets/Update');
        $filesystem->mirror($repository . '/Update', $local . '/StreamingAssets/Update');
        $filesystem->remove($local . '/StreamingAssets/CompiledUpdateScripts');
        $filesystem->mkdir($local . '/StreamingAssets/CompiledUpdateScripts');

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
