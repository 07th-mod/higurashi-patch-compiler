<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\Cleaner;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Missing extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:missing')
            ->setDescription('Detects missing files.')
            ->addArgument('directory', InputArgument::REQUIRED, 'StreamingAssets directory of the game.');
    }

    /**
     * @var string
     */
    private $directory;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');

        if (! file_exists($directory) || ! is_dir($directory)) {
            throw new \Exception('Directory not found.');
        }

        $this->directory = realpath($directory);

        $scriptsDirectory = sprintf('%s/Update', $this->directory);

        $files = glob(sprintf('%s/*.txt', $scriptsDirectory));

        foreach ($files as $file) {
            foreach ($this->generateLines($file) as $line) {
                $this->processLine($line);
            }
        }

        return 0;
    }

    private function generateLines(string $filename): \Generator
    {
        $file = fopen($filename, 'r');
        while (!feof($file) && ($line = fgets($file)) !== false) {
            yield $line;
        }
        fclose($file);
    }

    private function processLine(string $line): void
    {
        if ($match = Strings::match($line, '~^(?:\s++)PlayVoice\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('voice', $match[1] . '.ogg');
            $this->requireFile('spectrum', $match[1] . '.txt');
        }

        if ($match = Strings::match($line, '~^(?:\s++)ModPlayVoiceLS\(\s*+(?:[0-9]++)\s*+,\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('voice', $match[1] . '.ogg');
            $this->requireFile('spectrum', $match[1] . '.txt');
        }

        if ($match = Strings::match($line, '~^(?:\s++)PlaySE\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)?"~')) {
            $this->requireFile('SE', $match[1] . '.ogg');
        }

        if ($match = Strings::match($line, '~^(?:\s++)PlayBGM\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)?"~')) {
            $this->requireFile('BGM', $match[1] . '.ogg');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:DrawBustshot|DrawSceneWithMask|DrawSprite|DrawSpriteWithFiltering)\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('CG', $match[1] . '.png');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:DrawBG|DrawBGWithMask|DrawScene|DrawSceneWithMask|ChangeScene)\(?:\s*+"([^"]++)"~')) {
            $this->requireFile('CG', $match[1] . '.png');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:ModDrawCharacter|ModDrawCharacterWithFiltering)\(\s*+(?:[0-9]++)\s*+,\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"\s*+,\s*+"([0-9]++)"~')) {
            $this->requireFile('CG', $match[1] . 0 . '.png');
            $this->requireFile('CG', $match[1] . 1 . '.png');
            $this->requireFile('CG', $match[1] . 2 . '.png');
            if (Strings::startsWith($match[1], 'sprite') || Strings::startsWith($match[1], 'portrait')) {
                $this->requireFile('CGAlt', $match[1] . $match[2] . '.png');
            }
        }
    }

    private $errors = [];

    private function requireFile(string $directory, string $file): void
    {
        $path = $this->directory . '/' . $directory . '/' . Strings::lower($file);

        if (! file_exists($path)) {
            $error = sprintf('File %s/%s not found.', $directory, $file);
            $this->reportError($error);
        } elseif (str_replace('\\', '/', $path) !== str_replace('\\', '/', realpath($path))) {
            $error = sprintf('File %s/%s does not match the realpath.', $directory, $file);
            $this->reportError($error);
        }
    }

    private function reportError(string $error): void
    {
        if (array_key_exists($error, $this->errors)) {
            return;
        }

        $this->errors[$error] = true;

        echo $error . PHP_EOL;
    }
}
