<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Utils\LineProcessorTrait;
use Higurashi\Utils\LineStorage;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class VoicePack extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:voice-pack')
            ->setDescription('Compiles voice pack for chapter. Does not change the scripts.')
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
    private $ps2Map;
    private $bashCopy;

    private function finish(): void
    {
        $bashCopy = array_unique($this->bashCopy);

        $file = sprintf('%s/%s/%s-voices.sh', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $this->chapter);

        file_put_contents($file, implode("\n", $bashCopy));
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if ($match = Strings::match($line, '~^(\s++)ModPlayVoiceLS\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"([^"]++)?"\s*+,\s*+(.*)$~')) {
            $voice = $match[4];

            if (! $voice) {
                return $line;
            }

            if (Strings::lower($voice) !== $voice) {
                echo 'Warning - non lowercase voice: ' . $voice . PHP_EOL;
            }

            // TODO: Add support for PS2 using similar logic as Voices.php

            $this->addBashCopyForPS3Voice($voice);
        }

        return $line;
    }

    private function buildPS2Map(): void
    {
        $this->ps2Map = [];
        $cutLength = Strings::length($this->ps2VoicesDirectory);

        foreach (Finder::findFiles('*.ogg')->from($this->ps2VoicesDirectory) as $file) {
            $voice = $file->getBasename('.ogg');
            $this->ps2Map[$voice][] = Strings::substring($file->getPath(), $cutLength + 1);
        }
    }

    private function findPs2FileByVoiceAndHash(string $voice, string $voiceHash): ?string
    {
        if (! array_key_exists($voice, $this->ps2Map)) {
            return null;
        }

        foreach ($this->ps2Map[$voice] as $directory) {

        }

        return null;
    }

    private function addBashCopyForPS2Voice(string $voice, string $spectrum): void
    {
        $destination = $spectrum . '.ogg';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/voice/ps2/' . $destination) . ' && cp "voice/ps2/' . $voice . '.ogg" "' . $this->chapter . '/voice/ps2/' . $destination . '"';
    }

    private function addBashCopyForPS2Spectrum(string $spectrum): void
    {
        $destination = $spectrum . '.txt';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/spectrum/ps2/' . $destination) . ' && cp "spectrum/ps2/' . $spectrum . '.txt" "' . $this->chapter . '/spectrum/ps2/' . $destination . '"';
    }

    private function addBashCopyForPS3Voice(string $voice): void
    {
        $destination = $voice . '.ogg';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/voice/' . $destination) . ' && cp "voice/ps3/' . $destination . '" "' . $this->chapter . '/voice/' . $destination . '"';

        $destination = $voice . '.txt';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/spectrum/' . $destination) . ' && cp "spectrum/ps3/' . $destination . '" "' . $this->chapter . '/spectrum/' . $destination . '"';
    }
}
