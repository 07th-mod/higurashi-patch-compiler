<?php

declare(strict_types=1);

namespace Higurashi\Command;

use Higurashi\Service\ScriptParser;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class ParseScript extends Command
{
    protected function configure(): void
    {
        $this
            ->setName('higurashi:parse')
            ->setDescription('Parses the PS3 script.');
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $parser = new ScriptParser(__DIR__ . '/../../data/HigurashiPS3-Script.txt');

        foreach ($parser->parse() as [$name, $voice, $text, $line]) {

        }

        return 0;
    }
}
