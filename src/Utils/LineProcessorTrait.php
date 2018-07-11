<?php

declare(strict_types=1);

namespace Higurashi\Utils;

use Nette\Utils\FileSystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

trait LineProcessorTrait
{
    private $chapter;

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

    private function update(string $chapter, string $directory): void
    {
        $this->chapter = $chapter;

        FileSystem::delete($directory);
        FileSystem::copy(sprintf('%s/unpack/%s_%s', TEMP_DIR, $chapter, 'patch'), $directory);

        $this->init();

        $scriptsDirectory = sprintf('%s/Update', $directory);

        $files = glob(sprintf('%s/*.txt', $scriptsDirectory));

        foreach ($files as $file) {
            $this->processScript($file);
        }

        $this->finish();
    }

    private function processScript(string $filename): void
    {
        $file = fopen($filename, 'r');
        @unlink($filename . '.new');
        $output = fopen($filename . '.new', 'w');
        $this->initFile();
        foreach ($this->updateLines($file, $filename) as $line) {
            fwrite($output, $line);
        }
        fclose($file);
        fclose($output);
        unlink($filename);
        rename($filename . '.new', $filename);
    }

    private function updateLines($file, string $filename): iterable
    {
        $name = null;
        $lines = new LineStorage();

        $i = 1;
        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = str_replace("\xEF\xBB\xBF", '', rtrim($line)) . "\n";
            $line = $this->processLine($line, $lines, $i, $filename);
            $lines->add($line);
            ++$i;
        }

        $this->finishFile($lines, $filename);

        return $lines;
    }

    private function init(): void
    {
    }

    private function initFile(): void
    {
    }

    private function finishFile(LineStorage $lines, string $filename): void
    {
    }

    private function finish(): void
    {
    }
}