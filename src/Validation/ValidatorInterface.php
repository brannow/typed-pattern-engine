<?php declare(strict_types=1);

namespace TypedPatternEngine\Validation;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;

interface ValidatorInterface
{
    public function validate(AstNodeInterface $astNode): void;
}
