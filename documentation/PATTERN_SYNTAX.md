# Pattern Compiler Documentation

## Overview

The TypedPatternEngine is a PHP 8.1+ library that provides a human-readable DSL (Domain Specific Language) for pattern matching. It compiles patterns into regular expressions and can extract typed, validated values from matched strings.

## Core Concepts

The Type Checks and Constraints should happen at Runtime on the match and generate call and not reflect into the generated Regex

### Pattern Structure

A pattern consists of three main elements:

1. **Literals** - Static text that must match exactly
2. **Groups** - Dynamic segments that capture values: `{name:type}`
3. **Optional Sections** - Parts that may or may not be present: `(...)` or `{...}?`

## Syntax Reference

### Basic Group Syntax

#### 🔒 Greediness & Adjacency Rules (Sequence-Level Validation)

**Core Rules:**
1. **Groups are greedy** when their type is greedy by nature (`int`, `str`)
2. **No greedy group may follow another greedy group** - regardless of sequence boundaries
3. **Constraints do NOT affect greediness**: Constraints are for validation only, not regex pattern generation
4. **Optionality does NOT affect greediness**: Optional groups remain greedy
5. **Only literals provide safe boundaries** for greedy groups

#### **Sequence Satisfaction Rules**

**Universal Rule**: Every sequence (root or sub) requires **ALL child elements** to be satisfied.

**Exception Mechanism**: **SubSequences are optional** and cascade optionality to all contained elements.

```
Pattern: ABC{a:int}-{b:str}-{c:int}
Sequence: [LiteralNode("ABC"), GroupNode(a), LiteralNode("-"), GroupNode(b), LiteralNode("-"), GroupNode(c)]  
Rule: ALL elements must be satisfied → a AND b AND c required, literals provide boundaries

Pattern: ABC{a:int}(-{b:str})  
Sequence: [LiteralNode("ABC"), GroupNode(a), SubSequence([LiteralNode("-"), GroupNode(b)])]
Rule: ABC AND a required, SubSequence(b) is optional but has literal boundary
```

#### **Greediness Validation Matrix**

**V1.0 Strict Rules - All Adjacent Greedy Groups Are Forbidden:**

| Pattern | Valid? | Reason |
|---------|--------|---------|
| `{a:int}-{b:int}` | ✓ | Literal separator provides boundary |
| `{a:str}-{b:str}` | ✓ | Literal separator provides boundary |
| `{a:int}({b:str})` | ✗ | No boundary between a and b - both greedy |
| `{a:str}({b:int})-{c:int}` | ✗ | No boundary between a and b - both greedy |
| `{a:int}(-{b:str}){c:int}` | ✗ | No boundary between b and c - both greedy |
| `{a:int}(-{b:str}-{c:int})` | ✓ | Literal separator within SubSequence |

**Key Principle**: Greedy groups need **literal delimiters** to know where to stop consuming input. Optionality and SubSequences do not change greediness behavior.

#### **Greediness Rules (v1.0)**

**Greedy Types**: `int`, `str` 
**Constraint Impact**: None - constraints are validation-only

```
{a:int}              → Greedy
{a:int(max=999)}     → Greedy (constraints don't affect greediness)
{a:int(min=1)}       → Greedy  
{a:str}              → Greedy
{a:str(maxLen=10)}   → Greedy (constraints don't affect greediness)
{a:str(minLen=3)}    → Greedy
```

**V1.0 Limitation**: Adjacent greedy groups must be separated by literal delimiters.

**Examples of V1.0 Rules**:
```
{a:int}-{b:int}             → ✓ Valid (literal separator)
{a:int}(-{b:str}-{c:int})   → ✓ Valid (literal separators within SubSequence)
{a:int}{b:int}              → ✗ Forbidden (adjacent greedy groups)
{a:int}({b:str}){c:int}     → ✗ Forbidden (no boundaries between greedy groups)
{a:str}({b:int})-{c:int}    → ✗ Forbidden (a and b have no boundary)
```

```
{groupName:type}
```

- `groupName` - Identifier for the captured value (alphanumeric + underscore, must start with letter)
- `type` - Data type for validation and parsing
- Groups are **required** by default

**Examples:**
```
{id:int}           → Captures an integer as 'id'
{username:string}   → Captures string as 'username'
```

### Optionality Architecture

**Core Principle**: Only **SubSequences** handle optionality. Groups are always required within their parent sequence.

