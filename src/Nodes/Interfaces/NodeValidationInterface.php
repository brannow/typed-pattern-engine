<?php declare(strict_types=1);

namespace TypedPatternEngine\Nodes\Interfaces;

interface NodeValidationInterface
{
    public function validateEntireTree(): void;

    public function validateTreeContext(): void;
}
