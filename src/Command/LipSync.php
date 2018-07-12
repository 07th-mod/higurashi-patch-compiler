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
        'transparent' => 0,
        'nort' => 0,
        'oni_' => 0,
        'waku_' => 0,
        'sora' => 0,
        'oki_' => 0,

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
        'aks' => 13, // Akasaka
        '?oko' => 14, // Okonogi

        'kasa' => 15,
        'Kasa' => 15,
        'aka' => 16,
        'oryou' => 17,
        'ki' => 18,
        'kuma' => 19,
        'Kuma' => 19,

        '?rin' => 20, // Ritsuko - POSSIBLE COLLISION WITH RIKA!!

        'tetu' => 21,
        'ti' => 22,
        'Tie' => 22,
        'kameda' => 23,
        'tomita' => 24,
        'oka' => 25,

        'chibimion_' => 26,
        // Correct prefixes for child Mion and Rika are added in init method only for himatsubushi.
        '?chme' => 26, // Child Mion
        '?chri' => 27, // Child Rika

        'miyuki' => 34,

        '?chta' => 48, // Child Takano

        // console characters
        'chisa' => 37,
        'huji' => 31,
        'mado' => 29,
        'na' => 36,
        'tama' => 38,
        'tomo' => 28,
        'tou' => 39,
        'yamaoki' => 30,
    ];

    private $ignoredFiles = [
        '',
        'alphaimage',
        'black',
        'white',
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
        '203a',
        '203b',
        'hina1_1',
        'hina1_2',
        'hina1_3',
        'hina1_4',
        'no_data',
        'transparent',
        'title02',
        'toketu1a',
        'toketu1b',
        'toketu1c',
        'nort_mono1',
        'nort_mono2',
        'nort_mono3',
        'nort_mono4',
        'nort_mono5',
        'nort_mono6',
        'aka1',
        'aka2',
        'sora',
        'sora2',
        'oki_tv1',
    ];

    private $bustshots = [];

    private function initFile(): void
    {
        $this->bustshots = [];
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        $ignored = false;

        if (Strings::startsWith($line, '//')) {
            return $line;
        }

        if ($match = Strings::match($line, '~^(\s++)PlayVoice\(\s*+([0-9]++)\s*+,\s*+"([^"]++)?"\s*+,\s*+([0-9]++)\);$~')) {
            $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %d, TRUE);', $match[1], $match[2], $this->getCharacterNumberForVoice($match[3]), Strings::lower($match[3]), $match[4]) . "\n";
        }

        if ($match = Strings::match($line, '~^(\s++)DrawBustshot\(\s*+([0-9]++)\s*+,\s*+"([^"]*+)"\s*+,(.*)$~')) {
            $layer = $match[2];

            if ($this->ignoreDrawBustshot($match[3])) {
                $ignored = true;
            } else {
                [$sprite, $expression] = $this->getNewSpriteName($match[3]);
                $line = sprintf('%sModDrawCharacter(%d, %d, "%s", "%s",%s', $match[1], $layer, $this->getCharacterNumberForSprite($match[3]), $sprite, $expression, $match[4]) . "\n";
                $this->bustshots[$layer] = $line;
            }
        }

        if ($match = Strings::match($line, '~^(\s++)DrawBustshotWithFiltering\(\s*+([0-9]++)\s*+,\s*+"([^"]*+)"\s*+,\s*+"([^"]*+)"\s*+,(.*)$~')) {
            $layer = $match[2];
            $effect = $match[4];

            if ($this->ignoreDrawBustshot($match[3])) {
                $ignored = true;
            } else {
                [$sprite, $expression] = $this->getNewSpriteName($match[3]);
                $line = sprintf('%sModDrawCharacterWithFiltering(%d, %d, "%s", "%s", "%s",%s', $match[1], $layer, $this->getCharacterNumberForSprite($match[3]), $sprite, $expression, $effect, $match[5]) . "\n";
                $this->bustshots[$layer] = $line;
            }
        }

        if (($match = Strings::match($line, '~^(\s++)ChangeBustshot\(\s*+([0-9]++)\s*+,\s*+"([^"]*+)"\s*+,(.*)$~')) && !Strings::startsWith($match[3], 'end_')) {
            $layer = $match[2];

            [$sprite, $expression] = $this->getNewSpriteName($match[3]);

            $parameters = [
                'x' => 0,
                'y' => 0,
                'z' => 0,
                'move' => 'FALSE',
                'oldx' => 0,
                'oldy' => 0,
                'oldz' => 0,
                'unused1' => 0,
                'unused2' => 0,
                'unused3' => 0,
                'type' => 0,
                'priority' => 0,
            ];

            if (!isset($this->bustshots[$layer])) {
                throw new \Exception('Unable to find DrawBustshot for: ' . trim($line));
            }

            $call = explode(',', $this->bustshots[$layer]);

            if (Strings::contains($this->bustshots[$layer], 'WithFiltering')) {
                if (count($call) !== 17) {
                    throw new \Exception('Unable to parse line: ' . $this->bustshots[$layer]);
                }

                if (trim($call[8]) !== 'FALSE') {
                    throw new \Exception('Unsupported DrawBustshot: ' . $this->bustshots[$layer]);
                }

                $parameters['x'] = trim($call[6]);
                $parameters['y'] = trim($call[7]);
                $parameters['z'] = trim($call[12]);
                $parameters['priority'] = trim($call[14]);
            } else {
                if (count($call) !== 18) {
                    throw new \Exception('Unable to parse line: ' . $this->bustshots[$layer]);
                }

                if (trim($call[7]) !== 'FALSE') {
                    throw new \Exception('Unsupported DrawBustshot: ' . $this->bustshots[$layer]);
                }

                $parameters['x'] = trim($call[4]);
                $parameters['y'] = trim($call[5]);
                $parameters['z'] = trim($call[6]);
                $parameters['priority'] = trim($call[15]);
            }

            $line = sprintf('%sModDrawCharacter(%d, %d, "%s", "%s", %s,%s', $match[1], $match[2], $this->getCharacterNumberForSprite(strtolower($match[3])), $sprite, $expression, implode(', ', $parameters), $match[4]) . "\n";
        }

        if ((Strings::contains($line, 'PlayVoice(') || (Strings::contains($line, 'DrawBustshot') && ! $ignored)) && ! Strings::match($line, '~\s*+//~')) {
            throw new \Exception(sprintf('Cannot parse line "%s:%d".', $filename, $lineNumber));
        }

        return $line;
    }

    private function getNewSpriteName(string $sprite): array
    {
        if (! Strings::startsWith($sprite, 'sprite/') && ! Strings::startsWith($sprite, 'portrait/')) {
            throw new \Exception('Unknown prefix for sprite: ' . $sprite);
        }

        $match = Strings::match($sprite, '~^(sprite|portrait)/(normal|sunset|night|flashback)/([a-zA-Z0-9_]+)([0-2])$~');

        if (! $match) {
            throw new \Exception('Invalid sprite name: ' . $sprite);
        }

        return [
            $match[1] . '/' . $match[2] . '/' . $this->formatSpriteName($match[3]),
            $match[4],
        ];
    }

    private function getCharacterNumberForVoice(string $voice): int
    {
        if ($voice === '' || Strings::startsWith($voice, 's00/n/')) {
            return 0;
        }

        $match = Strings::match($voice, '~^ps[23]/(?:[sS][0-9]++/)?0?([0-9]++)/~');

        if (! $match) {
            throw new \Exception(sprintf('Cannot parse voice "%s".', $voice));
        }

        return (int) $match[1];
    }

    private function ignoreDrawBustshot(string $sprite): bool
    {
        return in_array(Strings::lower($sprite), $this->ignoredFiles, true) || Strings::startsWith($sprite, 'scene/');
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

    private function formatSpriteName(string $name): string
    {
        $name = Strings::replace(
            $name,
            '~([^_])([A-Z])~',
            function (array $matches): string {
                return $matches[1] . '_' . $matches[2];
            }
        );

        return Strings::lower($name);
    }
}