| Source text               | AST Representation | Behavior                                      |
|---------------------------|--------------------|-----------------------------------------------|
| `{id:int}`                | `GroupNode`        | Required group                                |
| `{id:int}?`               | `SubSequence(GroupNode)` | Optional single-group subsequence    |
| `(-{lang:alpha})`         | `SubSequence(LiteralNode, GroupNode)` | Optional multi-element subsequence |

#### **Syntax Normalization**

```
{groupName:type}?  →  ({groupName:type})
```

**During parsing**, `{id:int}?` is **automatically normalized** to `({id:int})` - a SubSequence containing a single GroupNode.

**Benefits:**
- **Single Responsibility**: Only SubSequence handles optional logic
- **Consistent Processing**: All groups are always required within their sequence
- **Simplified Validation**: One optionality mechanism to validate

**Examples:**
```
{lang:alpha}?      → ({lang:alpha})     // Normalized to SubSequence
{version:int}?     → ({version:int})    // Normalized to SubSequence
(-{lang:alpha})    → (-{lang:alpha})    // Already a SubSequence
```

### Groups with Constraints

```
{groupName:type(constraint1=value1, constraint2=value2)}
```

Constraints provide additional validation rules for the captured value. **Constraints are validation-only and do not affect regex pattern generation or greediness behavior.**

**Examples:**
```
{id:int(min=1, max=9999)}          → Integer between 1 and 9999
{code:str(minLen=3, maxLen=10)} → String with length 3-10
```

### SubSequences (Optional Sections)

```
(literal and/or groups)
```

**SubSequences are the only mechanism for optionality** in the pattern system. They create sequence boundaries and handle all optional logic.

#### **SubSequence Rules**

1. **All-or-Nothing Satisfaction**: ALL children in a SubSequence must be satisfied for the SubSequence to match
2. **Optional by Nature**: SubSequences don't need `?` markers - they're optional by design  
3. **Greediness Inheritance**: Groups within SubSequences remain greedy - optionality does not change greediness
4. **Nested Optionality**: SubSequences can contain other SubSequences for complex logic

#### **Valid Pattern Examples**

```
PAGE{id:int}(-{lang:str})         → "PAGE123" or "PAGE123-en" (literal boundary)
/article/{id:int}(/comments)      → "/article/5" or "/article/5/comments" (literal boundary)

// Complex nesting with literal separators
{a:int}(-{b:str}-{c:int})         → a required, b and c both optional with separator

// All-or-nothing within SubSequence  
USER({name:str}-{age:int})        → Both name AND age required if SubSequence matches (literal separator)
```

#### **SubSequence Satisfaction Logic**

```
Pattern: ABC({x:int}-{y:str})-{z:int}

Evaluation:
1. ABC must match (literal)
2. SubSequence is optional:
   - If present: BOTH x AND y must be satisfied (literal separator required)
   - If absent: SubSequence is skipped
3. z must match (required group, separated by literal)

Valid inputs: "ABC-123", "ABC123-test-456" 
Invalid: "ABC123-456" (y missing within SubSequence)

Note: This pattern is valid because x-y have literal separator, and SubSequence-z have literal separator
```

#### **Parsing Normalization Impact**

Since `{group}?` → `({group})`, all optionality flows through SubSequences:

```
// Before normalization (user syntax) - INVALID PATTERN
PAGE{id:int}{lang:str}?{version:int}?

// After normalization (AST representation) - STILL INVALID
PAGE{id:int}({lang:str})({version:int})

// This pattern is FORBIDDEN because:
// 1. id (greedy) adjacent to lang (greedy) - no literal separator
// 2. lang (greedy) adjacent to version (greedy) - no literal separator

// CORRECT V1.0 pattern with literal separators:
PAGE{id:int}-{lang:str}?-{version:int}?
→ PAGE{id:int}(-{lang:str})(-{version:int})
```

**Requirements:**
- SubSequences **must contain at least one element** (group or literal)
- Empty subsequences `()` are not allowed and will throw a parsing error

**Invalid patterns:**
```
PAGE()                           → ERROR: Empty optional subsequence
PAGE{id:int}()                   → ERROR: Empty optional subsequence  
```

### Literals and Reserved Characters

Literals are any characters outside of groups and optional sections.

**Reserved characters (cannot be used in literals):**
- `{` `}` - Reserved for groups: `{name:type}`
- `(` `)` - Reserved for optional subsequences: `(-{name:type})`

**Auto-escaped characters:**
`. ^ $ * + ? [ ] \ / |`

