<?php declare(strict_types=1);

namespace TypedPatternEngine\Exception;

use OutOfBoundsException;
use Throwable;

class TypeSystemException extends OutOfBoundsException
{
    private string $typeName;
    private string $typeClass;
    private string $operation;

    public function __construct(string $message, string $typeName = '', string $typeClass = '', string $operation = '', int $code = 0, ?Throwable $previous = null)
    {
        $this->typeName = $typeName;
        $this->typeClass = $typeClass;
        $this->operation = $operation;

        $formattedMessage = $this->formatMessage($message, $typeName, $typeClass, $operation);
        parent::__construct($formattedMessage, $code, $previous);
    }

    public function getTypeName(): string
    {
        return $this->typeName;
    }

    public function getTypeClass(): string
    {
        return $this->typeClass;
    }

    public function getOperation(): string
    {
        return $this->operation;
    }

    private function formatMessage(string $message, string $typeName, string $typeClass, string $operation): string
    {
        $result = $message;

        if (!empty($operation)) {
            $result .= " (Operation: $operation)";
        }

        if (!empty($typeName)) {
            $result .= " - Type name: '$typeName'";
        }

        if (!empty($typeClass)) {
            $result .= " - Type class: '$typeClass'";
        }

        return $result;
    }
}
