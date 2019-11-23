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

class SpriteFilters extends Command
{
    use LineProcessorTrait;

    protected function configure(): void
    {
        $this
            ->setName('higurashi:sprite-filters')
            ->setDescription('Changes scripts to use sprite filters.')
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

    private $layers = [];

    private function initFile(): void
    {
        $this->layers = [];
    }

    protected function processLine(string $line, LineStorage $lines, int $lineNumber, string $filename): string
    {
        if (Strings::startsWith($line, '//')) {
            return $line;
        }

        if (
            ($match = Strings::match($line, '~^(\s++)ModDrawCharacter\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"([^"]*+)"\s*+,(.*)$~'))
            || ($match = Strings::match($line, '~^(\s++)ModDrawCharacterWithFiltering\(\s*+([0-9]++)\s*+,\s*+([0-9]++)\s*+,\s*+"([^"]*+)"\s*+,\s*+"([^"]*+)"\s*+,(.*)$~'))
        ) {
            $space = $match[1];
            $layer = $match[2];
            $sprite = $match[4];
            $spriteMatch = Strings::match($sprite, '~^[a-z]++(/([a-z]++)(?:-([0-9]++))?/)~');
            if (! $spriteMatch) {
                return $line;
            }
            $replace = $spriteMatch[1];
            $variant = $spriteMatch[2];
            $variant = $variant === 'normal' ? 'none' : $variant;
            if ($variant === 'dream') {
                $variant = 'none';
                $alpha = round(70 / 100 * 256);
            } else {
                $alpha = round(($spriteMatch[3] ?? 100) / 100 * 256);
            }
            $line = str_replace($replace, '/', $line);

            if (
                ! array_key_exists($layer, $this->layers)
                || $this->layers[$layer] !== [$variant, $alpha]
                // z files are included into the middle of other files so we force layer settings there always for simplicity
                || Strings::startsWith($filename, 'z')
            ) {
                $this->layers[$layer] = [$variant, $alpha];
                $filter = sprintf(
                    '%sModSetLayerFilter(%d, %d, "%s");',
                    $space,
                    $layer,
                    $alpha,
                    $variant
                );
                $line = $filter . "\n" . $line;
            }
        }

        // z files might have changed the layer settings.
        if (Strings::contains($line, 'ModCallScriptSection(')) {
            $this->layers = [];
        }

        return $line;
    }
}
