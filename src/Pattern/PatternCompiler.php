<?php declare(strict_types=1);

namespace TypedPatternEngine\Pattern;

use TypedPatternEngine\Compiler\CompiledPattern;
use TypedPatternEngine\Compiler\CompiledPatternFactory;
use TypedPatternEngine\Types\TypeRegistry;
use TypedPatternEngine\Validation\ValidatorInterface;
use TypedPatternEngine\Exception\PatternCompilationException;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\TypeSystemException;

final class PatternCompiler
{
    public function __construct(
        private readonly TypeRegistry $typeRegistry,
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
        $astRootNode = (new PatternParser($this->typeRegistry, $pattern))->parse();

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
