<?php declare(strict_types=1);

namespace TypedPatternEngine\Pattern\Helper;

final class PatternGroupCounter implements PatternGroupCounterInterface
{
    private int $counter = 0;
    public function getCounter(): int
    {
        return $this->counter;
    }
    public function increaseCounter(): int
    {
        return ++$this->counter;
    }
}
