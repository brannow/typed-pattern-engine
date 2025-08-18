<?php declare(strict_types=1);

namespace TypedPatternEngine\Types;

use TypedPatternEngine\Types\Constrains\ConstraintRegistry;
use TypedPatternEngine\Types\Constrains\Interfaces\BoundingConstraintInterface;
use TypedPatternEngine\Types\Constrains\Interfaces\ModifyPatternAwareInterface;
use TypedPatternEngine\Types\Constrains\Interfaces\RefinementConstraintInterface;
use TypedPatternEngine\Types\Constrains\Interfaces\TypeConstraint;
use TypedPatternEngine\Exception\PatternRuntimeException;
use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

abstract class Type implements TypeInterface
{
    // register default type name used in pattern group
    protected const DEFAULT_NAME = '';
    protected const TYPE_NAMES_ALIASES = [];
    /**
     * @var array<string, TypeConstraint>
     */
    private readonly array $constraints;
    protected string $pattern = '';
    /**
     * @var array<string>
     */
    protected array $characterClasses = [];

    /**
     * @param ConstraintRegistry $constraintRegistry
     * @param array<string, mixed> $arguments constraint arguments
     * @throws PatternRuntimeException
     */
    public function __construct(ConstraintRegistry $constraintRegistry, array $arguments)
    {
        $this->constraints = $constraintRegistry->generateConstraintsForType($arguments, $this);
    }

    /**
     * Get the regex pattern for this type.
     *
     * @return string The regex pattern
     */
    public function getPattern(): string
    {
        return $this->pattern;
    }

    /**
     * Get the name of this type. (include all aliases)
     *
     * @return string[] The type names (e.g., 'int', 'str')
     * @throws TypeSystemException
     */
    public static function getNames(): array
    {
        return [static::getDefaultName(), ...static::TYPE_NAMES_ALIASES];
    }

    /**
     * @return string
     * @throws TypeSystemException
     */
    public static function getDefaultName(): string
    {
        return !empty(static::DEFAULT_NAME) ? static::DEFAULT_NAME : throw new TypeSystemException('No Name in type\''. static::class .'\' defined', '', static::class, 'getDefaultName');
    }

    /**
     * @return string
     * @throws TypeSystemException
     */
    public function getName(): string
    {
        return static::getDefaultName();
    }

    /**
     * convert constraintObjects back to ['constraintName' => 'value']
     * @return array<string, mixed>
     */
    public function getConstraintArguments(): array
    {
        $list = [];
        foreach ($this->constraints as $name => $constraint) {
            $list[$name] = $constraint->getValue();
        }

        return $list;
    }

    /**
     * @param mixed $value
     * @return mixed
     */
    public function parseValue(mixed $value): mixed
    {
        foreach ($this->constraints as $constraint) {
            $value = $constraint->parseValue($value) ?? $value;
        }

        return $value;
    }

    /**
     * Convert a string match to the appropriate PHP type.
     *
     * @param mixed $value The matched string
     * @return string The converted value
     * @throws PatternEngineInvalidArgumentException When validation fails
     */
    public function serialize(mixed $value): string
    {
        foreach ($this->constraints as $constraint) {
            $value = $constraint->serialize($value) ?? $value;
        }

        return (string)$value;
    }

    /**
     * @return array<string, TypeConstraint>
     */
    public function getConstraints(): array
    {
        return $this->constraints;
    }

    /**
     * @param string $name
     * @return TypeConstraint|null
     */
    public function getConstraint(string $name): ?TypeConstraint
    {
        return $this->constraints[$name] ?? null;
    }

    /**
     * @inheritDoc
     * @return array<string>
     */
    public function getCharacterClasses(): array
    {
        return $this->characterClasses;
    }

    /**
     * @inheritDoc
     */
    public function getConstrainedPattern(): string
    {
        $pattern = $this->getPattern();
        $constraints = $this->constraints;
        // correct order first the bounding
        foreach ($constraints as $name => $constraint) {
            if ($constraint instanceof BoundingConstraintInterface) {
                $pattern = $constraint->modifyPattern($pattern);
                unset($constraints[$name]);
            }
        }

        // later the refinement
        foreach ($constraints as $name => $constraint) {
            if ($constraint instanceof RefinementConstraintInterface) {
                $pattern = $constraint->modifyPattern($pattern);
                unset($constraints[$name]);
            }
        }

        // any leftovers
        foreach ($constraints as $constraint) {
            if ($constraint instanceof ModifyPatternAwareInterface) {
                $pattern = $constraint->modifyPattern($pattern);
            }
        }
        
        return $pattern;
    }

    abstract public function applyBoundary(string $pattern, ?string $boundary): string;

    /**
     * @inheritDoc
     */
    abstract public function isGreedy(): bool;
}