**Examples:**
```
user.{id:int}           → Matches "user.123" (dot is escaped)
price: ${amount:int}    → Matches "price: $50" ($ is escaped)
func_{id:int}           → Use underscore instead of parentheses
```

**Invalid patterns:**
```
func(){id:int}          → ERROR: Reserved characters '(' ')' in literals
test(value){id:int}     → ERROR: Reserved characters '(' ')' in literals
```

## Built-in Types

### Type Casting Rules

**Core Principle**: Pattern types enforce type casting during value processing.

#### Integer Type Casting (`int`)
- **Target Type**: Always returns PHP `int`
- **Input Validation**: Must be numeric (uses `is_numeric()` check)
- **Casting Rules**:
  - `string('123')` → `int(123)` ✓ Valid numeric string
  - `int(123)` → `int(123)` ✓ Already correct type
  - `float(123.0)` → `int(123)` ✓ Whole number float
  - `string('123.45')` → ✗ Throws `PatternEngineInvalidArgumentException` (decimal not allowed)
  - `string('a1b2c')` → ✗ Throws `PatternEngineInvalidArgumentException` (non-numeric)
  - `string('123abc')` → ✗ Throws `PatternEngineInvalidArgumentException` (mixed alphanumeric)
  - `bool(true)` → ✗ Throws `PatternEngineInvalidArgumentException` (not numeric)
  - `null` → ✗ Throws `PatternEngineInvalidArgumentException` (not numeric)
  - `array([123])` → ✗ Throws `PatternEngineInvalidArgumentException` (not scalar)

#### String Type Casting (`str`)
- **Target Type**: Always returns PHP `string`
- **Input Validation**: Must be scalar value
- **Casting Rules**:
  - `string('abc')` → `string('abc')` ✓ Already correct type
  - `int(123)` → `string('123')` ✓ Convert to string
  - `float(123.45)` → `string('123.45')` ✓ Convert to string
  - `bool(true)` → `string('1')` ✓ Convert to string
  - `bool(false)` → `string('')` ✓ Convert to string
  - `null` → ✗ Throws `PatternEngineInvalidArgumentException` (not scalar)
  - `array(['abc'])` → ✗ Throws `PatternEngineInvalidArgumentException` (not scalar)

#### Implementation Requirements
```php
use TypedPatternEngine\Exception\PatternEngineInvalidArgumentException;

// For int type casting
if (!is_numeric($value)) {
    throw new PatternEngineInvalidArgumentException("Value must be numeric for int type, got: " . gettype($value));
}
return (int)$value;

// For str type casting  
if (!is_scalar($value) || is_null($value)) {
    throw new PatternEngineInvalidArgumentException("Value must be scalar for str type, got: " . gettype($value));
}
return (string)$value;
```

### Numeric Types

| Type | Pattern | Description | Constraints              |
|------|---------|-------------|--------------------------|
| `int` | `\d+` | Positive integers (always cast to PHP int) | `min`, `max` , `default`* |

**Examples:**
```
{id:int}                    → "123", "45678" (returns int values)
{age:int(min=0, max=120)}   → "25", "100" (returns int values)
{age:int(default=0)}?  → "25", "100", "0" (if age is optional and not provided)
```

### String Types

| Type | Pattern | Description | Constraints                                              |
|------|---------|-------------|----------------------------------------------------------|
| `string` | `[^/]+` | Any non-slash characters (always cast to PHP string) | `minLen`, `maxLen`, `contains`, `startWith`, `endWith`,  `default`* |

**Examples:**
```
{name:str}                       → "John Doe", "Test-123" (returns string values)
{name:str(minLen=2, maxLen=50)} → "Jo" to 50 chars (returns string values)
```

## Pattern Examples

### Basic Patterns

```php
// Simple ID pattern
"PAGE{id:int}"
→ Matches: "PAGE1", "PAGE123", "PAGE999999"

// Multiple groups
"user-{userId:int}-post-{postId:int}"
→ Matches: "user-5-post-10", "user-123-post-456"

// Mixed types
"{username:alnum}@{domain:slug}"
→ Matches: "john123@my-site", "admin@example-blog"
```

### Patterns with Optional Elements

```php
// Optional suffix
"PAGE{id:int}(-{lang:alpha})"
→ Matches: "PAGE123", "PAGE123-en", "PAGE123-fr"

// Multiple optional groups
"doc_{docId:int}_{version:int}?_{status:alpha}?"
→ Matches: "doc_100_", "doc_100_1_", "doc_100_1_draft"

// Optional with literals
"article/{id:int}(/edit)"
→ Matches: "article/5", "article/5/edit"
```

