<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

interface CompilationPhaseAwareInterface
{
    public function onTreeComplete(): void;
}
