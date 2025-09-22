<?php declare(strict_types=1);

namespace TypedPatternEngine\Types;

use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

interface TypeInterface extends ConstraintAwareInterface
{
    /**
     * Get the name of this type. (include all aliases)
     *
     * Get the type name (e.g., 'int', 'slug', 'string')
     * @return string[]
     */
    public static function getNames(): array;

    /**
     * @return string return the first name in the name list
     * @throws TypeSystemException
     */
    public static function getDefaultName(): string;

    /**
     * @return string return the first name in the name list
     * @throws TypeSystemException
     */
    public function getName(): string;

    /**
     * convert constraintObjects back to ['constraintName' => 'value']
     * @return array<string, mixed>
     */
    public function getConstraintArguments(): array;

    /**
     * Get the regex pattern this type matches.
     * This is used by AST nodes to build the final regex.
     */
    public function getPattern(): string;

    /**
     * Parse a string value into the appropriate PHP type.
     *
     * @param mixed $value The raw matched string
     * @return mixed The parsed value
     * @throws PatternEngineInvalidArgumentException When value doesn't meet constraints
     */
    public function parseValue(mixed $value): mixed;

    /**
     * @return mixed
     */
    public function isDefaultValue(mixed $value): bool;

    /**
     * Serialize a PHP value back to string for URL generation.
     *
     * @param mixed $value The PHP value
     * @return string The serialized string
     * @throws PatternEngineInvalidArgumentException When value doesn't meet constraints
     */
    public function serialize(mixed $value): string;

    /**
     * Get character classes used by this type (for heuristic building).
     * @return array<string>
     */
    public function getCharacterClasses(): array;

    /**
     * Check if this type with given constraints is greedy.
     * Greedy types consume as much as possible unless capped by constraints.
     */
    public function isGreedy(): bool;

    /**
     * Apply boundary to pattern for greedy types
     * The type knows HOW to apply boundaries for its specific pattern
     *
     * @internal
     * @param string $pattern The base pattern
     * @param string|null $boundary The boundary string to apply
     * @return string Modified pattern with boundary applied
     */
    public function applyBoundary(string $pattern, ?string $boundary): string;
}
