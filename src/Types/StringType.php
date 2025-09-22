<?php declare(strict_types=1);

namespace TypedPatternEngine\Types;

use TypedPatternEngine\Types\Constrains\DefaultConstraint;
use TypedPatternEngine\Types\Constrains\StringConstraints\ContainsConstraint;
use TypedPatternEngine\Types\Constrains\StringConstraints\EndsWithConstraint;
use TypedPatternEngine\Types\Constrains\StringConstraints\MaxLengthConstraint;
use TypedPatternEngine\Types\Constrains\StringConstraints\MinLengthConstraint;
use TypedPatternEngine\Types\Constrains\StringConstraints\StartsWithConstraint;
use TypedPatternEngine\Exception\PatternRuntimeException;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

final class StringType extends Type
{
    protected const DEFAULT_NAME = 'str';
    protected const TYPE_NAMES_ALIASES = ['string'];
    private const REGEX_LOOK_AHEAD = '/\[(\^[^]]*)]/';
    private const REGEX_NEGATIVE_LOOK_AHEAD = '/(\[[^]]+])(\+)/';

    protected string $pattern = '[^\/]+';
    protected array $characterClasses = ['a-zA-Z0-9', '_', '-', '.'];

    public function getSupportedConstraintClasses(): array
    {
        return [
            MinLengthConstraint::class,
            MaxLengthConstraint::class,
            StartsWithConstraint::class,
            EndsWithConstraint::class,
            ContainsConstraint::class,
            DefaultConstraint::class
        ];
    }

    public function parseValue(mixed $value): mixed
    {
        // Validate raw input before applying constraints
        if (!is_scalar($value) && $value !== null) {
            throw new PatternEngineInvalidArgumentException('Value must be scalar for str type, got: \'' . gettype($value).'\'');
        }

        $value = parent::parseValue($value);
        if (empty($value) && $this->getConstraint('default') === null) {
            throw new PatternEngineInvalidArgumentException('Value must be scalar for str type, got: empty, with no Default');
        }

        return (string)$value;
    }

    /**
     * @throws PatternRuntimeException
     */
    public function serialize(mixed $value): string
    {
        if (!is_string($value)) {
            throw new PatternRuntimeException(
                'Expected string value, got ' . gettype($value),
                '',
                '',
                'type_validation',
                $value
            );
        }
        return parent::serialize($value);
    }

    public function applyBoundary(string $pattern, ?string $boundary): string
    {
        if ($boundary === null) {
            return $pattern;
        }

        $escapedBoundary = preg_quote($boundary, '/');

        // String type knows its pattern is [^/]+ and how to modify it
        if (strlen($boundary) === 1) {
            // For single char: modify character class
            if (preg_match(self::REGEX_LOOK_AHEAD, $pattern, $matches)) {
                $charClass = $matches[1];
                if (!str_contains($charClass, $escapedBoundary)) {
                    $newCharClass = $charClass . $escapedBoundary;
                    return str_replace('[' . $charClass . ']', '[' . $newCharClass . ']', $pattern);
                }
            }
        } else {
            // For multi-char: use negative lookahead
            $lookahead = '(?!' . $escapedBoundary . ')';
            return preg_replace(self::REGEX_NEGATIVE_LOOK_AHEAD, '(?:' . $lookahead . '$1)$2', $pattern);
        }

        return $pattern;
    }

    /**
     * @inheritDoc
     */
    public function isGreedy(): bool
    {
        // v1.0: String type is always greedy, constraints don't affect greediness
        return true;
    }

    /**
     * @return mixed
     */
    public function isDefaultValue(mixed $value): bool
    {
        $default = $this->getConstraint('default');
        return $default && $value !== null && ((string)($default->getValue() ?? null)) === ((string)$value);
    }
}
