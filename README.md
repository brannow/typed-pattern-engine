# TypedPatternEngine

Human-readable pattern matching engine with type-safe value extraction and bidirectional processing for PHP 8.1+.

## Overview

TypedPatternEngine provides a Domain Specific Language (DSL) for defining patterns that compile to regular expressions. It extracts typed, validated values from matched strings and supports bidirectional processing (matching input and generating output from values).

**Key Features:**
- Type-safe value extraction with automatic casting
- Constraint validation system  
- Optional sections with sophisticated logic
- Bidirectional pattern processing (match ↔ generate)
- Pattern compilation with validation pipeline
- Heuristic analysis for performance optimization

## Installation

```bash
composer require brannow/typed-pattern-engine
```

**Requirements:** PHP 8.1+

## Quick Start

```php
use TypedPatternEngine\TypedPatternEngine;
use TypedPatternEngine\Types\TypeRegistry;

$engine = new TypedPatternEngine(new TypeRegistry());
$compiler = $engine->getPatternCompiler();

// Compile pattern
$pattern = "user/{id:int}/posts/{postId:int}?";
$compiled = $compiler->compile($pattern);

// Match input
$result = $compiled->match("user/123/posts/456");
if ($result) {
    echo $result->get('id');      // 123 (int)
    echo $result->get('postId');  // 456 (int)
}

// Generate output
$output = $compiled->generate(['id' => 123, 'postId' => 456]);
echo $output; // "user/123/posts/456"
```

## Core Concepts

### Pattern Syntax

Patterns consist of three elements:
- **Literals**: Static text that must match exactly
- **Groups**: Dynamic segments that capture values: `{name:type}`  
- **Optional Sections**: Parts that may or may not be present: `(...)`

#### Basic Groups
```php
{groupName:type}                    // Required group
{groupName:type}?                   // Optional group (normalized to subsequence)
{groupName:type(constraints)}       // Group with validation constraints
```

#### Examples
```php
"PAGE{id:int}"                      // Matches: PAGE123
"user-{userId:int}-post-{postId:int}" // Matches: user-5-post-10  
"{username:str}@{domain:str}"       // Matches: john@example.com
```

### Types

Built-in types with automatic casting:

| Type | Pattern | PHP Type | Constraints |
|------|---------|----------|-------------|
| `int` | `\d+` | `int` | `min`, `max`, `default` |
| `str` | `[^/]+` | `string` | `minLen`, `maxLen`, `contains`, `startsWith`, `endsWith`, `default` |

### Optional Sections

Optional sections use subsequence logic where ALL elements must be satisfied if the section matches:

```php
"PAGE{id:int}(-{lang:str})"         // Matches: PAGE123 or PAGE123-en
"doc/{id:int}(/edit)"               // Matches: doc/5 or doc/5/edit
```

### Constraints

Constraints provide validation without affecting pattern generation:

```php
"{id:int(min=1, max=9999)}"         // Integer between 1-9999
"{code:str(minLen=3, maxLen=10)}"   // String length 3-10 characters
"{lang:str(default=en)}?"           // Optional with default value
```

## API Reference

### TypedPatternEngine

Main entry point for the pattern engine.

```php
$engine = new TypedPatternEngine(TypeRegistry $typeRegistry);
$patternCompiler = $engine->getPatternCompiler();
$heuristicCompiler = $engine->getHeuristicCompiler();
```

### PatternCompiler

Compiles patterns into executable objects.

```php
$compiled = $compiler->compile(string $pattern): CompiledPattern;
$dehydrated = $compiler->dehydrate(CompiledPattern $pattern): array;
$rehydrated = $compiler->hydrate(array $data): CompiledPattern;
```

### CompiledPattern  

Executable pattern with matching and generation capabilities.

```php
// Matching
$result = $compiled->match(string $input): ?MatchResult;
$regex = $compiled->getRegex(): string;

// Generation  
$output = $compiled->generate(array $values): string;
```

### MatchResult

Contains extracted values from successful matches.

```php
$value = $result->get(string $key): mixed;
$input = $result->getInput(): string; 
$allValues = $result->toArray(): array;
$errors = $result->getErrors(): array;
```

### HeuristicCompiler

Optimizes pattern matching with pre-filtering.

```php
$heuristic = $heuristicCompiler->compile(array $compiledPatterns);
$canMatch = $heuristic->support(string $input): bool;
```

## Pattern Examples

### Web Routes
```php
// RESTful API
"/api/v{version:int}/{resource:str}/{id:int}?(/edit)"

// Blog URLs  
"/blog/{year:int(min=2000)}/{month:int(min=1,max=12)}/{slug:str}"

// Multilingual routes
"/{lang:str}?/page/{pageId:int}(-{slug:str})"
```

### File Paths
```php
// Upload paths
"uploads/{year:int}/{month:int}/{filename:str}.{ext:str}"

// Document storage
"docs/{category:str}/{docId:int}(-v{version:int}).pdf"
```

### Identifiers  
```php
// Order numbers
"ORD-{year:int}-{number:int(min=1,max=999999)}"

// Product SKUs
"{category:str}-{product:int}-{variant:str}?"
```
### Project Structure

```
src/
├── Compiler/           # Pattern compilation and execution
├── Exception/          # Custom exceptions  
├── Heuristic/          # Performance optimization
├── Nodes/              # AST node implementations
├── Pattern/            # Pattern parsing and compilation
├── Types/              # Type system and constraints
├── Validation/         # Validation pipeline
└── TypedPatternEngine.php

tests/Unit/             # Comprehensive test suite
documentation/          # Detailed pattern syntax docs
```

## Advanced Usage

### Custom Types

Extend the type system by registering custom types:

```php
$typeRegistry = new TypeRegistry();
// Custom type registration would be handled via TypeRegistry extension
```

### Error Handling

```php
use TypedPatternEngine\Exception\PatternSyntaxException;
use TypedPatternEngine\Exception\PatternValidationException;

try {
    $compiled = $compiler->compile($pattern);
    $result = $compiled->match($input);
} catch (PatternSyntaxException $e) {
    // Invalid pattern syntax
} catch (PatternValidationException $e) {  
    // Pattern validation failed
}
```

### Performance Optimization

Use heuristic pre-filtering for multiple patterns:

```php
$patterns = [
    $compiler->compile("PAGE{id:int}"),
    $compiler->compile("ARTICLE{id:int}"), 
    $compiler->compile("USER{name:str}")
];

$heuristic = $engine->getHeuristicCompiler()->compile($patterns);

if ($heuristic->support($input)) {
    // Only test patterns if heuristic suggests possible match
    foreach ($patterns as $pattern) {
        if ($result = $pattern->match($input)) {
            break;
        }
    }
}
```

## Documentation

- [Pattern Syntax Reference](documentation/PATTERN_SYNTAX.md) - Complete syntax guide with examples
- [Architecture Overview](documentation/ARCHITECTURE.md) - Technical implementation details

## License

MIT License. See LICENSE file for details.

## Contributing

1. Fork the repository
2. Create feature branch (`git checkout -b feature/new-feature`)  
3. Write tests for new functionality
4. Ensure all tests pass (`./docker.sh exec composer test`)
5. Submit pull request

## Authors

**Benjamin Rannow**
