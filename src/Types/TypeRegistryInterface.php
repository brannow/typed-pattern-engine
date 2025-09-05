<?php declare(strict_types=1);

namespace TypedPatternEngine\Types;

interface TypeRegistryInterface
{
    public function getTypeObject(string $name, array $arguments): TypeInterface;
    public function registerType(string $class, array $names): void;
}
