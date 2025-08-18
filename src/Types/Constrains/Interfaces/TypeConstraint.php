<?php declare(strict_types=1);

namespace TypedPatternEngine\Types\Constrains\Interfaces;

use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

interface TypeConstraint
{
    /**
     * constant name used in pattern
     */
    public const NAME = '__undefined__';

    /**
     * @return mixed
     */
    public function getValue(): mixed;

    /**
     * Process the value - validate, transform, or apply defaults.
     *
     * @param mixed $value The value to process
     * @return mixed The processed value (maybe same or transformed)
     * @throws PatternEngineInvalidArgumentException When validation fails
     */
    public function parseValue(mixed $value): mixed;

    /**
     * Modify the builder in-place or return null to skip
     * @param mixed $value
     * @return mixed
     */
    public function serialize(mixed $value): mixed;

    /**
     * Whether this constraint caps greediness (makes patterns non-greedy).
     * Used to determine if a type with this constraint should be considered greedy.
     * 
     * @return bool True if this constraint caps/limits greediness
     */
    public function capsGreediness(): bool;
}
