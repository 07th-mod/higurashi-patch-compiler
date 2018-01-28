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
        'oni_' => 0,
        'waku_' => 0,

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

        'chibimion_' => 26,
        // Correct prefixes for child Mion and Rika are added in init method only for himatsubushi.
        '?chme' => 26, // Child Mion
        '?chri' => 27, // Child Rika

        'miyuki' => 34,

        '?chta' => 48, // Child Takano
    ];

    private $ignoredFiles = [
        '',
        'alphaimage',
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
        '203a',
        '203b',
        'hina1_1',
        'hina1_2',
        'hina1_3',
        'hina1_4',
    ];

    private $textFilePrefixes = [
        'onik0',
        'tyuui',
        'si_onikakusi',
        't_ep',
        'tata0',
        'tatari_list',
        'si_tatarigorosi',
    ];

    private $spriteRules = [];

    private $bgRules = [];

    private $cgRules = [];

    private $bashCopy = [];

    private $errors = [];

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        $ignored = false;

        if (Strings::startsWith($line, '//')) {
            return $line;
        }

        if ($match = Strings::match($line, '~^(\s++)PlayVoice\(\s*+([0-9]++)\s*+,\s*+"([^"]++)?",\s*+([0-9]++)\);$~')) {
            $line = sprintf('%sModPlayVoiceLS(%d, %d, "%s", %d, TRUE);', $match[1], $match[2], $this->getCharacterNumberForVoice($match[3]), $match[3], $match[4]) . "\n";
        }

        if ($match = Strings::match($line, '~^(\s++)DrawBustshot\(\s*+([0-9]++)\s*+,\s*+"([^"]*+)",(.*)$~')) {
            if (in_array($match[3], $this->ignoredFiles, true)) {
                $ignored = true;
            } else {
                [$sprite, $expression] = $this->getNewSpriteName($match[3]);
                $line = sprintf('%sModDrawCharacter(%d, %d, "%s", "%s",%s', $match[1], $match[2], $this->getCharacterNumberForSprite($match[3]), $sprite, $expression, $match[4]) . "\n";
            }
        }

        if ($match = Strings::match($line, '~^(\s++)DrawBustshotWithFiltering\(\s*+([0-9]++)\s*+,\s*+"([^"]*+)",(.*)$~')) {
            if (in_array($match[3], $this->ignoredFiles, true)) {
                $ignored = true;
            } else {
                [$sprite, $expression] = $this->getNewSpriteName($match[3]);
                $line = sprintf('%sModDrawCharacterWithFiltering(%d, %d, "%s", "%s",%s', $match[1], $match[2], $this->getCharacterNumberForSprite($match[3]), $sprite, $expression, $match[4]) . "\n";
            }
        }

        if ((Strings::contains($line, 'PlayVoice(') || (Strings::contains($line, 'DrawBustshot') && ! $ignored)) && ! Strings::match($line, '~\s*+//~')) {
            throw new \Exception(sprintf('Cannot parse line "%s:%d".', $filename, $lineNumber));
        }

        if ($match = Strings::match($line, '~^(\s++)(DrawBG|DrawScene|DrawSceneWithMask)\(\s*+"([^"]*+)",(.*)$~')) {
            $bg = $match[3];
            $rest = Strings::trim($match[4]);

            if (Strings::startsWith($bg, 'cg_')) {
                $cg = Strings::substring($bg, 3);

                $line = sprintf('%s%s("scene/%s", %s', $match[1], $match[2], $cg, $rest) . "\n";

                $this->addBashCopyForCG($cg);
            } elseif (array_key_exists($bg, $this->cgRules)) {
                $cg = $this->cgRules[$bg];

                $line = sprintf('%s%s("scene/%s", %s', $match[1], $match[2], $cg, $rest) . "\n";

                $this->addBashCopyForCG($cg);
            } elseif (array_key_exists($bg, $this->bgRules)) {
                $line = sprintf('%s%s("background/%s", %s', $match[1], $match[2], $this->bgRules[$bg], $rest) . "\n";

                $this->addBashCopyForBG($this->bgRules[$bg]);
            } else {
                if (!array_key_exists($bg, $this->errors)) {
                    $this->errors[$bg] = true;
                    printf('Rule for background "%s" not found.' . PHP_EOL, $bg);
                }

                $bgDestination = $this->isTextFile($bg) ? 'text/' . Strings::lower($bg) : Strings::lower($bg);

                $line = sprintf('%s%s("%s", %s', $match[1], $match[2], $bgDestination, $rest) . "\n";

                $this->addBashCopyForOriginal($bg, $this->isTextFile($bg) ? 'text' : '');
            }
        }

        if ($match = Strings::match($line, '~^(\s++)(DrawBustshot|DrawBustshotWithFiltering)\(\s*+([0-9]++)\s*+,\s*+"([^"]++)",(.*)$~')) {
            $bg = $match[4];
            $rest = Strings::trim($match[5]);

            if (array_key_exists($bg, $this->cgRules)) {
                $cg = $this->cgRules[$bg];

                $line = sprintf('%s%s(%d, "scene/%s", %s', $match[1], $match[2], $match[3], $cg, $rest) . "\n";

                $this->addBashCopyForCG($cg);
            } elseif (array_key_exists($bg, $this->bgRules)) {
                $line = sprintf('%s%s(%d, "background/%s", %s', $match[1], $match[2], $match[3], $this->bgRules[$bg], $rest) . "\n";

                $this->addBashCopyForBG($this->bgRules[$bg]);
            } else {
                if (!array_key_exists($bg, $this->errors)) {
                    $this->errors[$bg] = true;
                    printf('Rule for background "%s" not found.' . PHP_EOL, $bg);
                }

                $bgDestination = $this->isTextFile($bg) ? 'text/' . Strings::lower($bg) : Strings::lower($bg);

                $line = sprintf('%s%s(%d, "%s", %s', $match[1], $match[2], $match[3], $bgDestination, $rest) . "\n";

                $this->addBashCopyForOriginal($bg, $this->isTextFile($bg) ? 'text' : '');
            }
        }

        return $line;
    }

    private function init(): void
    {
        $this->loadSpritesCsv(__DIR__ . '/../../data/sprites/rulefile.csv', false);
        $this->loadSpritesCsv(__DIR__ . '/../../data/sprites/tatahimazoom.csv', false);

        if ($this->chapter === 'himatsubushi') {
            $this->loadSpritesCsv(__DIR__ . '/../../data/sprites/child.csv', true);
            $this->loadSpritesCsv(__DIR__ . '/../../data/sprites/childzoom.csv', true);

            $this->numbers['me_si_'] = 26;
            $this->numbers['ri_si_'] = 27;
            $this->numbers['rim_'] = 27;
        }

        $this->loadBGsCsv(__DIR__ . '/../../data/bgs/' . $this->chapter . '.csv');

        if ($this->chapter === 'onikakushi') {
            $this->loadCGsCsv(__DIR__ . '/../../data/cgs/' . $this->chapter . '.csv');
        }
    }

    private function finish(): void
    {
        $bashCopy = array_unique($this->bashCopy);

        $file = sprintf('%s/%s/%s.sh', TEMP_DIR, strtolower((new \ReflectionClass($this))->getShortName()), $this->chapter);

        file_put_contents($file, implode("\n", $bashCopy));
    }

    private function loadSpritesCsv(string $file, bool $override): void
    {
        foreach ($this->loadCsv($file) as $data) {
            if (!$override && isset($this->spriteRules[$data[0]])) {
                throw new \Exception(sprintf('Duplicate rule found for sprite "%s".', $data[0]));
            }

            $this->spriteRules[$data[0]] = [$data[1], $data[2]];
        }
    }

    private function loadBGsCsv(string $file): void
    {
        foreach ($this->loadCsv($file) as $data) {
            if (isset($this->bgRules[$data[0]])) {
                throw new \Exception(sprintf('Duplicate rule found for BG "%s".', $data[0]));
            }

            $this->bgRules[$data[0]] = $data[1] ?? $data[0];
        }
    }

    private function loadCGsCsv(string $file): void
    {
        foreach ($this->loadCsv($file) as $data) {
            if (isset($this->cgRules[$data[0]])) {
                throw new \Exception(sprintf('Duplicate rule found for CG "%s".', $data[0]));
            }

            $this->cgRules[$data[0]] = $data[1] ?? $data[0];
        }
    }

    private function loadCsv(string $file): \Generator
    {
        $handle = fopen($file, 'r');

        if (! $handle) {
            throw new \Exception(sprintf('Can\'t load rule file "%s".', $file));
        }

        while (($data = fgetcsv($handle, 1000, ',')) !== false) {
            yield $data;
        }

        fclose($handle);
    }

    private function getNewSpriteName(string $sprite): array
    {
        $original = $sprite;
        $prefix = 'normal/';
        $directory = 'sprite/';

        if (Strings::startsWith($sprite, 'night/')) {
            $prefix = 'night/';
            $sprite = Strings::after($sprite, 'night/');
        }

        if (Strings::startsWith($sprite, 'Night/')) {
            $prefix = 'night/';
            $sprite = Strings::after($sprite, 'Night/');
        }

        if (Strings::endsWith($sprite, '_a')) {
            $prefix = 'night/';
            $sprite = Strings::before($sprite, '_a', -1);
        }

        if (Strings::startsWith($sprite, 'sunset/')) {
            $prefix = 'sunset/';
            $sprite = Strings::after($sprite, 'sunset/');
        }

        if (Strings::startsWith($sprite, 'Sunset/')) {
            $prefix = 'sunset/';
            $sprite = Strings::after($sprite, 'Sunset/');
        }

        if (Strings::endsWith($sprite, '_b')) {
            $prefix = 'Strings/';
            $sprite = Strings::before($sprite, '_b', -1);
        }

        if (Strings::endsWith($sprite, '_zoom')) {
            $directory = 'portrait/';
            $sprite = Strings::before($sprite, '_zoom', -1);
        }

        if ($this->chapter === 'tatarigoroshi') {
            if ($sprite === 'iri2_Majime_0') {
                $directory = 'portrait/';
                $prefix = 'sunset/';
            }

            if (Strings::startsWith($sprite, 'sa1a_') || Strings::startsWith($sprite, 'sa5_')) {
                $directory = 'portrait/';
            }
        }

        if ($this->chapter === 'himatsubushi') {
            if ($sprite === 'oisi1_2_0' || $sprite === 'oisi2_8_0') {
                $directory = 'portrait/';
            }

            if (Strings::startsWith($sprite, 'rim_') || Strings::startsWith($sprite, 'iri2_') || Strings::startsWith($sprite, 'chibimion_')) {
                $directory = 'portrait/';
            }

            if ($sprite === 'oryou_Warai_0' && $prefix === 'sunset/') {
                $directory = 'portrait/';
            }
        }

        if (! isset($this->spriteRules[$sprite])) {
            printf('No rule found for sprite "%s".' . PHP_EOL, $sprite);

            return [$sprite, 0];
        }

        [$sprite, $expression] = $this->spriteRules[$sprite];

        $this->addBashCopyForSprite($directory, $prefix, $sprite, $original, $expression);

        return [$directory . $prefix . $this->formatSpriteName($sprite), $expression];
    }

    private function getCharacterNumberForVoice(string $voice): int
    {
        if ($voice === '' || Strings::startsWith($voice, 's00/n/')) {
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

    private function addBashCopyForSprite(string $directory, string $prefix, string $sprite, string $original, string $expression): void
    {
        switch ($prefix) {
            case 'night/':
                $weather = 'Night/';
                break;
            case 'sunset/':
                $weather = 'Sunset/';
                break;
            default:
                $weather = 'Normal/';
        }

        $size = $directory === 'portrait/' ? 'l/' : 'm/';

        $destination = $directory . $prefix . $this->formatSpriteName($sprite) . '%s.png';

        $command = 'mkdir -p ' . dirname($this->chapter . '/CG/' . $destination) . ' && cp sprites/' . $weather . $size . $sprite . '%s.png ' . $this->chapter . '/CG/' . $destination;

        $this->bashCopy[] = sprintf($command, 0, 0);
        $this->bashCopy[] = sprintf($command, 1, 1);
        $this->bashCopy[] = sprintf($command, 2, 2);

        $this->bashCopy[] = sprintf('mkdir -p ' . dirname($this->chapter . '/CGAlt/' . $destination) . ' && cp ' . $this->chapter . '-old/CGAlt/' . $original . '.png ' .  $this->chapter . '/CGAlt/' . $destination, $expression);
    }

    private function addBashCopyForCG(string $cg): void
    {
        $destination = 'scene/' . $cg . '.png';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/CG/' . $destination) . ' && cp ps3/e/' . $cg . '.png ' . $this->chapter . '/CG/' . $destination;
    }

    private function addBashCopyForBG(string $bg): void
    {
        $destination = 'background/' . $bg . '.png';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/CG/' . $destination) . ' && cp ps3/' . $bg . '.png ' . $this->chapter . '/CG/' . $destination;
    }

    private function addBashCopyForOriginal(string $image, string $targeDirectory = ''): void
    {
        $destination = ($targeDirectory ? $targeDirectory . '/' : '') . Strings::lower($image) . '.png';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/CG/' . $destination) . ' && cp ' . $this->chapter . '-old/CG/' . $image . '.png ' . $this->chapter . '/CG/' . $destination;

        $destination = ($targeDirectory ? $targeDirectory . '/' : '') . Strings::lower($image) . '_j.png';

        $this->bashCopy[] = 'mkdir -p ' . dirname($this->chapter . '/CG/' . $destination) . ' && cp ' . $this->chapter . '-old/CG/' . $image . '_j.png ' . $this->chapter . '/CG/' . $destination;
    }

    private function isTextFile(string $bg): bool
    {
        foreach ($this->textFilePrefixes as $prefix) {
            if (Strings::startsWith($bg, $prefix)) {
                return true;
            }
        }

        return false;
    }
}
