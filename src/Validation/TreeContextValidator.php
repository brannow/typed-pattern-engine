<?php declare(strict_types=1);

namespace TypedPatternEngine\Validation;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;

/**
 * Validates tree context rules after all parent-child relationships are established.
 * Delegates to each node's validateTreeContext() method.
 */
final class TreeContextValidator implements ValidatorInterface
{
    public function validate(AstNodeInterface $astNode): void
    {
        $this->validateNode($astNode);
    }

    private function validateNode(AstNodeInterface $node): void
    {
        // Validate this node's context
        $node->validateTreeContext();
        
        // Recursively validate children
        if ($node instanceof NodeTreeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->validateNode($child);
            }
        }
    }
}
