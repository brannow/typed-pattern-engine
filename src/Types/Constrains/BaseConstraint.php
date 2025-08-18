<?php declare(strict_types=1);

namespace TypedPatternEngine\Types\Constrains;

use TypedPatternEngine\Types\Constrains\Interfaces\TypeConstraint;

abstract class BaseConstraint implements TypeConstraint
{
    public function __construct(
        protected readonly mixed $value
    )
    {}

    /**
     * @return mixed
     */
    public function getValue(): mixed
    {
        return $this->value;
    }
}
