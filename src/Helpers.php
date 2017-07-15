<?php

namespace Higurashi;

use Nette\Utils\Strings;

class Helpers
{
    public static function guessChapter(string $name): string
    {
        foreach (Constants::PATCHES as $chapter => $links) {
            if (Strings::startsWith($chapter, $name)) {
                return $chapter;
            }
        }

        return $name;
    }
}