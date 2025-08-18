<?php declare(strict_types=1);

namespace TypedPatternEngine\Compiler;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Types\TypeRegistry;
use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;
use Throwable;

final class CompiledPattern
{
    /**
     * @param string $pattern
     * @param string $regex
     * @param AstNodeInterface $ast
     * @param array<string, string> $namedGroups
     * @param array<string, string> $groupTypes
     * @param array<string, array<string, mixed>> $groupConstraints
     * @param TypeRegistry $typeRegistry
     */
    public function __construct(
        private readonly string $pattern,
        private readonly string $regex,
        private readonly AstNodeInterface $ast,
        private readonly array $namedGroups,
        private readonly array $groupTypes,
        private readonly array $groupConstraints,
        private readonly TypeRegistry $typeRegistry
    ) {}

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getRegex(): string
    {
        return $this->regex;
    }

    public function getAst(): AstNodeInterface
    {
        return $this->ast;
    }

    /**
     * @return array<string, string>
     */
    public function getNamedGroups(): array
    {
        return $this->namedGroups;
    }

    /**
     * @return array<string, string>
     */
    public function getGroupTypes(): array
    {
        return $this->groupTypes;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public function getGroupConstraints(): array
    {
        return $this->groupConstraints;
    }

    /**
     * @throws TypeSystemException
     */
    public function match(string $input): ?MatchResult
    {
        // Empty input should not match patterns that consist entirely of optional groups
        if ($input === '') {
            return null;
        }
        
        if (!preg_match($this->regex, $input, $matches)) {
            return null;
        }

        $result = new MatchResult($input);

        foreach ($this->namedGroups as $groupId => $groupName) {
            // Check if group matched (empty string is valid for string types)
            $hasValue = isset($matches[$groupId]);

            if ($hasValue && $matches[$groupId] !== '') {
                $rawValue = $matches[$groupId];
                $type = $this->groupTypes[$groupName];
                $constraints = $this->groupConstraints[$groupName];
                $typeHandler = $this->typeRegistry->getTypeObject($type, $constraints);

                // Get the type handler
                try {
                    // Process value with type and constraints
                    $processedValue = $typeHandler->parseValue($rawValue);
                    $result->addGroup($groupName, $processedValue, $type, $constraints);
                } catch (Throwable $e) {
                    $result->addError($e);
                }
            } else {
                // Handle missing optional groups with default constraints
                $type = $this->groupTypes[$groupName];
                $constraints = $this->groupConstraints[$groupName];
                
                if (isset($constraints['default'])) {
                    // Get the type handler
                    $typeHandler = $this->typeRegistry->getTypeObject($type, $constraints);

                    try {
                        // Process null value to trigger default
                        $processedValue = $typeHandler->parseValue(null);
                        $result->addGroup($groupName, $processedValue, $type, $constraints);
                    } catch (PatternEngineInvalidArgumentException $e) {
                        $result->addError($e);
                    }
                }
            }
        }

        // Return result even if constraints failed - caller can check isFailed()
        return $result;
    }

    /**
     * @param array<string, mixed> $values
     */
    public function generate(array $values): string
    {
        // Extract just the values if we're given the full group data
        $cleanValues = [];
        foreach ($values as $key => $value) {
            if (is_array($value) && isset($value['value'])) {
                $cleanValues[$key] = $value['value'];
            } else {
                $cleanValues[$key] = $value;
            }
        }
        return $this->ast->generate($cleanValues);
    }
}
