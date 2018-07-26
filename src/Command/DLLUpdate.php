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
use Symfony\Component\Filesystem\Filesystem;

class DLLUpdate extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:dll-update')
            ->setDescription('Changes scripts with new DLL features.')
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

        $initFile = sprintf('%s/dllupdate/%s/Update/init.txt', TEMP_DIR, $chapter);
        if (file_exists($initFile)) {
            $filesystem = new Filesystem();
            $filesystem->remove($initFile);
            $filesystem->copy(sprintf('%s/../data/init.txt', TEMP_DIR), $initFile);
        }

        return 0;
    }

    private $deleteLines = 0;
    private $playingVoice = false;

    private function initFile(): void
    {
        $this->playingVoice = false;
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if (Strings::match($line, '~^\\s++int AdvMode;$~')) {
            $this->deleteLines = 8;
        }

        if ($this->deleteLines) {
            $this->deleteLines -= 1;
            $line = '';
        }

        if ($match = Strings::match($line, '~^\\s++PlaySE\\(\\s*+([0-9]++)\\s*+,\\s*+"(ps[23]/[^"]++)?",\\s*+([0-9]++),\\s*+[^)]+\\);$~')) {
            $line = "\t" . sprintf('PlayVoice(%d, "%s", %d);', $match[1], $match[2], $match[3]) . "\n";
        }

        if ($match = Strings::match($line, '~^\\s++PlaySE\\(\\s*+([0-9]++)\\s*+,\\s*+"([sS][0-9]{2}/[^"]++)?",\\s*+([0-9]++),\\s*+[^)]+\\);$~')) {
            $line = "\t" . sprintf('PlayVoice(%d, "%s", %d);', $match[1], 'ps3/' . Strings::lower($match[2]), $match[3]) . "\n";
        }

        $line = str_replace('if (AdvMode) {', 'if (GetGlobalFlag(GADVMode)) {', $line);
        $line = str_replace('if (AdvMode == 0) {', 'if (GetGlobalFlag(GADVMode) == 0) {', $line);
        $line = str_replace('Line_ModeSpecific', 'GetGlobalFlag(GLinemodeSp)', $line);

        if (
            $lines->count()
            && Strings::match($lines->get(-1), '~^\\s++PlayVoice\\(~')
            && (
                Strings::match($line, '~^\\s++ClearMessage\\(\\);$~')
                || (Strings::match($line, '~\\<\\/color\\>~') && Strings::contains($line, 'GetGlobalFlag(GADVMode)'))
            )
        ) {
            $previous = $lines->get(-1);
            $lines->set(-1, $line);
            $line = $previous;

            for (
                $i = -1;
                Strings::match($lines->get($i), '~\\<\\/color\\>~') && Strings::contains($lines->get($i), 'GetGlobalFlag(GADVMode)') && Strings::match($lines->get($i - 1), '~^\\s++PlayVoice\\(~');
                --$i
            ) {
                $previous = $lines->get($i - 1);
                $lines->set($i - 1, $lines->get($i));
                $lines->set($i, $previous);
            }

            $this->playingVoice = false;
        }

        if (Strings::match($line, '~^\\s++PlayVoice\\(~')) {
            if ($this->playingVoice && ! Strings::match($lines->get(-1), '~^\\s++PlayVoice\\(~')) {
                // This seems to report more false positives than real errors.
                // echo sprintf('Found voice wait error in %s:%d.', $filename, $lineNumber) . PHP_EOL;
            }

            $this->playingVoice = true;
        }

        // The <size=-2> tag is used for long unbreakable lines that don't fit on 3 lines.
        // However using </size> causes the last line to jump a little because the line-break icon would have a different line-height.
        // Therefore </size> should be omitted. The next line will have the default font size regardless.
        if (Strings::contains($line, '"<size=-2>')) {
            $line = str_replace('</size>"', '"', $line);
        }

        if (
            Strings::contains($line, 'GetGlobalFlag(GLinemodeSp)')
            || Strings::contains($line, 'Line_Normal')
            || Strings::contains($line, 'Line_WaitForInput')
        ) {
            $this->playingVoice = false;
        }

        if ($this->playingVoice) {
            $line = str_replace('Line_ContinueAfterTyping', 'Line_Continue', $line);
            $line = str_replace("\tSetValidityOfInput", "\t// (backup) SetValidityOfInput", $line);
        }

        return $line;
    }
}
