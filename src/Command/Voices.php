<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Utils\LineProcessorTrait;
use Higurashi\Utils\LineStorage;
use Nette\Utils\FileSystem;
use Nette\Utils\Finder;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Yaml\Yaml;

class Voices extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:voices')
            ->setDescription('Fixes voice pack to contain all voices and spectrum files at the right place.')
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

        $config = Yaml::parse(file_get_contents(__DIR__ . '/../../config/local.yml'));

        if (! isset(Constants::PATCHES[$chapter]) || ! isset($config['chapters'][$chapter])) {
            $output->writeln(sprintf('Chapter "%s" not found.', $chapter));

            return 1;
        }

        $this->chapterVoicesDirectory = $config['chapters'][$chapter]['local'] . '/StreamingAssets/voice';
        $this->ps2VoicesDirectory = sprintf('%s/%s', TEMP_DIR, 'unpack/ps2-voices');
        $this->ps3VoicesDirectory = sprintf('%s/%s', TEMP_DIR, 'unpack/voices');
        $this->ps2SpectrumDirectory = sprintf('%s/%s', TEMP_DIR, 'unpack/spectrum/ps2');
        $this->ps3SpectrumDirectory = sprintf('%s/%s', TEMP_DIR, 'unpack/spectrum/ps3');

        $resourceDirectories = [
            $this->chapterVoicesDirectory,
            $this->ps2VoicesDirectory,
            $this->ps3VoicesDirectory,
            $this->ps2SpectrumDirectory,
            $this->ps3SpectrumDirectory,
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

    private $chapterVoicesDirectory;
    private $ps2VoicesDirectory;
    private $ps3VoicesDirectory;
    private $ps2SpectrumDirectory;
    private $ps3SpectrumDirectory;
    private $ps2Map;
    private $bashCopy;

    private function finish(): void
    {
        $bashCopy = array_unique($this->bashCopy);

        $file = sprintf('%s/%s/%s.sh', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $this->chapter);

        file_put_contents($file, implode("\n", $bashCopy));
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if ($match = Strings::match($line, '~^(\s++)ModPlayVoiceLS\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"([^"]++)?"\s*+,\s*+(.*)$~')) {
            $voice = $match[4];

            $voiceHash = $this->getFileHash($this->chapterVoicesDirectory, $voice);

            if (Strings::startsWith($voice, 'ps3/')) {
                $voice = Strings::substring($voice, 4);
            }

            $ps3Hash = $this->getFileHash($this->ps3VoicesDirectory, $voice);

            if ($voiceHash && $voiceHash === $ps3Hash) {
                $this->addBashCopyForPS3Voice($voice);
                $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %s', $match[1], $match[2], $match[3], sprintf('ps3/%s', $voice), $match[5]) . "\n";

                return $line;
            }

            if ($ps3Hash) {
                $this->addBashCopyForPS3Voice($voice);
                $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %s', $match[1], $match[2], $match[3], sprintf('ps3/%s', $voice), $match[5]) . "\n";

                return $line;
            }

            $baseVoice = Strings::after($voice, '/', -1);

            if (! $voiceHash) {
                // Can't identify voice. In reality this should not happen.
                // If it does happen then try to find in PS2 voices manually and see if it matches.
                throw new \Exception();
            }

            $ps2VoiceDirectory = $this->findPs2FileByVoiceAndHash($baseVoice, $voiceHash);

            if ($ps3Hash && $ps2VoiceDirectory) {
                // PS3 voice exists but PS2 voice is used. In reality this should not happen.
                throw new \Exception();
            }

            if ($ps2VoiceDirectory) {
                $this->addBashCopyForPS2Voice($voice);
                $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %s', $match[1], $match[2], $match[3], sprintf('ps2/%s/%s', $ps2VoiceDirectory, $baseVoice), $match[5]) . "\n";

                return $line;
            }

            // Voice exists in current patch but has been somehow customized.
            $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %s', $match[1], $match[2], $match[3], sprintf('custom/%s', $baseVoice), $match[5]) . "\n";
            $this->addBashCopyForCustomVoice($match[4], $baseVoice);

            return $line;
        }

        return $line;
    }

    private function getFileHash(string $directory, string $voice): ?string
    {
        $file = sprintf('%s/%s.ogg', $directory, $voice);

        return file_exists($file) ? md5_file($file) : null;
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
            $hash = $this->getFileHash($this->ps2VoicesDirectory, $directory . '/' . $voice);

            if ($hash === $voiceHash) {
                return $directory;
            }
        }

        return null;
    }

    private function addBashCopyForPS2Voice(string $voice): void
    {
        $destination = $voice . '.ogg';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/voice/ps2/' . $destination) . ' && cp "voice/ps2/' . $destination . '" "' . $this->chapter . '/voice/ps2/' . $destination . '"';

        $destination = $voice . '.txt';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/spectrum/ps2/' . $destination) . ' && cp "spectrum/ps2/' . $destination . '" "' . $this->chapter . '/spectrum/ps2/' . $destination . '"';
    }

    private function addBashCopyForPS3Voice(string $voice): void
    {
        $destination = $voice . '.ogg';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/voice/ps3/' . $destination) . ' && cp "voice/ps3/' . $destination . '" "' . $this->chapter . '/voice/ps3/' . $destination . '"';

        $destination = $voice . '.txt';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/spectrum/ps3/' . $destination) . ' && cp "spectrum/ps3/' . $destination . '" "' . $this->chapter . '/spectrum/ps3/' . $destination . '"';
    }

    private function addBashCopyForCustomVoice(string $voice, string $baseVoice): void
    {
        $destination = $baseVoice . '.ogg';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/voice/custom/' . $destination) . ' && cp "' . $this->chapter . '-old/' . $voice . '.ogg" "' . $this->chapter . '/voice/custom/' . $destination . '"';
    }
}
