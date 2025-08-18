<?php declare(strict_types=1);

namespace TypedPatternEngine\Heuristic;

interface HeuristicPatternInterface
{
    /**
     * @param string $string
     * @return bool
     */
    public function support(string $string): bool;
}
