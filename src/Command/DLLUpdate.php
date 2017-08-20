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

        return 0;
    }

    private $deleteLines = 0;

    protected function processLine(string $line, LineStorage $lines): string
    {
        if (Strings::match($line, '~^\\s++int AdvMode;$~')) {
            $this->deleteLines = 8;
        }

        if ($this->deleteLines) {
            $this->deleteLines -= 1;
            $line = '';
        }

        if ($match = Strings::match($line, '~^\\s++PlaySE\\(\\s*+([0-9]++)\\s*+,\\s*+"((?:ps2\\/)?[sS][^_][^"]++)",\\s*+([0-9]++),\\s*+[^)]+\\);$~')) {
            $line = "\t" . sprintf('PlayVoice(%d, "%s", %d);', $match[1], $match[2], $match[3]) . "\n";
        }

        $line = str_replace('if (AdvMode) {', 'if (GetGlobalFlag(GADVMode)) {', $line);
        $line = str_replace('if (AdvMode == 0) {', 'if (GetGlobalFlag(GADVMode) == 0) {', $line);
        $line = str_replace('Line_ModeSpecific', 'GetGlobalFlag(GLinemodeSp)', $line);

        if (
            $lines->count()
            && Strings::match($lines->get(-1), '~^\\s++PlayVoice\\(~')
            && (
                Strings::match($line, '~^\\s++ClearMessage\\(\\);$~')
                || Strings::match($line, '~\\<\\/color\\>~')
            )
        ) {
            $previous = $lines->get(-1);
            $lines->set(-1, $line);
            $line = $previous;
        }

        return $line;
    }
}
