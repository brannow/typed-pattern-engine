<?php declare(strict_types=1);

namespace TypedPatternEngine\Heuristic;


use TypedPatternEngine\Compiler\CompiledPattern;

final class HeuristicCompiler
{
    /**
     * Compile heuristic from compiled patterns
     *
     * @param iterable<CompiledPattern> $compiledPatterns
     * @return PatternHeuristic
     */
    public function compile(iterable $compiledPatterns): PatternHeuristic
    {
        return PatternHeuristic::buildFromPatterns($compiledPatterns);
    }

    /**
     * Recreate heuristic from cached data
     *
     * @param array<string, mixed> $data
     * @return PatternHeuristic
     */
    public function hydrate(array $data): PatternHeuristic
    {
        return PatternHeuristic::fromArray($data);
    }

    /**
     * Convert heuristic to cacheable array
     *
     * @param PatternHeuristic $heuristic
     * @return array<string, mixed>
     */
    public function dehydrate(PatternHeuristic $heuristic): array
    {
        return $heuristic->toArray();
    }
}
