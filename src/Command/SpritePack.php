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

class SpritePack extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:sprite-pack')
            ->setDescription('Compiles sprite pack for chapter. Does not change the scripts.')
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

        $directory = sprintf('%s/%s/%s', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $chapter);

        $this->update($chapter, $directory);

        return 0;
    }

    private $spriteMap;
    private $bashCopy;

    private function init(): void
    {
        $this->spriteMap = include __DIR__ . '/../../data/map.php';
    }

    private function finish(): void
    {
        $bashCopy = array_unique($this->bashCopy);

        $file = sprintf('%s/%s/%s-sprites.sh', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $this->chapter);

        file_put_contents($file, implode("\n", $bashCopy));
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if ($match = Strings::match($line, '~^(\s++)(?:ModDrawCharacter|ModDrawCharacterWithFiltering)\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"([^"]*+)"\s*+,\s*+"([^"]*+)"\s*+,(.*)$~')) {
            $this->addBashCopyForSprite($match[4], $match[5]);
        }

        if ($match = Strings::match($line, '~^\s++sprite_[a-z0-9_]++ = "([^"]*+)";$~')) {
            $this->addBashCopyForSprite($match[1], '0');
        }

        return $line;
    }

    private function addBashCopyForSprite(string $sprite, string $expression): void
    {
        if ($sprite === 'transparent') {
            return;
        }

        $parts = explode('/', $sprite);

        if (count($parts) !== 2) {
            throw new \Exception(sprintf('Unable to parse sprite name "%s".', $sprite));
        }

        [$directory, $spriteName] = $parts;

        if (! isset($this->spriteMap[$spriteName . $expression])) {
            echo sprintf('Sprite not found in map: "%s".' . PHP_EOL, $spriteName . $expression);
            return;
        }

        $directory .= '/';
        $ps3Sprite = substr($this->spriteMap[$spriteName . $expression], 0, -1);
        $size = $directory === 'portrait/' ? 'l/' : 'm/';
        $destination = $directory . $spriteName . '%s.png';

        $command = 'mkdir -p ' . dirname($this->chapter . '-sprites/CG/' . $destination) . ' && cp sprites/Normal/' . $size . $ps3Sprite . '%s.png ' . $this->chapter . '-sprites/CG/' . $destination;

        $this->bashCopy[] = sprintf($command, 0, 0);
        $this->bashCopy[] = sprintf($command, 1, 1);
        $this->bashCopy[] = sprintf($command, 2, 2);

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '-sprites/CGAlt/' . $destination) . ' && cp sprites/Normal/' . $size . $ps3Sprite . $expression . '.png ' . $this->chapter . '-sprites/CGAlt/' . $directory . $spriteName . $expression . '.png';
    }
}
