<?php declare(strict_types=1);

namespace TypedPatternEngine\Types;

use TypedPatternEngine\Types\Constrains\ConstraintRegistry;
use TypedPatternEngine\Exception\TypeSystemException;

/**
 * Type Registry - regular class with dependency injection
 */
final class TypeRegistry
{
    /**
     * @var array<string, string> lookup Map of [TypeNames => TypeClass]
     */
    private array $types = [];
    private readonly ConstraintRegistry $constraintRegistry;

    /**
     * @throws TypeSystemException
     */
    public function __construct(bool $registerDefaults = true)
    {
        if ($registerDefaults) {
            $this->registerDefaults();
        }
        $this->constraintRegistry = new ConstraintRegistry();
    }

    /**
     * @param string $name
     * @param array<string, mixed> $arguments [ConstraintName => value]
     * @return TypeInterface
     * @throws TypeSystemException
     */
    public function getTypeObject(string $name, array $arguments): TypeInterface
    {
        return match(false) {
            $typeClass = $this->types[$name] ?? false => throw new TypeSystemException('Type not found', $name, '', 'getTypeObject'),
            class_exists($typeClass) => throw new TypeSystemException('Type \''. $typeClass .'\' is not a CLASS or not exists', $name, $typeClass, 'getTypeObject'),
            is_a($typeClass, TypeInterface::class, true) => throw new TypeSystemException('Type \''. $typeClass .'\' must implement ' . TypeInterface::class, $name, $typeClass, 'getTypeObject'),
            default => new $typeClass($this->constraintRegistry, $arguments)
        };
    }

    /**
     * @param string $class
     * @param string[] $names
     * @return void
     * @throws TypeSystemException
     */
    public function registerType(string $class, array $names): void
    {
        foreach ($names as $name) {
            if (!is_a($class, TypeInterface::class, true)) {
                throw new TypeSystemException('TypeClass '. $class .' must be implement '. TypeInterface::class, $name, $class, 'registerType');
            }
            // overwrite types if needed
            $this->types[$name] = $class;
        }
    }

    /**
     * @throws TypeSystemException
     */
    private function registerDefaults(): void
    {
        $this->registerType(IntType::class, IntType::getNames());
        $this->registerType(StringType::class, StringType::getNames());
        // Add more default types here
    }
}
