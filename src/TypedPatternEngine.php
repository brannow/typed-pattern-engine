<?php declare(strict_types=1);

namespace TypedPatternEngine;

use TypedPatternEngine\Compiler\CompiledPatternFactory;
use TypedPatternEngine\Heuristic\HeuristicCompiler;
use TypedPatternEngine\Pattern\PatternCompiler;
use TypedPatternEngine\Types\TypeRegistry;
use TypedPatternEngine\Types\TypeRegistryInterface;
use TypedPatternEngine\Validation\ValidationPipelineFactory;

final class TypedPatternEngine
{
    private ?PatternCompiler $patternCompiler = null;
    private ?HeuristicCompiler $heuristicCompiler = null;

    public function __construct(
        private readonly TypeRegistryInterface $typeRegistry = new TypeRegistry()
    ) {}

    public function getPatternCompiler(): PatternCompiler
    {
        return $this->patternCompiler ??= new PatternCompiler(
            $this->typeRegistry,
            new CompiledPatternFactory($this->typeRegistry),
            (new ValidationPipelineFactory())->create()
        );
    }

    public function getHeuristicCompiler(): HeuristicCompiler
    {
        return $this->heuristicCompiler ??= new HeuristicCompiler();
    }
}
