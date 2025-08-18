<?php declare(strict_types=1);

namespace TypedPatternEngine\Pattern\Helper;

interface PatternGroupCounterInterface
{
    public function getCounter(): int;
    public function increaseCounter(): int;
}
