<?php declare(strict_types=1);

namespace TypedPatternEngine\Tests\Unit;

use TypedPatternEngine\TypedPatternEngine;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Tests for the new optional group default behavior during generation:
 * - Optional groups with defaults are omitted when value equals default
 * - Optional groups are only rendered when needed to reach non-default values
 * - This only affects generation, not matching
 */
class OptionalGroupDefaultGenerationTest extends TestCase
{
    private TypedPatternEngine $engine;

    protected function setUp(): void
    {
        $this->engine = new TypedPatternEngine();
    }

    #[DataProvider('optionalDefaultGenerationProvider')]
    public function testOptionalDefaultGeneration(string $pattern, array $values, string $expectedOutput): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $generated = $compiled->generate($values);

        $this->assertSame($expectedOutput, $generated,
            "Pattern '$pattern' with values " . json_encode($values) . " should generate '$expectedOutput'");

        // Verify round-trip: generated output should still match
        $matchResult = $compiled->match($generated);
        $this->assertNotNull($matchResult, "Generated output '$generated' should match the original pattern");
    }

    #[DataProvider('nestedOptionalDefaultGenerationProvider')]
    public function testNestedOptionalDefaultGeneration(string $pattern, array $values, string $expectedOutput): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $generated = $compiled->generate($values);

        $this->assertSame($expectedOutput, $generated,
            "Nested pattern '$pattern' with values " . json_encode($values) . " should generate '$expectedOutput'");

        // Verify round-trip
        $matchResult = $compiled->match($generated);
        $this->assertNotNull($matchResult, "Generated output '$generated' should match the original pattern");
    }

    public static function optionalDefaultGenerationProvider(): Generator
    {
        // Simple optional group with default
        yield 'simple-optional-omit-default' => [
            'PAGE{id:int}(-{lang:str(default=en)})',
            ['id' => 123],
            'PAGE123'  // Should omit the optional section when using default
        ];

        yield 'simple-optional-explicit-default' => [
            'PAGE{id:int}(-{lang:str(default=en)})',
            ['id' => 123, 'lang' => 'en'],
            'PAGE123'  // Should omit even when explicitly set to default
        ];

        yield 'simple-optional-non-default' => [
            'PAGE{id:int}(-{lang:str(default=en)})',
            ['id' => 123, 'lang' => 'de'],
            'PAGE123-de'  // Should render when value differs from default
        ];

        // Multiple groups in optional section
        yield 'multiple-groups-all-default' => [
            'ITEM{id:int}(-{code:str(default=ABC)}-{version:int(default=1)})',
            ['id' => 100],
            'ITEM100'  // All defaults, omit entire section
        ];

        yield 'multiple-groups-explicit-defaults' => [
            'ITEM{id:int}(-{code:str(default=ABC)}-{version:int(default=1)})',
            ['id' => 100, 'code' => 'ABC', 'version' => 1],
            'ITEM100'  // All equal defaults, omit entire section
        ];

        yield 'multiple-groups-first-non-default' => [
            'ITEM{id:int}(-{code:str(default=ABC)}-{version:int(default=1)})',
            ['id' => 100, 'code' => 'XYZ'],
            'ITEM100-XYZ-1'  // First differs, must render all including default version
        ];

        yield 'multiple-groups-last-non-default' => [
            'ITEM{id:int}(-{code:str(default=ABC)}-{version:int(default=1)})',
            ['id' => 100, 'version' => 2],
            'ITEM100-ABC-2'  // Last differs, must render all including default code
        ];

        // Optional with question mark syntax (normalized to subsequence)
        yield 'question-mark-syntax-default' => [
            'USER{id:int}-{role:str(default=user)}?',
            ['id' => 42],
            'USER42-'  // Literal '-' required, role omitted
        ];

        yield 'question-mark-syntax-explicit-default' => [
            'USER{id:int}-{role:str(default=user)}?',
            ['id' => 42, 'role' => 'user'],
            'USER42-'  // Literal '-' required, role omitted when equals default
        ];

        yield 'question-mark-syntax-non-default' => [
            'USER{id:int}-{role:str(default=user)}?',
            ['id' => 42, 'role' => 'admin'],
            'USER42-admin'
        ];

        // Integer defaults
        yield 'int-default-omitted' => [
            'DOC{id:int}(-v{version:int(default=1)})',
            ['id' => 50],
            'DOC50'
        ];

        yield 'int-default-explicit' => [
            'DOC{id:int}(-v{version:int(default=1)})',
            ['id' => 50, 'version' => 1],
            'DOC50'
        ];

        yield 'int-default-non-default' => [
            'DOC{id:int}(-v{version:int(default=1)})',
            ['id' => 50, 'version' => 2],
            'DOC50-v2'
        ];
    }

    public static function nestedOptionalDefaultGenerationProvider(): Generator
    {
        // Nested optional sections
        yield 'nested-all-defaults-omitted' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            ['id' => 123],
            'PAGE123'
        ];

        yield 'nested-all-explicit-defaults' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            ['id' => 123, 'lang' => 'en', 'variant' => 'mobile'],
            'PAGE123'
        ];

        yield 'nested-outer-non-default-inner-default' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            ['id' => 123, 'lang' => 'de'],
            'PAGE123-de'  // Inner variant is default, omit it
        ];

        yield 'nested-outer-non-default-inner-explicit-default' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            ['id' => 123, 'lang' => 'de', 'variant' => 'mobile'],
            'PAGE123-de'  // Inner variant equals default, omit it
        ];

        yield 'nested-skip-to-inner-non-default' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            ['id' => 123, 'variant' => 'desktop'],
            'PAGE123-en+desktop'  // Need variant, must render lang with default
        ];

        yield 'nested-both-non-default' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            ['id' => 123, 'lang' => 'de', 'variant' => 'desktop'],
            'PAGE123-de+desktop'
        ];

        // Triple nesting
        yield 'triple-nested-all-defaults' => [
            'API{v:int}(/{resource:str(default=users)}(/{id:int(default=1)}(/{action:str(default=view)})))',
            ['v' => 2],
            'API2'
        ];

        yield 'triple-nested-skip-to-action' => [
            'API{v:int}(/{resource:str(default=users)}(/{id:int(default=1)}(/{action:str(default=view)})))',
            ['v' => 2, 'action' => 'edit'],
            'API2/users/1/edit'  // Need action, must render resource and id with defaults
        ];

        yield 'triple-nested-resource-non-default' => [
            'API{v:int}(/{resource:str(default=users)}(/{id:int(default=1)}(/{action:str(default=view)})))',
            ['v' => 2, 'resource' => 'posts'],
            'API2/posts'  // id and action would be default, omit them
        ];

        yield 'triple-nested-resource-and-id' => [
            'API{v:int}(/{resource:str(default=users)}(/{id:int(default=1)}(/{action:str(default=view)})))',
            ['v' => 2, 'resource' => 'posts', 'id' => 5],
            'API2/posts/5'  // action would be default, omit it
        ];

        yield 'triple-nested-skip-to-id' => [
            'API{v:int}(/{resource:str(default=users)}(/{id:int(default=1)}(/{action:str(default=view)})))',
            ['v' => 2, 'id' => 5],
            'API2/users/5'  // Need id, must use resource default, action would be default
        ];

        yield 'triple-nested-resource-default-id-non-default' => [
            'API{v:int}(/{resource:str(default=users)}(/{id:int(default=1)}(/{action:str(default=view)})))',
            ['v' => 2, 'resource' => 'users', 'id' => 5],
            'API2/users/5'  // resource equals default but id differs, must render both
        ];

        // Multiple optional sections (not nested)
        yield 'multiple-sections-all-defaults' => [
            'FILE{name:str}(.{ext:str(default=txt)})(_{version:int(default=1)})',
            ['name' => 'doc'],
            'FILEdoc'
        ];

        yield 'multiple-sections-first-non-default' => [
            'FILE{name:str}(.{ext:str(default=txt)})(_{version:int(default=1)})',
            ['name' => 'doc', 'ext' => 'pdf'],
            'FILEdoc.pdf'  // version section would be default, omit it
        ];

        yield 'multiple-sections-second-non-default' => [
            'FILE{name:str}(.{ext:str(default=txt)})(_{version:int(default=1)})',
            ['name' => 'doc', 'version' => 2],
            'FILEdoc.txt_2'  // Need version, must render ext with default
        ];

        yield 'multiple-sections-both-non-default' => [
            'FILE{name:str}(.{ext:str(default=txt)})(_{version:int(default=1)})',
            ['name' => 'doc', 'ext' => 'pdf', 'version' => 2],
            'FILEdoc.pdf_2'
        ];

        // Complex real-world example
        yield 'complex-url-pattern-minimal' => [
            '/{lang:str(default=en)}?/page/{pageId:int}(-{slug:str(default=untitled)}(#p{paragraph:int(default=1)}))',
            ['pageId' => 123],
            '/page/123'  // lang uses ? syntax, becomes '/' required, lang omitted; slug section all defaults
        ];

        yield 'complex-url-pattern-with-slug' => [
            '/{lang:str(default=en)}?/page/{pageId:int}(-{slug:str(default=untitled)}(#p{paragraph:int(default=1)}))',
            ['pageId' => 123, 'slug' => 'my-article'],
            '/page/123-my-article'  // lang default, slug differs, paragraph default omitted
        ];

        yield 'complex-url-pattern-with-paragraph' => [
            '/{lang:str(default=en)}?/page/{pageId:int}(-{slug:str(default=untitled)}(#p{paragraph:int(default=1)}))',
            ['pageId' => 123, 'paragraph' => 3],
            '/page/123-untitled#p3'  // Need paragraph, must render slug with default
        ];

        yield 'complex-url-pattern-full' => [
            '/{lang:str(default=en)}?/page/{pageId:int}(-{slug:str(default=untitled)}(#p{paragraph:int(default=1)}))',
            ['lang' => 'de', 'pageId' => 123, 'slug' => 'my-article', 'paragraph' => 3],
            '/de/page/123-my-article#p3'
        ];
    }

    #[DataProvider('matchingStillUsesDefaultsProvider')]
    public function testMatchingStillUsesDefaults(string $pattern, string $input, array $expectedValues): void
    {
        $compiler = $this->engine->getPatternCompiler();
        $compiled = $compiler->compile($pattern);

        $result = $compiled->match($input);

        $this->assertNotNull($result, "Pattern '$pattern' should match input '$input'");

        foreach ($expectedValues as $key => $expectedValue) {
            $this->assertSame($expectedValue, $result->get($key),
                "Matching should still apply defaults: expected $key to be " . json_encode($expectedValue));
        }
    }

    public static function matchingStillUsesDefaultsProvider(): Generator
    {
        // Verify that matching still applies defaults when groups are missing
        yield 'match-applies-default' => [
            'PAGE{id:int}(-{lang:str(default=en)})',
            'PAGE123',
            ['id' => 123, 'lang' => 'en']  // Default should be applied during match
        ];

        yield 'match-nested-applies-defaults' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            'PAGE123',
            ['id' => 123, 'lang' => 'en', 'variant' => 'mobile']  // All defaults applied
        ];

        yield 'match-partial-nested-applies-defaults' => [
            'PAGE{id:int}(-{lang:str(default=en)}(+{variant:str(default=mobile)}))',
            'PAGE123-de',
            ['id' => 123, 'lang' => 'de', 'variant' => 'mobile']  // Inner default applied
        ];
    }
}