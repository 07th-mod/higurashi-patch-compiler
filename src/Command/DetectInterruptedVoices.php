<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Utils\LineProcessorTrait;
use Higurashi\Utils\LineStorage;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class DetectInterruptedVoices extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:detect:interruped-voices')
            ->setDescription('Detects voices that are interrupted by another voice.')
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

        $directory = sprintf('%s/%s/%s', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $chapter);

        $this->update($chapter, $directory);

        return 0;
    }

    /**
     * @var bool[]
     */
    private $currentVoices;

    private function initFile(): void
    {
        $this->currentVoices = [];
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if ($match = Strings::match($line, '~^(\s++)ModPlayVoiceLS\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"([^"]++)"\s*+,\s*+(.*)$~')) {
            $line = $this->processVoice($line, $lines, (int) $match[2], $match[4], $filename, $lineNumber);
        }

        if ($match = Strings::match($line, '~^(\s++)PlayVoice\(\s*+([0-9]++)\s*+,\s*+"([^"]++)"\s*+,\s*+(.*)$~')) {
            $line = $this->processVoice($line, $lines, (int) $match[2], $match[3], $filename, $lineNumber);
        }

        if ($match = Strings::match($line, '~^(\s++)PlaySE\(\s*+([0-9]++)\s*+,\s*+"(ps[23]/[^"]++)"\s*+,\s*+(.*)$~')) {
            $line = $this->processVoice($line, $lines, (int) $match[2], $match[3], $filename, $lineNumber);
        }

        if (Strings::contains($line, 'Line_WaitForInput')) {
            $this->currentVoices = [];
        }

        if (Strings::contains($line, 'Line_Normal')) {
            $this->currentVoices = [];
        }

        if (Strings::contains($line, 'GetGlobalFlag(GLinemodeSp)')) {
            $this->currentVoices = [];
        }

        if ($match = Strings::match($line, '~^(\s++)WaitToFinishVoicePlaying\(\s*+([0-9]++)\s*+(.*)$~')) {
            unset($this->currentVoices[(int) $match[2]]);
        }

        return $line;
    }

    private function processVoice(string $line, LineStorage $lines, int $channel, string $voice, string $filename, int $lineNumber): string
    {
        if (array_key_exists($channel, $this->currentVoices)) {
            printf(
                'Voice %s is interrupted by voice %s in %s:%d.' . PHP_EOL,
                $this->currentVoices[$channel],
                $voice,
                Strings::after($filename, '/', -1),
                $lineNumber
            );

            $line = "\tWaitToFinishVoicePlaying( $channel ); // autofix interrupted voice\n" . $line;
        }

        $this->currentVoices[$channel] = $voice;

        return $line;
    }
}
