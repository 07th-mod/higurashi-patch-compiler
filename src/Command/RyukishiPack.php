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

class RyukishiPack extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:ryukishi-pack')
            ->setDescription('Compiles Ryukishi sprite pack for chapter. Does not change the scripts.')
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
        $this->spriteMap = [];
        // https://github.com/drojf/match_higurashi_scripts/blob/master/imageComparer/noconsole_output.txt.csv
        $handle = fopen(__DIR__ . '/../../data/ryukishi.csv', 'r');
        fgetcsv($handle);
        while ($line = fgetcsv($handle)) {
            if (in_array($line[2], ['CHIBI_SPRITE', 'RENA_WITH_SWORD', 'IGNORE_NOT_A_SPRITE'], true) || Strings::startsWith($line[2], 'NEW_CHAR_')) {
                continue;
            }
            $this->spriteMap[Strings::after($line[1], 'sprite/')] = $line[2];
        }
        fclose($handle);
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

        return $line;
    }

    private function addBashCopyForSprite(string $sprite, string $expression): void
    {
        if ($sprite === 'transparent') {
            return;
        }

        $parts = explode('/', $sprite);

        if (count($parts) !== 3) {
            throw new \Exception(sprintf('Unable to parse sprite name "%s".', $sprite));
        }

        [$directory, $prefix, $spriteName] = $parts;

        $directory .= '/';
        $prefix .= '/';
        $baseVariant = Strings::match($prefix, '~^([a-z]++)(?:-[0-9]++)?/$~')[1];

        switch ($baseVariant) {
            default:
                $weather = 'Normal/';
        }

        $size = $directory === 'portrait/' ? 'l/' : 'm/';
        $size = '';

        $destination = $directory . $prefix . $spriteName . $expression . '.png';

        $ryukishiSprite = $this->spriteMap[$spriteName] ?? null;
        if ($ryukishiSprite) {
            $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '-sprites/CG/' . $destination) . ' && cp "sprites/' . $weather . $size . $ryukishiSprite . '.png" ' . $this->chapter . '-sprites/CG/' . $destination;
        } else {
            $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '-sprites/CG/' . $destination) . ' && cp transparent.png ' . $this->chapter . '-sprites/CG/' . $destination;
        }
    }
}
