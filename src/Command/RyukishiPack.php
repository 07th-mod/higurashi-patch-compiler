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
            if (in_array($line[2], ['CHIBI_SPRITE', 'RENA_WITH_SWORD'], true) || Strings::startsWith($line[2], 'NEW_CHAR_')) {
                continue;
            }
            $this->spriteMap[Strings::after($line[1], 'sprite/')] = $line[2];
        }
        fclose($handle);
    }

    private function finish(): void
    {
        $bashCopy = array_unique($this->bashCopy);
        array_unshift($bashCopy, 'mkdir -p ' . $this->chapter . '-sprites/OGSprites/portrait');
        array_unshift($bashCopy, 'mkdir -p ' . $this->chapter . '-sprites/OGSprites/sprite');

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

        $directory .= '/';
        // size is not used for now, we use regular sprites for portraits
        $size = $directory === 'portrait/' ? 'l/' : 'm/';

        $destination = $directory . $spriteName . $expression . '.png';

        $ryukishiSprite = $this->spriteMap[$spriteName] ?? null;

        if ($this->hasAnyPrefix($spriteName, self::MG_SPRITE_PREFIXES)) {
            if ($ryukishiSprite) {
                $this->bashCopy[] = 'cp "sprites/' . Strings::lower($ryukishiSprite) . '.png" ' . $this->chapter . '-sprites/OGSprites/' . $destination;
            } else {
                throw new \Exception('OG sprite should exist for "' . $spriteName . '".');
            }
        } elseif ($this->hasAnyPrefix($spriteName, self::PS_ONLY_SPRITE_PREFIXES)) {
            if ($ryukishiSprite) {
                throw new \Exception('OG sprite should NOT exist for "' . $spriteName . '".');
            } else {
                $this->bashCopy[] = 'cp transparent.png ' . $this->chapter . '-sprites/OGSprites/' . $destination;
            }
        } else {
            throw new \Exception('Unable to decide if sprite "' . $spriteName . '" should have OG sprite or not.');
        }
    }

    private function hasAnyPrefix(string $spriteName, array $prefixes): bool
    {
        foreach ($prefixes as $prefix) {
            if (Strings::match($spriteName, '~^' . $prefix . '(?:[0-9][ab]?)?_~')) {
                return true;
            }
        }

        return false;
    }

    private const MG_SPRITE_PREFIXES = [
        're',
        'me',
        'ri',
        'sa',
        'oisi',
        'tomi',
        'ta',
        'si',
        'kei',
        'sato',
        'tie',
        'kasa',
        'iri',
        'tetu',
        'aka',
        'rina',
        'rim',
        'chibimion',
        'renasen',
        'ha',
        'aks',
    ];

    private const PS_ONLY_SPRITE_PREFIXES = [
        'oko', // TODO: Okonogi should get sprites in Matsuribayashi

        'tomita',
        'oka',
        'kuma',
        'oryou',
        'kameda',
        'ki',
        'miyuki',
    ];
}
