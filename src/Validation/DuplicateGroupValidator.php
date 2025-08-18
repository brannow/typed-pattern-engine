<?php declare(strict_types=1);

namespace TypedPatternEngine\Validation;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;
use TypedPatternEngine\Nodes\Interfaces\NodeTreeInterface;
use TypedPatternEngine\Nodes\Interfaces\TypeNodeInterface;
use TypedPatternEngine\Exception\PatternValidationException;

final class DuplicateGroupValidator implements ValidatorInterface
{
    /**
     * @throws PatternValidationException
     */
    public function validate(AstNodeInterface $astNode): void
    {
        $groupNames = [];
        $this->collectGroupNames($astNode, $groupNames);
        
        $duplicates = array_filter(array_count_values($groupNames), fn($count) => $count > 1);
        
        if (!empty($duplicates)) {
            $duplicateNames = array_keys($duplicates);
            throw new PatternValidationException(
                'Duplicate group names found: ' . implode(', ', $duplicateNames),
                '',
                'no-duplicate-group-names',
                $duplicateNames
            );
        }
    }
    
    /**
     * @param array<string> $groupNames
     */
    private function collectGroupNames(AstNodeInterface $node, array &$groupNames): void
    {
        if ($node instanceof TypeNodeInterface) {
            $groupNames[] = $node->getName();
        }
        
        if ($node instanceof NodeTreeInterface) {
            foreach ($node->getChildren() as $child) {
                $this->collectGroupNames($child, $groupNames);
            }
        }
    }
}
