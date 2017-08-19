<?php

declare(strict_types=1);

namespace Higurashi\Utils;

class LineStorage implements \IteratorAggregate
{
    private $lines = [];

    public function add(string $line): void
    {
        $this->lines[] = $line;
    }

    public function get(int $index): string
    {
        return $this->lines[$this->calculateIndex($index)];
    }

    public function set(int $index, string $line): void
    {
        $this->lines[$this->calculateIndex($index)] = $line;
    }

    public function getIterator(): \Iterator
    {
        return new \ArrayIterator($this->lines);
    }

    private function calculateIndex(int $index): int
    {
        return $index < 0 ? (count($this->lines) + $index) : $index;
    }
}
