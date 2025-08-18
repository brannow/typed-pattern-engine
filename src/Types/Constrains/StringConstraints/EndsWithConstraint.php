<?php declare(strict_types=1);

namespace TypedPatternEngine\Types\Constrains\StringConstraints;

use TypedPatternEngine\Types\Constrains\BaseConstraint;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

class EndsWithConstraint extends BaseConstraint
{
    public const NAME = 'endsWith';

    /**
     * @inheritDoc
     */
    public function parseValue(mixed $value): mixed
    {
        if ($value === null) {
            return null; // Let default constraint handle this
        }

        $stringValue = (string)$value;
        $suffix = $this->unescapeString((string)$this->value);

        if (!str_ends_with($stringValue, $suffix)) {
            throw new PatternEngineInvalidArgumentException("String '$stringValue' does not end with '$suffix'");
        }

        return $stringValue;
    }

    /**
     * @inheritDoc
     */
    public function serialize(mixed $value): mixed
    {
        return $value;
    }

    /**
     * @inheritDoc
     */
    public function capsGreediness(): bool
    {
        return false; // EndsWith constraint doesn't cap greediness
    }

    private function unescapeString(string $value): string
    {
        // Remove surrounding quotes if present
        if (str_starts_with($value, '"') && str_ends_with($value, '"')) {
            $value = substr($value, 1, -1);
        }

        // Unescape common sequences
        return str_replace(['\"', '\\\\'], ['"', '\\'], $value);
    }
}
