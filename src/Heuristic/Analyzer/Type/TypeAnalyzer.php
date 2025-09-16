<?php declare(strict_types=1);

namespace TypedPatternEngine\Heuristic\Analyzer\Type;

use TypedPatternEngine\Heuristic\Analyzer\AnalyzerResult;
use TypedPatternEngine\Types\TypeInterface;
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

class TypeAnalyzer
{
    // Common character sets for batch testing
    private const CHAR_SETS = [
        'digits' => '0123456789',
        'lower' => 'abcdefghijklmnopqrstuvwxyz',
        'upper' => 'ABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?/\\\'"`~',
        'whitespace' => " \t\n\r",
    ];

    // Cache for analyzed types (since types are immutable)
    /**
     * @var array<int, TypeAnalyzerResult>
     */
    private static array $cache = [];

    /**
     * Analyze a type by probing it with test values
     * Uses blackbox testing - no knowledge about specific types needed!
     */
    public static function analyzeType(TypeInterface $type): TypeAnalyzerResult
    {
        // Check cache first (types are immutable)
        $cacheKey = spl_object_id($type);
        if (isset(self::$cache[$cacheKey])) {
            return self::$cache[$cacheKey];
        }

        // Probe for allowed characters (optimized)
        $allowedChars = self::probeAllowedCharactersOptimized($type);

        // Simple heuristic: we don't probe actual length constraints
        // The NodeAnalyzer will handle min/max based on optionality
        $minLen = 0;  // Conservative default
        $maxLen = null; // No limit

        // Check if empty string is valid
        $canBeEmpty = self::probeCanBeEmpty($type);

        $result = new TypeAnalyzerResult(
            minLen: $minLen,
            maxLen: $maxLen,
            allowedChars: $allowedChars,
            canBeEmpty: $canBeEmpty
        );

        // Cache the result
        self::$cache[$cacheKey] = $result;

        return $result;
    }

    /**
     * Optimized character probing - test character sets first, then individuals
     *
     * @param TypeInterface $type
     * @return array<int, true>
     */
    private static function probeAllowedCharactersOptimized(TypeInterface $type): array
    {
        $allowedChars = [];
        $testedChars = [];

        // First, test if this might be a numeric type with constraints
        // by testing some multi-digit values
        $numericTests = ['123', '456', '999', '1000'];
        $acceptsNumbers = false;
        foreach ($numericTests as $test) {
            try {
                $type->parseValue($test);
                $acceptsNumbers = true;
                break;
            } catch (PatternEngineInvalidArgumentException) {
                // Not accepted
            }
        }

        if ($acceptsNumbers) {
            // For numeric types, assume digits are allowed even if
            // individual digits might be rejected due to constraints
            for ($i = ord('0'); $i <= ord('9'); $i++) {
                $allowedChars[$i] = true;
                $testedChars[$i] = true;
            }
            // Also check for minus sign with multi-char negative numbers
            try {
                $type->parseValue('-100');
                $allowedChars[ord('-')] = true;
            } catch (PatternEngineInvalidArgumentException) {
                // Minus not allowed
            }
        }

        // First, test common character sets to batch-identify allowed ranges
        foreach (self::CHAR_SETS as $setName => $chars) {
            // Skip digits if we already handled them as numeric
            if ($setName === 'digits' && $acceptsNumbers) {
                continue;
            }
            // Try the whole set at once
            try {
                $type->parseValue($chars);
                // If the whole set works, all chars are likely allowed
                for ($i = 0; $i < strlen($chars); $i++) {
                    $char = $chars[$i];
                    $allowedChars[ord($char)] = true;
                    $testedChars[ord($char)] = true;
                }
            } catch (PatternEngineInvalidArgumentException) {
                // Set as a whole failed, test individual chars from this set
                for ($i = 0; $i < strlen($chars); $i++) {
                    $char = $chars[$i];
                    $ord = ord($char);
                    if (!isset($testedChars[$ord]) && !isset($allowedChars[$ord])) {
                        try {
                            $type->parseValue($char);
                            $allowedChars[$ord] = true;
                        } catch (PatternEngineInvalidArgumentException) {
                            // Character not allowed
                        }
                        $testedChars[$ord] = true;
                    }
                }
            }
        }

        // Test remaining printable ASCII characters not in sets
        for ($i = 32; $i < 127; $i++) {
            if (!isset($testedChars[$i]) && !isset($allowedChars[$i])) {
                $char = chr($i);
                try {
                    $type->parseValue($char);
                    $allowedChars[$i] = true;
                } catch (PatternEngineInvalidArgumentException) {
                    // Character not allowed
                }
            }
        }

        // Special cases for numeric types
        self::probeNumericPatterns($type, $allowedChars);

        return $allowedChars;
    }

    /**
     * Probe for numeric patterns (negative numbers, decimals, etc.)
     *
     * @param TypeInterface $type
     * @param array<int, true> $allowedChars
     * @return void
     */
    private static function probeNumericPatterns(TypeInterface $type, array &$allowedChars): void
    {
        // Test negative numbers
        $negativeTests = ['-1', '-123', '-999'];
        foreach ($negativeTests as $test) {
            try {
                $type->parseValue($test);
                $allowedChars[ord('-')] = true;
                break; // One success is enough
            } catch (PatternEngineInvalidArgumentException) {
                // Continue testing
            }
        }

        // Test decimal numbers
        $decimalTests = ['1.0', '3.14', '0.5'];
        foreach ($decimalTests as $test) {
            try {
                $type->parseValue($test);
                $allowedChars[ord('.')] = true;
                break; // One success is enough
            } catch (PatternEngineInvalidArgumentException) {
                // Continue testing
            }
        }
    }


    /**
     * Check if empty string is valid
     */
    private static function probeCanBeEmpty(TypeInterface $type): bool
    {
        try {
            $type->parseValue('');
            return true;
        } catch (PatternEngineInvalidArgumentException) {
            return false;
        }
    }

    /**
     * Clear the type analysis cache (useful for testing or memory management)
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}
