<?php declare(strict_types=1);

namespace TypedPatternEngine\Exception;

use LogicException;
use Throwable;

class PatternValidationException extends LogicException
{
    private string $pattern;
    private string $validationRule;
    /**
     * @var array<mixed>
     */
    private array $violatingElements;

    /**
     * @param array<mixed> $violatingElements
     */
    public function __construct(string $message, string $pattern = '', string $validationRule = '', array $violatingElements = [], int $code = 0, ?Throwable $previous = null)
    {
        $this->pattern = $pattern;
        $this->validationRule = $validationRule;
        $this->violatingElements = $violatingElements;

        $formattedMessage = $this->formatMessage($message, $pattern, $validationRule, $violatingElements);
        parent::__construct($formattedMessage, $code, $previous);
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getValidationRule(): string
    {
        return $this->validationRule;
    }

    /**
     * @return array<mixed>
     */
    public function getViolatingElements(): array
    {
        return $this->violatingElements;
    }

    /**
     * @param array<mixed> $violatingElements
     */
    private function formatMessage(string $message, string $pattern, string $validationRule, array $violatingElements): string
    {
        $result = $message;

        if (!empty($validationRule)) {
            $result .= " (Validation rule: $validationRule)";
        }

        if (!empty($violatingElements)) {
            $elements = implode(', ', array_map(fn($el) => "'$el'", $violatingElements));
            $result .= " - Violating elements: $elements";
        }

        if (!empty($pattern)) {
            $result .= " in pattern '$pattern'";
        }

        return $result;
    }
}
