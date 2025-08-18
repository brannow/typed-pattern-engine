<?php declare(strict_types=1);

namespace TypedPatternEngine\Types\Constrains\NumberConstraints;

use TypedPatternEngine\Types\Constrains\BaseConstraint;
use TypedPatternEngine\Types\Constrains\Interfaces\BoundingConstraintInterface;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

class MaxConstraint extends BaseConstraint implements BoundingConstraintInterface
{
    public const NAME = 'max';

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $intValue = (int)$value;
        $maxValue = (int)$this->value;

        if ($intValue > $maxValue) {
            throw new PatternEngineInvalidArgumentException("Value $intValue exceeds maximum $maxValue");
        }

        return $intValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value): mixed
    {
        // Validation happens during parsing, just return the value for serialization
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function modifyPattern(string $basePattern): string
    {
        // v1.0: Constraints don't modify patterns, validation-only
        return $basePattern;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return true; // Max constraint caps greediness by limiting digits
    }
}
