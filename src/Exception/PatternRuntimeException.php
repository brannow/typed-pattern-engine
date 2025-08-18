<?php declare(strict_types=1);

namespace TypedPatternEngine\Exception;

use RuntimeException;
use Throwable;

class PatternRuntimeException extends RuntimeException
{
    private string $pattern;
    private string $input;
    private string $constraint;
    private mixed $value;

    public function __construct(string $message, string $pattern = '', string $input = '', string $constraint = '', mixed $value = null, int $code = 0, ?Throwable $previous = null)
    {
        $this->pattern = $pattern;
        $this->input = $input;
        $this->constraint = $constraint;
        $this->value = $value;

        $formattedMessage = $this->formatMessage($message, $pattern, $input, $constraint, $value);
        parent::__construct($formattedMessage, $code, $previous);
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getInput(): string
    {
        return $this->input;
    }

    public function getConstraint(): string
    {
        return $this->constraint;
    }

    public function getValue(): mixed
    {
        return $this->value;
    }

    private function formatMessage(string $message, string $pattern, string $input, string $constraint, mixed $value): string
    {
        $result = $message;

        if (!empty($constraint)) {
            $result .= " (Constraint: $constraint)";
        }

        if ($value !== null) {
            $valueStr = is_scalar($value) ? (string)$value : gettype($value);
            $result .= " - Value: '$valueStr'";
        }

        if (!empty($input)) {
            $result .= " - Input: '$input'";
        }

        if (!empty($pattern)) {
            $result .= " - Pattern: '$pattern'";
        }

        return $result;
    }
}