### Complex Real-World Patterns

```php
// Blog URL pattern
"/blog/{year:int(min=2000, max=2099)}/{month:int(min=1, max=12)}/{slug:slug}"
→ Matches: "/blog/2024/03/my-first-post"

// REST API endpoint
"/api/v{version:int}/{resource:alpha}/{id:int}?(/edit)"
→ Matches: "/api/v1/users", "/api/v2/posts/123", "/api/v1/users/5/edit"

// File path with optional extension
"uploads/{year:int}/{month:int}/{filename:slug}(.{ext:alpha})"
→ Matches: "uploads/2024/12/document", "uploads/2024/12/image.jpg"

// Multilingual route
"/{lang:alpha}?/page/{pageId:int}(-{slug:slug})"
→ Matches: "/page/1", "/en/page/1", "/fr/page/1-about-us"
```

## Usage in PHP

### Basic Usage

```php
use TypedPatternEngine\TypedPatternEngine;

$engine = new TypedPatternEngine();

// Compile pattern
$compiler = $engine->getPatternCompiler();
$pattern = "user/{id:int}/posts/{postId:int}?";
$compiled = $compiler->compile($pattern);

// Get generated regex
echo $compiled->getRegex();
// Output: /^user\/(?P<g1>\d+)\/posts\/(?P<g2>\d+)?$/

// Match and extract values
$result = $compiled->match("user/123/posts/456");
if ($result) {
    echo $result->get('id');      // 123 (as integer)
    echo $result->get('postId');  // 456 (as integer)

    // Get all values
    print_r($result->toArray());
    // Array(
    //     [input] => user/123/posts/456
    //     [id] => 123
    //     [postId] => 456
    // )
}
```

### Working with Constraints

```php
$pattern = "product-{id:int(min=1000, max=9999)}";
$compiled = $compiler->compile($pattern);

$result1 = $compiled->match("product-5000");  // ✓ Valid
$result2 = $compiled->match("product-500");   // ✗ Below min
$result3 = $compiled->match("product-10000"); // ✗ Above max
```

### Handling Optional Values and Defaults

```php
$pattern = "PAGE{id:int}(-{lang:alpha(default=en)})?";
$compiled = $compiler->compile($pattern);

$result1 = $compiled->match("PAGE123");
echo $result1->get('id');    // 123
echo $result1->get('lang');  // "en" (default applied)

$result2 = $compiled->match("PAGE123-fr");
echo $result2->get('id');    // 123
echo $result2->get('lang');  // "fr"
```

### Default Constraint Behavior

**Important**: The `default` constraint only applies to **optional groups**. 
If used on required groups, a compilation error will be thrown.

```php
// ✗ ERROR: Will throw ShortNrPatternConstraintException
{uid:int(default=42)}     

// ✓ CORRECT: Default applied when group is missing
{uid:int(default=42)}?    

// ✓ CORRECT: Default applied when subsequence is missing  
(-{uid:int(default=42)})  
```

## Limitations and Boundaries

### Pattern Syntax Rules

1. **Group Names**
    - Must start with letter or underscore
    - Can contain letters, numbers, underscores
    - Case-sensitive
    - Must be unique within pattern

2. **Optional Markers**
    - `?` must always be placed **outside** groups
    - `{name:type}?` ✓ Correct
    - `{name?:type}` ✗ Not supported
    - `(...) ` ✓ Correct, Sub Sequence sections don't need `?`

3. **Reserved Characters**
    - `{` `}` are reserved for groups and cannot appear in literals
    - `(` `)` are reserved for optional subsequences and cannot appear in literals
    - Use alternatives: `_`, `-`, `.`, or other characters for literal text

4. **Optional Sections**
    - Must contain at least one element (group or literal)
    - Empty subsequences `()` are not allowed
    - Use meaningful content: `(-{lang:str})` not `()`

5. **Nesting**
    - Groups cannot be nested inside other groups
    - Optional sections can contain multiple groups
    - Keep nesting simple for maintainability

### Type Constraints

1. **Integer Constraints**
    - `min` and `max` are validated after regex matching (not during pattern generation)
    - Large numbers work but are validated as PHP integers
    - Negative numbers require custom type or pattern
    - `default` only works on optional groups (`{name:int}?` or subsequences)

