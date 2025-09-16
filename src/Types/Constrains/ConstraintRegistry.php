<?php declare(strict_types=1);

namespace TypedPatternEngine\Types\Constrains;

use TypedPatternEngine\Types\Constrains\Interfaces\TypeConstraint;
use TypedPatternEngine\Types\ConstraintAwareInterface;
use TypedPatternEngine\Exception\PatternRuntimeException;

class ConstraintRegistry implements ConstraintRegistryInterface
{

    /**
     * @param array<string, mixed> $constraints
     * @param ConstraintAwareInterface $type
     * @return array<string, TypeConstraint>
     * @throws PatternRuntimeException
     */
    public function generateConstraintsForType(array $constraints, ConstraintAwareInterface $type): array
    {
        $constraintsObj = [];
        $constraintMap = $this->getConstraintMap($type);
        foreach ($constraints as $name => $value) {
            /** @var class-string<TypeConstraint> $constraintClass */
            $constraintClass = $constraintMap[$name] ?? throw new PatternRuntimeException('Constraint with name' . $name . ' not found for ' . $type::class . ', support only ('. implode(',', array_keys($constraintMap)) .')', '', '', $name, $name);
            $constraintsObj[$name] = new $constraintClass($value);
        }

        return $constraintsObj;
    }

    /**
     * @return array<string, class-string<TypeConstraint>>
     * @throws PatternRuntimeException
     */
    private function getConstraintMap(ConstraintAwareInterface $type): array
    {
        $map = [];
        foreach ($type->getSupportedConstraintClasses() as $constraintClass) {
            if (is_a($constraintClass, TypeConstraint::class, true)) {
                if ($constraintClass::NAME === TypeConstraint::NAME || empty($constraintClass::NAME)) {
                    throw new PatternRuntimeException('Constraint ' . $constraintClass . ' from type ' . $type::class . ' has invalid name: ' . $constraintClass::NAME, '', '', $constraintClass::NAME, $constraintClass::NAME);
                }

                $map[$constraintClass::NAME] = $constraintClass;
            } else {
                throw new PatternRuntimeException('Constraint ' . $constraintClass . ' from type ' . $type::class . 'must implement ' . TypeConstraint::class, '', '', 'constraint-validation', $constraintClass);
            }
        }

        return $map;
    }
}
