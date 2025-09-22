<?php declare(strict_types=1);

namespace TypedPatternEngine\Tests\Unit;

use TypedPatternEngine\Heuristic\Analyzer\AnalyzerResult;
use TypedPatternEngine\TypedPatternEngine;
use TypedPatternEngine\Exception\PatternValidationException;
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\TypeSystemException;
use TypedPatternEngine\Exception\PatternRuntimeException;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test error scenarios, edge cases, and constraint validation failures
 * Tests the system's robustness and error handling
 */
class PatternErrorScenariosTest extends TestCase
{
    private TypedPatternEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TypedPatternEngine();
    }

    #[DataProvider('constraintViolationProvider')]
    public function testConstraintViolationHandling(string $pattern, string $input, bool $shouldHaveErrors): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldHaveErrors) {
            $this->assertNotNull($result, "Should return a result even with constraint violations");
            $this->assertTrue($result->isFailed(), "Result should indicate failure due to constraint violations");
            $this->assertNotEmpty($result->getErrors(), "Should have error details");
        } else {
            if ($result !== null) {
                $this->assertFalse($result->isFailed(), "Result should not indicate failure");
                $this->assertEmpty($result->getErrors(), "Should not have errors");
            }
        }
    }

    #[DataProvider('invalidPatternProvider')]
    public function testInvalidPatternHandling(string $pattern, string $expectedExceptionClass): void
    {
        $this->expectException($expectedExceptionClass);
        
        $compiler = $this->engine->getPatternCompiler();
        $compiler->compile($pattern);
    }

    #[DataProvider('emptyAndNullInputProvider')]
    public function testEmptyAndNullInputHandling(string $pattern, string $input, bool $shouldMatch): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Empty/null input should match when expected");
        } else {
            $this->assertNull($result, "Empty/null input should not match when not expected");
        }
    }

    #[DataProvider('extremeValueProvider')]
    public function testExtremeValues(string $pattern, string $input, bool $shouldMatch): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Extreme value should match when valid");
        } else {
            $this->assertNull($result, "Extreme value should not match when invalid");
        }
    }

    #[DataProvider('unicodeAndSpecialCharProvider')]
    public function testUnicodeAndSpecialCharacterHandling(string $pattern, string $input, bool $shouldMatch, array $expectedValues = []): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        $result = $compiled->match($input);
        
        if ($shouldMatch) {
            $this->assertNotNull($result, "Unicode/special char input should match when expected");
            
            foreach ($expectedValues as $key => $expectedValue) {
                $this->assertSame($expectedValue, $result->get($key));
            }
        } else {
            $this->assertNull($result, "Unicode/special char input should not match when not expected");
        }
    }

    #[DataProvider('generationEdgeCaseProvider')]
    public function testGenerationEdgeCases(string $pattern, array $values, ?string $expectedOutput, bool $shouldThrow = false): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);
        
        if ($shouldThrow) {
            $this->expectException(\Exception::class);
            $compiled->generate($values);
        } else {
            $generated = $compiled->generate($values);
            $this->assertSame($expectedOutput, $generated);
        }
    }

    #[DataProvider('heuristicEdgeCaseProvider')]
    public function testHeuristicEdgeCases(array $patterns, string $input, bool $expectedSupport): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiledPatterns = [];
        
        foreach ($patterns as $pattern) {
            $compiledPatterns[] = $compiler->compile($pattern);
        }
        
        $heuristic = (new \TypedPatternEngine\Heuristic\HeuristicCompiler())->compile($compiledPatterns);
        
        $this->assertSame($expectedSupport, $heuristic->support($input));
    }

    #[DataProvider('serializationEdgeCaseProvider')]
    public function testSerializationEdgeCases(string $pattern): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $original = $compiler->compile($pattern);
        
        // Test multiple dehydration/hydration cycles
        $dehydrated1 = $compiler->dehydrate($original);
        $rehydrated1 = $compiler->hydrate($dehydrated1);
        
        $dehydrated2 = $compiler->dehydrate($rehydrated1);
        $rehydrated2 = $compiler->hydrate($dehydrated2);
        
        // Should be identical after multiple cycles
        $this->assertEquals($dehydrated1, $dehydrated2, "Multiple dehydration cycles should produce identical results");
        
        // Test with various inputs
        $testInputs = ['TEST123', 'INVALID', '', 'EDGE_CASE_999'];
        
        foreach ($testInputs as $input) {
            $originalResult = $original->match($input);
            $rehydrated1Result = $rehydrated1->match($input);
            $rehydrated2Result = $rehydrated2->match($input);
            
            if ($originalResult === null) {
                $this->assertNull($rehydrated1Result);
                $this->assertNull($rehydrated2Result);
            } else {
                $this->assertNotNull($rehydrated1Result);
                $this->assertNotNull($rehydrated2Result);
                $this->assertEquals($originalResult->toArray(), $rehydrated1Result->toArray());
                $this->assertEquals($originalResult->toArray(), $rehydrated2Result->toArray());
            }
        }
    }

    // Data Providers

    public static function constraintViolationProvider(): Generator
    {
        // These should parse correctly but fail constraint validation
        yield 'int-below-min' => ['PAGE{uid:int(min=100)}', 'PAGE50', true];
        yield 'int-above-max' => ['PAGE{uid:int(max=50)}', 'PAGE100', true];
        yield 'str-too-short' => ['USER{name:str(minLen=5)}', 'USERjoe', true];
        yield 'str-too-long' => ['USER{name:str(maxLen=3)}', 'USERjohnathan', true];
        
        // These should pass constraints
        yield 'int-valid-min' => ['PAGE{uid:int(min=100)}', 'PAGE150', false];
        yield 'int-valid-max' => ['PAGE{uid:int(max=50)}', 'PAGE25', false];
        yield 'str-valid-length' => ['USER{name:str(minLen=3, maxLen=10)}', 'USERjohn', false];
    }

    public static function invalidPatternProvider(): Generator
    {
        yield 'unclosed-group' => ['{name:int', PatternSyntaxException::class];
        yield 'invalid-type' => ['{name:invalidtype}', TypeSystemException::class];
        yield 'nested-groups' => ['{outer{inner:int}:str}', PatternSyntaxException::class];
        yield 'malformed-constraints' => ['{name:int(min=)}', PatternValidationException::class];
        yield 'duplicate-group-names' => ['{id:int}-{id:str}', PatternValidationException::class];
        
        // Greediness rule violations - should throw parse exceptions
        yield 'forbidden-adjacent-greedy-groups' => ['{a:int}{b:int}', PatternValidationException::class];
        yield 'forbidden-adjacent-greedy-mixed' => ['{id:int}{name:str}', PatternValidationException::class];
        
        // After normalization: {a:int}?{b:int} becomes ({a:int})({b:int}) - should be ALLOWED
        // Removed this test since normalization makes it valid
        
        // Within same SubSequence - still forbidden
        yield 'forbidden-adjacent-within-subsequence' => ['PAGE({a:int}{b:int})', PatternValidationException::class];
        
        // Additional architectural validation errors
        yield 'empty-subsequence-forbidden' => ['PAGE()', PatternSyntaxException::class];
        yield 'nested-empty-subsequence-forbidden' => ['PAGE{id:int}()', PatternSyntaxException::class];
        yield 'empty-subsequence-single' => ['PAGE()', PatternSyntaxException::class];
        yield 'empty-subsequence-with-group' => ['PAGE{id:int}()', PatternSyntaxException::class];
        yield 'empty-subsequence-multiple' => ['PAGE(){id:int}()', PatternSyntaxException::class];
        
        // Default constraint validation - only allowed on optional groups
        yield 'default-on-required-group' => ['{id:int(default=42)}', PatternRuntimeException::class];
        
        // Group name validation - must start with letter
        yield 'group-name-starts-with-number' => ['{1name:int}', PatternSyntaxException::class];
    }

    public static function emptyAndNullInputProvider(): Generator
    {
        yield 'empty-string-no-match' => ['PAGE{uid:int}', '', false];
        yield 'empty-string-optional-all' => ['{uid:int}?', '', false]; // Even optional groups need some content
        yield 'whitespace-only' => ['PAGE{uid:int}', '   ', false];
        yield 'literal-only-pattern-empty' => ['PAGE', '', false];
        yield 'literal-only-pattern-match' => ['PAGE', 'PAGE', true];
    }

    public static function extremeValueProvider(): Generator
    {
        yield 'very-large-int' => ['PAGE{uid:int}', 'PAGE999999999999', true];
        yield 'very-long-string' => ['USER{name:str}', 'USER' . str_repeat('a', 1000), true];
        yield 'single-char-string' => ['USER{name:str}', 'USERa', true];
        yield 'zero-int' => ['PAGE{uid:int}', 'PAGE0', true];
        
        // PHP int limits
        yield 'max-php-int' => ['PAGE{uid:int}', 'PAGE' . PHP_INT_MAX, true];
        yield 'beyond-php-int' => ['PAGE{uid:int}', 'PAGE99999999999999999999999999999', true]; // Should still match regex, fail in type conversion
    }

    public static function unicodeAndSpecialCharProvider(): Generator
    {
        yield 'unicode-in-literal' => ['PÄGE{uid:int}', 'PÄGE123', true, ['uid' => 123]];
        yield 'emoji-in-literal' => ['🏠{id:int}', '🏠42', true, ['id' => 42]];
        yield 'special-regex-chars' => ['test.{id:int}', 'test.123', true, ['id' => 123]];
        yield 'backslash-in-literal' => ['path\\{id:int}', 'path\\456', true, ['id' => 456]];
        yield 'unicode-in-string-group' => ['USER{name:str}', 'USERjöhn', true, ['name' => 'jöhn']];
        
        // Should not match
        yield 'unicode-mismatch' => ['PAGE{uid:int}', 'PÄGE123', false];
    }

    public static function generationEdgeCaseProvider(): Generator
    {
        yield 'missing-required-value' => ['PAGE{uid:int}', [], null, true];
        yield 'extra-unused-values' => ['PAGE{uid:int}', ['uid' => 123, 'extra' => 'ignored'], 'PAGE123'];
        yield 'null-optional-value' => ['PAGE{uid:int}{lang:str}?', ['uid' => 123, 'lang' => null], 'PAGE123'];
        yield 'empty-string-value' => ['PAGE{uid:int}-{suffix:str}', ['uid' => 123, 'suffix' => ''], null, true];
        yield 'zero-value' => ['PAGE{uid:int}', ['uid' => 0], 'PAGE0'];
        
        // Nested optional structure
        yield 'nested-optional-partial' => ['PAGE{uid:int}(-{lang:str}(-{var:str}))', ['uid' => 123, 'lang' => 'en'], 'PAGE123-en'];
        // no default value provided cannot see the middle-part
        yield 'nested-optional-skip-middle' => ['PAGE{uid:int}(-{lang:str}(-{var:str}))', ['uid' => 123, 'var' => 'mobile'], null, true];
    }

    public static function heuristicEdgeCaseProvider(): Generator
    {
        $patterns = ['PAGE{uid:int}', 'ARTICLE{id:int}'];
        
        yield 'empty-string' => [$patterns, '', false];
        yield 'single-char' => [$patterns, 'P', false];
        yield 'exact-min-length' => [$patterns, 'PAGE1', true];
        yield 'binary-data' => [$patterns, "\x00\x01\x02", false];
        $str = 'PAGE123';
        $strLen = strlen('PAGE123');
        yield 'very-long-input' => [$patterns, str_repeat($str, (int)ceil(AnalyzerResult::MAX_LEN_LIMIT / $strLen) + $strLen), false];
        yield 'mixed-case' => [$patterns, 'page123', false]; // Should be case-sensitive
        yield 'partial-match' => [$patterns, 'PAG123', false];
        
        // Edge case: input that starts correctly but has invalid characters
        yield 'starts-correct-invalid-chars' => [$patterns, 'PAGE123$%^', false];
    }

    public static function serializationEdgeCaseProvider(): Generator
    {
        yield 'simple-pattern' => ['PAGE{uid:int}'];
        yield 'complex-constraints' => ['PAGE{uid:int(min=1, max=999)}(-{lang:str(minLen=2, maxLen=5)})'];
        yield 'deeply-nested' => ['A{a:int}(B{b:int}(C{c:int}(D{d:int})))'];
        yield 'many-optional-groups' => ['{a:int}?{b:int}?{c:int}?{d:int}?{e:int}?'];
        yield 'special-characters' => ['test.{id:int}+{name:str}$'];
        yield 'unicode-literals' => ['TËST{uid:int}🏠{name:str}'];
    }
}
