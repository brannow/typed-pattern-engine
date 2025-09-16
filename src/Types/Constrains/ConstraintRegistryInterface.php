<?php declare(strict_types=1);

namespace TypedPatternEngine\Types\Constrains;

use TypedPatternEngine\Types\ConstraintAwareInterface;

interface ConstraintRegistryInterface
{
    public function generateConstraintsForType(array $constraints, ConstraintAwareInterface $type): array;
}
