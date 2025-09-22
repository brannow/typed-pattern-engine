<?php declare(strict_types=1);

namespace TypedPatternEngine\Types;

use TypedPatternEngine\Types\Constrains\DefaultConstraint;
use TypedPatternEngine\Types\Constrains\NumberConstraints\MaxConstraint;
use TypedPatternEngine\Types\Constrains\NumberConstraints\MinConstraint;
use TypedPatternEngine\Exception\PatternRuntimeException;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

final class IntType extends Type
{
    protected const DEFAULT_NAME = 'int';
    protected const TYPE_NAMES_ALIASES = ['integer'];

    protected string $pattern = '\d+';
    protected array $characterClasses = ['0-9','-'];

    /**
     * @internal
     * @return string[] return the supported Constraint classes for that Type
     */
    public function getSupportedConstraintClasses(): array
    {
        return [
            MinConstraint::class,
            MaxConstraint::class,
            DefaultConstraint::class
        ];
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function parseValue(mixed $value): mixed
    {
        // Apply constraints first (including defaults) 
        $value = parent::parseValue($value);
        
        // Then validate the processed value
        if (!is_numeric($value)) {
            throw new PatternEngineInvalidArgumentException("Value must be numeric for int type, got: " . gettype($value));
        }
        
        // Reject decimal numbers for int type (per compiler-syntax.md:250)
        if (is_string($value) && str_contains($value, '.')) {
            throw new PatternEngineInvalidArgumentException("Value must be numeric for int type, got: " . gettype($value));
        }
        
        return (int)$value;
    }

    public function applyBoundary(string $pattern, ?string $boundary): string
    {
        if ($boundary === null) {
            return $pattern;
        }

        // Int type knows its pattern is \d+ and uses positive lookahead
        $escapedBoundary = preg_quote($boundary, '/');
        return $pattern . '(?=' . $escapedBoundary . '|$)';
    }

    /**
     * @param mixed $value
     * @return string
     * @throws PatternRuntimeException
     */
    public function serialize(mixed $value): string
    {
        if (!is_int($value)) {
            throw new PatternRuntimeException(
                'Expected integer value, got ' . gettype($value),
                '',
                '',
                'type_validation',
                $value
            );
        }
        return parent::serialize($value);
    }

    /**
     * @inheritDoc
     */
    public function isGreedy(): bool
    {
        // v1.0: Int type is always greedy, constraints don't affect greediness
        return true;
    }

    /**
     * @param mixed $value
     * @return bool
     */
    public function isDefaultValue(mixed $value): bool
    {
        $default = $this->getConstraint('default');
        return $default && $value !== null && ((int)($default->getValue() ?? null)) === ((int)$value);
    }
}
