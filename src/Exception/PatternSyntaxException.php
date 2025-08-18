<?php declare(strict_types=1);

namespace TypedPatternEngine\Exception;

use ParseError;
use Throwable;

class PatternSyntaxException extends ParseError
{
    private string $pattern;
    private int $position;

    public function __construct(string $message, string $pattern = '', int $position = -1, int $code = 0, ?Throwable $previous = null)
    {
        $this->pattern = $pattern;
        $this->position = $position;

        $formattedMessage = $this->formatMessage($message, $pattern, $position);
        parent::__construct($formattedMessage, $code, $previous);
    }

    public function getPattern(): string
    {
        return $this->pattern;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    private function formatMessage(string $message, string $pattern, int $position): string
    {
        if (empty($pattern)) {
            return $message;
        }

        $result = $message;
        
        if ($position >= 0 && $position < strlen($pattern)) {
            $result .= sprintf(
                " at position %d in pattern '%s'",
                $position,
                $pattern
            );
            
            // Add visual pointer to error location
            $pointer = str_repeat(' ', $position) . '^';
            $result .= "\n" . $pattern . "\n" . $pointer;
        } else {
            $result .= sprintf(" in pattern '%s'", $pattern);
        }

        return $result;
    }
}
