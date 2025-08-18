<?php declare(strict_types=1);

namespace TypedPatternEngine\Exception;

use RuntimeException;
use Throwable;

class PatternCompilationException extends RuntimeException
{
    private string $pattern;
    private string $context;

    public function __construct(string $message, string $pattern = '', string $context = '', int $code = 0, ?Throwable $previous = null)
    {
        $this->pattern = $pattern;
        $this->context = $context;

        $formattedMessage = $this->formatMessage($message, $pattern, $context);
        parent::__construct($formattedMessage, $code, $previous);
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getContext(): string
    {
        return $this->context;
    }

    private function formatMessage(string $message, string $pattern, string $context): string
    {
        $result = $message;

        if (!empty($context)) {
            $result .= " (Context: $context)";
        }

        if (!empty($pattern)) {
            $result .= " in pattern '$pattern'";
        }

        return $result;
    }
}
