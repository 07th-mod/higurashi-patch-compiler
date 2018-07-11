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

class DetectMultilineVoices extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:detect:multiline-voices')
            ->setDescription('Detects voices that cover multiple lines.')
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

    private function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        return $line;
    }

    private function finishFile(LineStorage $lines, string $filename): void
    {
        $count = $lines->count();
        for ($i = 0; $i + 4 < $count; ++$i) {
            if (
                $this->isVoice($lines->get($i))
                && $this->isText($lines->get($i + 1))
                && $this->startsQuote($lines->get($i + 1))
                && ($match = Strings::match($lines->get($i + 2), '~^\s++NULL\s*+,\s*+"((?:[^"]|\\\\")+)"\s*+,\s*+Line_WaitForInput~'))
                && $this->isText($lines->get($i + 3))
            ) {
                $line = $lines->get($i + 2);
                $line = str_replace('Line_WaitForInput', 'Line_Continue', $line);
                $wait = round(Strings::length($match[1]) / 25 * 10) * 100;
                $line .= "\tWait( $wait ); // autofix multiline voice\n";
                $lines->set($i + 2, $line);
            }
        }
    }

    private function isVoice(string $line): bool
    {
        return (Strings::contains($line, 'PlayVoice') || Strings::contains($line, 'PlaySE')) && (Strings::contains($line, 'ps2/') || Strings::contains($line, 'ps3/'));
    }

    private function isText(string $line): bool
    {
        return Strings::contains($line, 'OutputLine(');
    }

    private function startsQuote(string $line): bool
    {
        return Strings::contains($line, '"「');
    }
}
