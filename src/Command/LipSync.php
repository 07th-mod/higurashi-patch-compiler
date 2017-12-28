<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Dibi\Exception;
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

class LipSync extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:lip-sync')
            ->setDescription('Changes scripts for Lip-Sync compatibility.')
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

    private $numbers = [
        'black' => 0,
        'white' => 0,
        'cinema' => 0,
        'Title' => 0,
        '0' => 0,
        'furiker' => 0,
        'logo' => 0,
        'cg_' => 0,
        'e1' => 0,
        'toketu' => 0,
        'no_data' => 0,
        'nort' => 0,

        'kei' => 1,
        're' => 2,
        'Re' => 2,
        'me' => 3,
        'sa' => 4,
        'ri' => 5,
        'si' => 6,
        'sato' => 7,
        'tm' => 8,
        'tomi' => 8,
        'ta' => 9,
        'ir' => 10,
        'oi' => 11,

        '?ha' => 12, // Hanyuu
        '?aka' => 13, // Akasaka
        '?oko' => 14, // Okonogi

        'kasa' => 15,
        'Kasa' => 15,
        'aka' => 16,
        'oryou' => 17,
        'ki' => 18,
        'kuma' => 19,
        'Kuma' => 19,

        '?rin' => 20, // Ritsuko

        'tetu' => 21,
        'ti' => 22,
        'Tie' => 22,
        'kameda' => 23,
        'tomita' => 24,
        'oka' => 25,

        '?chme' => 26, // Child Mion
        '?chri' => 27, // Child Rika

        'miyuki' => 34,

        '?chta' => 48, // Child Takano
    ];

    private $ignoredFiles = [
        'black',
        'cinema',
        'Title02',
        'logo',
        'furiker_a',
        'furiker_b',
        '01_a',
        '01_b',
        '01_b1',
        '01_b2',
        '01_b3',
        '01_b4',
        '01_b5',
        'cg_001c',
        'cg_001d',
        'e1',
    ];

    private $rules = [];

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        $ignored = false;

        if (Strings::startsWith($line, '//')) {
            return $line;
        }

        if ($match = Strings::match($line, '~^(\s++)PlayVoice\(\s*+([0-9]++)\s*+,\s*+"([^"]++)?",\s*+([0-9]++)\);$~')) {
            $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %d);', $match[1], $match[2], $this->getCharacterNumberForVoice($match[3]), $match[3], $match[4]) . "\n";
        }

        if ($match = Strings::match($line, '~^(\s++)DrawBustshot\(\s*+([0-9]++)\s*+,\s*+"([^"]++)",(.*)$~')) {
            if (in_array($match[3], $this->ignoredFiles, true)) {
                $ignored = true;
            } else {
                [$sprite, $expression] = $this->getNewSpriteName($match[3]);
                $line = sprintf('%sModDrawCharacter(%d, %d, "%s", "%s",%s', $match[1], $match[2], $this->getCharacterNumberForSprite($match[3]), $sprite, $expression, $match[4]) . "\n";
            }
        }

        if ($match = Strings::match($line, '~^(\s++)DrawBustshotWithFiltering\(\s*+([0-9]++)\s*+,\s*+"([^"]++)",(.*)$~')) {
            if (in_array($match[3], $this->ignoredFiles, true)) {
                $ignored = true;
            } else {
                [$sprite, $expression] = $this->getNewSpriteName($match[3]);
                $line = sprintf('%sModDrawCharacterWithFiltering(%d, %d, "%s", "%s",%s', $match[1], $match[2], $this->getCharacterNumberForSprite($match[3]), $sprite, $expression, $match[4]) . "\n";
            }
        }

        if (Strings::contains($line, 'PlayVoice(') || (Strings::contains($line, 'DrawBustshot') && ! $ignored)) {
            throw new \Exception(sprintf('Cannot parse line "%s:%d".', $filename, $lineNumber));
        }

        return $line;
    }

    private function init(): void
    {
        $handle = fopen(__DIR__ . '/../../data/rulefile.csv', 'r');

        if (! $handle) {
            throw new \Exception('Can\'t load rule file.');
        }

        while (($data = fgetcsv($handle, 1000, ",")) !== false) {
            if (isset($this->rules[$data[0]])) {
                throw new \Exception(sprintf('Duplicate rule found for sprite "%s".', $data[0]));
            }

            $this->rules[$data[0]] = [$data[1], $data[2]];
        }

        fclose($handle);
    }

    private function getNewSpriteName(string $sprite): array
    {
        $prefix = '';
        $directory = 'character/';

        if (Strings::startsWith($sprite, 'night/')) {
            $prefix = 'night/';
            $sprite = Strings::after($sprite, 'night/');
        }

        if (Strings::startsWith($sprite, 'sunset/')) {
            $prefix = 'sunset/';
            $sprite = Strings::after($sprite, 'sunset/');
        }

        if (Strings::endsWith($sprite, '_zoom')) {
            $directory = 'character_zoomed/';
            $sprite = Strings::before($sprite, '_zoom', -1);
        }

        if (! isset($this->rules[$sprite])) {
            printf('No rule found for sprite "%s".' . PHP_EOL, $sprite);

            return [$sprite, 0];
        }

        [$sprite, $expression] = $this->rules[$sprite];

        return [$directory . $prefix . $sprite, $expression];
    }

    private function getCharacterNumberForVoice(string $voice): int
    {
        if ($voice === '') {
            return 0;
        }

        $match = Strings::match($voice, '~^(?:ps2/)?[sS][0-9]++/0?([0-9]++)/~');

        if (! $match) {
            throw new \Exception(sprintf('Cannot parse voice "%s".', $voice));
        }

        return (int) $match[1];
    }

    private function getCharacterNumberForSprite(string $sprite): int
    {
        if (Strings::contains($sprite, '/')) {
            $sprite = Strings::after($sprite, '/', -1);
        }

        $prefixLength = 0;
        $character = null;

        foreach ($this->numbers as $prefix => $number) {
            if (Strings::startsWith($sprite, $prefix) && Strings::length($prefix) > $prefixLength) {
                $prefixLength = Strings::length($prefix);
                $character = $number;
            }
        }

        if ($character === null) {
            throw new \Exception(sprintf('Cannot get number for sprite "%s".', $sprite));
        }

        return $character;
    }
}