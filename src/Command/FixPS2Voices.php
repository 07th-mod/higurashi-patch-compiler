<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Utils\LineProcessorTrait;
use Higurashi\Utils\LineStorage;
use Nette\Utils\Arrays;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class FixPS2Voices extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:fix-ps2-voices')
            ->setDescription('Fixes paths to PS2 voices.')
            ->addArgument('chapter', InputArgument::REQUIRED, 'Chapter to update.')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Redownload all resources.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        /** @var string $chapter */
        $chapter = $input->getArgument('chapter');
        $chapter = Helpers::guessChapter($chapter);

        /** @var bool $force */
        $force = $input->getOption('force');

        if (! isset(Constants::PATCHES[$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $this->runCommand(
            'higurashi:download',
            [
                'chapter' => $chapter,
                '--force' => $force,
            ],
            $output
        );

        $this->runCommand(
            'higurashi:unpack',
            [
                'chapter' => $chapter,
                '--force' => $force,
            ],
            $output
        );

        $this->runCommand(
            'higurashi:download',
            [],
            $output
        );

        $this->runCommand(
            'higurashi:unpack',
            [],
            $output
        );

        $this->ps2VoicesDirectory = sprintf('%s/%s', TEMP_DIR, 'unpack/ps2-voices');
        $this->ps2SpectrumDirectory = sprintf('%s/%s', TEMP_DIR, 'unpack/spectrum/ps2');

        $resourceDirectories = [
            $this->ps2VoicesDirectory,
            $this->ps2SpectrumDirectory,
        ];

        foreach ($resourceDirectories as $resourceDirectory) {
            if (! is_dir($resourceDirectory)) {
                $output->writeln(sprintf('Directory "%s" not found.', $resourceDirectory));

                return 1;
            }
        }

        $this->buildPS2Map();

        $directory = sprintf('%s/%s/%s', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $chapter);

        $this->update($chapter, $directory);

        return 0;
    }

    private $ps2VoicesDirectory;
    private $ps2SpectrumDirectory;
    private $ps2VoiceMap;
    private $ps2SpectrumMap;

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if ($match = Strings::match($line, '~^(\s++)ModPlayVoiceLS\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"ps2/([^"]++)?"\s*+,\s*+(.*)$~')) {
            $voice = $match[4];
            $newVoice = $this->fixPs2VoiceName($voice);
            if ($newVoice) {
                $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %d, TRUE);', $match[1], $match[2], $match[3], 'ps2/' . $newVoice, $match[5]) . "\n";
            }
        }

        if ($match = Strings::match($line, '~^(\s++)PlayVoice\(\s*+([0-9]++)\s*+,\s*+"ps2/([^"]++)?"\s*+,\s*+(.*)$~')) {
            $voice = $match[3];
            $newVoice = $this->fixPs2VoiceName($voice);
            if ($newVoice) {
                $line = sprintf('%sPlayVoice(%d, "%s", %d);', $match[1], $match[2], 'ps2/' . $newVoice, $match[4]) . "\n";
            }
        }

        if ($match = Strings::match($line, '~^(\s++)PlaySE\(\s*+([0-9]++)\s*+,\s*+"ps2/([^"]++)?"\s*+,\s*+(.*)$~')) {
            $voice = $match[3];
            $newVoice = $this->fixPs2VoiceName($voice);
            if ($newVoice) {
                $line = sprintf('%sPlaySE(%d, "%s", %s', $match[1], $match[2], 'ps2/' . $newVoice, $match[4]) . "\n";
            }
        }

        return $line;
    }

    private function fixPs2VoiceName(string $voice): ?string
    {
        $baseVoice = Strings::after($voice, '/');
        $directory = Strings::before($voice, '/');

        if (! array_key_exists($baseVoice, $this->ps2SpectrumMap)) {
            throw new \Exception(sprintf('Can\'t find spectrum file for voice "%s"', $baseVoice));
        }

        $spectrumDirectories = $this->ps2SpectrumMap[$baseVoice];
        if (! in_array($directory, $spectrumDirectories, true)) {
            if (count($spectrumDirectories) !== 1) {
                // TODO: Use Constants::CHARACTER_NUMBERS to narrow it down.
                printf(
                    'Unable to decide correct directory for voice "%s".',
                    $voice
                );
                echo PHP_EOL;
            } else {
                return $spectrumDirectories[0] . '/' . $baseVoice;
            }
        }

        return null;
    }

    private function buildPS2Map(): void
    {
        $this->ps2VoiceMap = [];
        $cutLength = Strings::length($this->ps2VoicesDirectory);

        foreach (Finder::findFiles('*.ogg')->from($this->ps2VoicesDirectory) as $file) {
            $voice = $file->getBasename('.ogg');
            $this->ps2VoiceMap[$voice][] = Strings::substring($file->getPath(), $cutLength + 1);
        }

        $this->ps2SpectrumMap = [];
        $cutLength = Strings::length($this->ps2SpectrumDirectory);

        foreach (Finder::findFiles('*.txt')->from($this->ps2SpectrumDirectory) as $file) {
            $voice = $file->getBasename('.txt');
            $this->ps2SpectrumMap[$voice][] = Strings::substring($file->getPath(), $cutLength + 1);
        }
    }
}
