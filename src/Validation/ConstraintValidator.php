<?php declare(strict_types=1);

namespace TypedPatternEngine\Validation;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NestedNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Exception\PatternValidationException;

final class ConstraintValidator implements ValidatorInterface
{
    /**
     * @throws PatternValidationException
     */
    public function validate(AstNodeInterface $astNode): void
    {
        $this->validateConstraints($astNode);
    }

    /**
     * @throws PatternValidationException
     */
    private function validateConstraints(AstNodeInterface $node): void
    {
        if ($node instanceof TypeNodeInterface) {
            foreach ($node->getType()->getConstraints() as $key => $constraint) {
                $value = $constraint->getValue();
                if ($value === '' || $value === null) {
                    throw new PatternValidationException(
                        "Empty constraint value for '$key' in group '".$node->getName()."'",
                        '',
                        'no-empty-constraint-values',
                        [$node->getName(), $key]
                    );
                }
            }
        }
        
        if ($node instanceof NestedNodeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->validateConstraints($child);
            }
        }
    }
}
