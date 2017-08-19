<?php

declare(strict_types=1);

namespace Higurashi\Utils;

use Nette\Utils\FileSystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

trait LineProcessorTrait
{
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
        FileSystem::delete($directory);
        FileSystem::copy(sprintf('%s/unpack/%s_%s', TEMP_DIR, $chapter, 'patch'), $directory);

        $scriptsDirectory = sprintf('%s/Update', $directory);

        $files = glob(sprintf('%s/*.txt', $scriptsDirectory));

        foreach ($files as $file) {
            $this->processScript($file);
        }
    }

    private function processScript(string $filename): void
    {
        $file = fopen($filename, 'r');
        @unlink($filename . '.new');
        $output = fopen($filename . '.new', 'w');
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

        while (!feof($file) && ($line = fgets($file)) !== false) {
            $line = str_replace("\xEF\xBB\xBF", '', rtrim($line)) . "\n";

            $line = $this->processLine($line, $lines);

            $lines->add($line);
        }

        return $lines;
    }
}