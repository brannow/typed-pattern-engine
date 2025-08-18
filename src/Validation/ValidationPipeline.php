<?php declare(strict_types=1);

namespace TypedPatternEngine\Validation;

use TypedPatternEngine\Nodes\Interfaces\AstNodeInterface;

final class ValidationPipeline implements ValidatorInterface
{
    /** @var ValidatorInterface[] */
    private array $validators = [];

    public function addValidator(ValidatorInterface $validator): void
    {
        $this->validators[] = $validator;
    }

    public function validate(AstNodeInterface $astNode): void
    {
        foreach ($this->validators as $validator) {
            $validator->validate($astNode);
        }
    }
}
