<?php

declare(strict_types=1);

namespace Higurashi\Service;

use Nette\Utils\Strings;

class ScriptParser
{
    private const JAPANESE_CHARACTER = '[\\x{FF08}-\\x{FF19}\\x{FF06}\\x{FF1F}\\x{FF30}-\\x{FF36}\\x{4E00}-\\x{9FBF}\\x{3040}-\\x{309F}\\x{30A0}-\\x{30FF}]';

    /**
     * @var string
     */
    private $script;

    public function __construct(string $script)
    {
        $this->script = $script;
    }

    public function parse(): \Generator
    {
        $file = fopen($this->script, 'r');
        $i = 0;
        while (!feof($file)) {
            ++$i;
            $line = fgets($file);
            if ($line) {
                yield from $this->parseLine($i, $line);
            }
        }
        fclose($file);
    }

    private function parseLine(int $i, string $line): \Generator
    {
        $match = Strings::match($line, '~^(?:c[0-9]{3}\\.r?)?(' . self::JAPANESE_CHARACTER . '*+)((?:[a-z0-9.|-]+S[0-9]{2}/[0-9]{2}/[^.]++\\.[^krv]++)+)~u');

        if (! $match) {
            return;
        }

        $matches = Strings::matchAll($match[2], '~(S[0-9]{2}/[0-9]{2}/[^.]++)\\.([^a-z]++)~');

        foreach ($matches as $voice) {
            yield [
                $match[1], // character name
                $voice[1], // voice file
                $voice[2], // japanese text
                $i,        // line number
            ];
        }
    }
}
