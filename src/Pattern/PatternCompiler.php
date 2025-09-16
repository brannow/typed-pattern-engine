<?php declare(strict_types=1);

namespace TypedPatternEngine\Pattern;

use TypedPatternEngine\Compiler\CompiledPattern;
use TypedPatternEngine\Compiler\CompiledPatternFactory;
use TypedPatternEngine\Exception\PatternCompilationException;
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Nodes\NodeRegistryInterface;
use TypedPatternEngine\Types\TypeRegistryInterface;
use TypedPatternEngine\Validation\ValidatorInterface;

final class PatternCompiler
{
    public function __construct(
        private readonly NodeRegistryInterface $nodeRegistry,
        private readonly TypeRegistryInterface $typeRegistry,
        private readonly CompiledPatternFactory $factory,
        private readonly ValidatorInterface $validator
    ) {}

    /**
     * @param string $pattern
     * @return CompiledPattern
     * @throws PatternSyntaxException
     * @throws TypeSystemException
     * @throws PatternValidationException
     */
    public function compile(string $pattern): CompiledPattern
    {
        // Parse
        $astRootNode = (new PatternParser($this->nodeRegistry, $this->typeRegistry, $pattern))->parse();

        // Validate using injected validator pipeline
        $this->validator->validate($astRootNode);
        
        // Create compiled pattern
        return $this->factory->create($pattern, $astRootNode);
    }

    /**
     * @param CompiledPattern $compiledPattern
     * @return array<string, mixed>
     */
    public function dehydrate(CompiledPattern $compiledPattern): array
    {
        return $this->factory->dehydrate($compiledPattern);
    }

    /**
     * @param array<string, mixed> $compiledPatternData
     * @return CompiledPattern
     * @throws PatternCompilationException
     * @throws PatternValidationException
     */
    public function hydrate(array $compiledPatternData): CompiledPattern
    {
        return $this->factory->hydrate($compiledPatternData);
    }
}