2. **String Constraints**
    - Default pattern excludes forward slashes `/`
    - Use custom `pattern` constraint for specific formats
    - **v1.0**: Length constraints are validation-only (not applied at regex level)
    - `default` only works on optional groups (`{name:str}?` or subsequences)

3. **Default Constraint Rules**
    - Only valid on optional groups: `{name:type(default=value)}?`
    - Compilation error if used on required groups: `{name:type(default=value)}`
    - Applied when optional group/subsequence is missing from input
    - *\* See "Default Constraint Behavior" section for details*

### Performance Considerations

1. **Compilation**
    - Compile patterns once and reuse
    - Compiled patterns are immutable
    - Cache compiled patterns in production

2. **Matching**
    - Regex complexity affects performance
    - Constraints add post-processing overhead
    - Simple patterns are always faster

## Extending the System

### Adding Custom Types

```php
// In generateTypeRegex method
'phone' => '\+?[0-9]{1,3}[-.\s]?\(?[0-9]{1,4}\)?[-.\s]?[0-9\s-]{1,9}',
'date' => '\d{4}-\d{2}-\d{2}',
'time' => '\d{2}:\d{2}(:\d{2})?',
'ip' => '\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}',
'hex' => '[0-9a-fA-F]+',
'base64' => '[A-Za-z0-9+/]+=*',
```

### Adding Custom Constraints

```php
// In generateIntRegex or generateStringRegex
'divisibleBy' => function($value, $constraint) {
    return $value % $constraint === 0;
},
'enum' => function($value, $constraint) {
    $allowed = explode('|', $constraint);
    return in_array($value, $allowed);
}
```

## Best Practices

### Pattern Design

1. **Keep It Simple**
    - Prefer multiple simple patterns over one complex pattern
    - Use meaningful group names
    - Document complex patterns

2. **Use Appropriate Types**
    - Choose the most specific type available
    - Add constraints for validation
    - Create custom types for domain-specific needs

3. **Optional Elements**
    - Place most specific/required parts first
    - Group related optional elements
    - Avoid deeply nested optionals

### Error Handling

```php
try {
    $compiled = $compiler->compile($pattern);
    $result = $compiled->match($input);

    if ($result === null) {
        // No match
    } else {
        // Process matches
    }
} catch (PatternEngineInvalidArgumentException $e) {
    // Invalid pattern syntax
    echo "Pattern error: " . $e->getMessage();
}
```

## Common Patterns Library

### Web Routes

```php
// RESTful resource
"{resource:alpha}/{id:int}?(/edit|/delete)?"

// API versioning
"/api/v{version:int}/{endpoint:slug}"

// Blog/CMS
"/{category:slug}/{year:int}/{month:int}/{post:slug}"

// User profiles
"/@{username:alnum}(/followers|/following)"
```

### File Paths

```php
// Upload path
"uploads/{year:int}/{month:int}/{day:int}/{hash:alnum}.{ext:alpha}"

// Document storage
"docs/{category:slug}/{docId:uuid}(-v{version:int}).pdf"

// Image variants
"images/{id:int}(-{size:alnum}).{ext:alpha}"
```

### Identifiers

```php
// Order number
"ORD-{year:int}-{number:int(min=0, max=999999)}"

// SKU
"{category:alpha}-{product:int}-{variant:alnum}?"

// Transaction ID
"TXN{date:int}{sequence:int(min=0, max=9999)}"
```

## Troubleshooting

### Pattern Not Matching

1. Check for typos in pattern syntax
2. Verify group types match input format
3. Check for reserved characters `()` `{}` in literals
4. Test with simpler pattern first

### Reserved Character Errors

1. Replace `()` with `_`, `-`, or other characters in literals
2. Use `(-{group})` for optional sections, not literal parentheses
3. Ensure `{}` only used for groups, not in literal text

### Empty Subsequence Errors

1. Empty `()` sections are not allowed - add content: `(-{group})`
2. Use specific optional groups instead: `{group}?`
3. Remove unnecessary empty subsequences from patterns

### Unexpected Values

1. Verify constraint syntax
2. Check type conversion logic
3. Ensure optional groups are properly marked
4. Test edge cases

### Performance Issues

1. Simplify complex patterns
2. Cache compiled patterns
3. Reduce number of groups
4. Avoid backtracking in regex

## Version History

- **1.0.0** - Initial release with basic types and optional syntax
- **Future** - Reverse compilation, custom validators, advanced types
