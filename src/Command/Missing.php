<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Constants;
use Higurashi\Helpers;
use Higurashi\Service\Cleaner;
use Nette\NotImplementedException;
use Nette\Utils\Strings;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class Missing extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:missing')
            ->setDescription('Detects missing files.')
            ->addArgument('directory', InputArgument::REQUIRED, 'StreamingAssets directory of the game.');
    }

    /**
     * @var string
     */
    private $directory;

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $directory = $input->getArgument('directory');

        if (! file_exists($directory) || ! is_dir($directory)) {
            throw new \Exception('Directory not found.');
        }

        $this->directory = realpath($directory);

        $scriptsDirectory = sprintf('%s/Update', $this->directory);

        $files = glob(sprintf('%s/*.txt', $scriptsDirectory));

        if ($files === []) {
            echo 'No files scripts found. The path given should usually end with "StreamingAssets".';
            return 1;
        }

        foreach ($files as $file) {
            foreach ($this->generateLines($file) as $line) {
                $this->processLine($line);
            }
        }

        return 0;
    }

    private function generateLines(string $filename): \Generator
    {
        $file = fopen($filename, 'r');
        while (!feof($file) && ($line = fgets($file)) !== false) {
            yield $line;
        }
        fclose($file);
    }

    private function processLine(string $line): void
    {
        if ($match = Strings::match($line, '~^(?:\s++)PlayVoice\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('voice', $match[1] . '.ogg');
            //$this->requireFile('spectrum', $match[1] . '.txt');
        }

        if ($match = Strings::match($line, '~^(?:\s++)ModPlayVoiceLS\(\s*+(?:[0-9]++)\s*+,\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('voice', $match[1] . '.ogg');
            //$this->requireFile('spectrum', $match[1] . '.txt');
        }

        if (($match = Strings::match($line, '~^(?:\s++)PlaySE\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)?"~')) && array_key_exists(1, $match)) {
            $this->requireFile('SE', $match[1] . '.ogg');
        }

        if ($match = Strings::match($line, '~^(?:\s++)PlayBGM\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)?"~')) {
            $this->requireFile('BGM', $match[1] . '.ogg');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:DrawBustshot|DrawSprite)\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('CG', $match[1] . '.png');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:DrawBustshotWithFiltering|DrawSpriteWithFiltering)\(\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('CG', $match[1] . '.png');
            $this->requireFile('CG', $match[2] . '.png');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:DrawBG|DrawScene|ChangeScene)\(\s*+"([^"]++)"~')) {
            $this->requireFile('CG', $match[1] . '.png');
        }

        if ($match = Strings::match($line, '~^(?:\s++)(?:DrawBGWithMask|DrawSceneWithMask)\(\s*+"([^"]++)"\s*+,\s*+"([^"]++)"~')) {
            $this->requireFile('CG', $match[1] . '.png');
            $this->requireFile('CG', $match[2] . '.png');
        }

        if (($match = Strings::match($line, '~^(?:\s++)ModDrawCharacter\(\s*+(?:[0-9]++)\s*+,\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"\s*+,\s*+"([0-9]++)"~')) && $match[1] !== 'transparent') {
            $this->requireFile('CG', $match[1] . 0 . '.png');
            $this->requireFile('CG', $match[1] . 1 . '.png');
            $this->requireFile('CG', $match[1] . 2 . '.png');
            if ($this->shouldHaveSteamSprite($match[1])) {
                $this->requireFile('CGAlt', $match[1] . $match[2] . '.png');
                $this->requireFile('OGSprites', $match[1] . $match[2] . '.png');
            }
        }

        if ($match = Strings::match($line, '~^(?:\s++)ModDrawCharacterWithFiltering\(\s*+(?:[0-9]++)\s*+,\s*+(?:[0-9]++)\s*+,\s*+"([^"]++)"\s*+,\s*+"([0-9]++)",\s*+"([^"]++)"\s*+~')) {
            $this->requireFile('CG', $match[1] . 0 . '.png');
            $this->requireFile('CG', $match[1] . 1 . '.png');
            $this->requireFile('CG', $match[1] . 2 . '.png');
            if ($this->shouldHaveSteamSprite($match[1])) {
                $this->requireFile('CGAlt', $match[1] . $match[2] . '.png');
                $this->requireFile('OGSprites', $match[1] . $match[2] . '.png');
            }
            $this->requireFile('CG', $match[3] . '.png');
        }

        if ($match = Strings::match($line, '~^\s++sprite_[a-z0-9_]++ = "([^"]*+)";$~')) {
            $this->requireFile('CG', $match[1] . 0 . '.png');
            $this->requireFile('CG', $match[1] . 1 . '.png');
            $this->requireFile('CG', $match[1] . 2 . '.png');
            if ($this->shouldHaveSteamSprite($match[1])) {
                $this->requireFile('CGAlt', $match[1] . 0 . '.png');
                $this->requireFile('OGSprites', $match[1] . 0 . '.png');
            }
        }
    }

    private $errors = [];

    private function requireFile(string $directory, string $file): void
    {
        $path = $this->directory . '/' . $directory . '/' . Strings::lower($file);

        if (! file_exists($path)) {
            $error = sprintf('File %s/%s not found.', $directory, $file);
            $this->reportError($error);
        } elseif (str_replace('\\', '/', $path) !== str_replace('\\', '/', realpath($path))) {
            $error = sprintf('File %s/%s does not match the realpath.', $directory, $file);
            $this->reportError($error);
        }

        if (
            $directory === 'CG'
            && (
                Strings::startsWith($file, 'text/')
                || Strings::startsWith($file, 'tips/')
                || Strings::startsWith($file, 'scenario/')
            )
            && ! Strings::endsWith($file, '_j.png')
            && ! in_array($file, ['scenario/background.png', 'scenario/stripes.png'], true)
        ) {
            $this->requireFile($directory, Strings::substring($file, 0, -4) . '_j.png');
        }
    }

    private function reportError(string $error): void
    {
        if (array_key_exists($error, $this->errors)) {
            return;
        }

        $this->errors[$error] = true;

        echo $error . PHP_EOL;
    }

    private function shouldHaveSteamSprite(string $sprite): bool
    {
        $sprite = Strings::after($sprite, '/', -1);

        if (
            Strings::startsWith($sprite, 'tomita') // Tomita, collision with Tomitake
            || Strings::startsWith($sprite, 'tama') // Tamako, collision with Takano
            || Strings::startsWith($sprite, 'hara') // Busstop sprite, collision with Hanyuu
        ) {
            return false;
        }

        if (
            Strings::startsWith($sprite, 're') // Reina
            || Strings::startsWith($sprite, 'me') // Mion
            || Strings::startsWith($sprite, 'ri') // Rika & Child Rika
            || Strings::startsWith($sprite, 'sa') // Satoko & Satoshi
            || Strings::startsWith($sprite, 'oisi') // Oishi
            || Strings::startsWith($sprite, 'tie') // Chie
            || Strings::startsWith($sprite, 'tomi') // Tomitake
            || Strings::startsWith($sprite, 'si') // Shion
            || Strings::startsWith($sprite, 'ta') // Takano
            || Strings::startsWith($sprite, 'iri') // Irie
            || Strings::startsWith($sprite, 'chibimion') // Child Mion
            || Strings::startsWith($sprite, 'kasa') // Kasai
            || Strings::startsWith($sprite, 'kei') // Keichi
            || Strings::startsWith($sprite, 'ha') // Hanyuu
            || Strings::startsWith($sprite, 'oko') // Okonogi
            || Strings::startsWith($sprite, 'mo1') // Outbreak mobs
            || Strings::startsWith($sprite, 'mo2')
            || Strings::startsWith($sprite, 'mo3')
            || Strings::startsWith($sprite, 'mura')
            || Strings::startsWith($sprite, 'une') // Une
            || Strings::startsWith($sprite, 'tamura') // Tamurahime
            // Somehow these were incorrectly listed as not having steam sprites until now?
            || Strings::startsWith($sprite, 'tetu') // Teppei
            || Strings::startsWith($sprite, 'aka') // Akane
            || Strings::startsWith($sprite, 'aks') // Akasaka
            || Strings::startsWith($sprite, 'kameda') // Kameda
        ) {
            return true;
        }

        if (
            Strings::startsWith($sprite, 'oka') // Okamura
            || Strings::startsWith($sprite, 'ki') // Kimiyoshi
            || Strings::startsWith($sprite, 'miyuki') // Miyuki
            || Strings::startsWith($sprite, 'oryou') // Oryou
            || Strings::startsWith($sprite, 'kuma') // Kumagai
            || Strings::startsWith($sprite, 'chisa') // Chisato
            || Strings::startsWith($sprite, 'huji') // Fujita
            || Strings::startsWith($sprite, 'mado') // Madoka
            || Strings::startsWith($sprite, 'na') // Natsumi
            || Strings::startsWith($sprite, 'tomo') // Tomoe
            || Strings::startsWith($sprite, 'tou') // Akira
            || Strings::startsWith($sprite, 'yamaoki') // Kaoru
            || Strings::startsWith($sprite, 'miyo') // Child Takano
            || Strings::startsWith($sprite, 'ama') // Amakusa
            || Strings::startsWith($sprite, 'kumi') // Bodyguards
            // Busstop sprites, they technically do exist but are ugly. ~Olga
            || Strings::startsWith($sprite, 'hmi2a')
            || Strings::startsWith($sprite, 'hri2a')
            || Strings::startsWith($sprite, 'hton1a')
            || Strings::startsWith($sprite, 'hyos1a')
            || Strings::startsWith($sprite, 'hoka1a')
            || Strings::startsWith($sprite, 'hhos1a')
            || Strings::startsWith($sprite, 'hhot1a')
            || Strings::startsWith($sprite, 'hoda1a')
            || Strings::startsWith($sprite, 'hnit1a')
        ) {
            return false;
        }

        throw new NotImplementedException(sprintf('Not sure if sprite %s should have steam sprite.', $sprite));
    }
}
