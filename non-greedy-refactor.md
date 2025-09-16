# TypedPatternEngine Builder-Based Constraint System

## Executive Summary

This blueprint replaces constraint-level regex rewriting with a centralized **Builder Pattern**.
Each constraint declares its needs (`min`, `max`, `regexOverride`, etc.), and the `ConstraintRegexBuilder` assembles a final, optimized regex with full context.

---

## Core Principles

1. **Separation of Concerns**

    * **Constraints**: Declare requirements only.
    * **Types**: Set defaults (char class, sign, Unicode).
    * **Builder**: Central “regex brain,” merges all data and decides lazy/greedy, quantifiers, and safety rules.

2. **Centralized Regex Logic**

    * Prevents double-wrapped quantifiers.
    * Merges overlapping constraints safely (min/max/regex overrides).
    * Provides one place to handle performance heuristics.

3. **Extensibility**

    * New constraints (e.g., `ExactLength`, `AllowEmpty`, `RegexOverride`) just mutate builder state.

---

## Architecture Overview

```
Constraint (interface)
   └── apply(ConstraintRegexBuilder $builder)

Type (e.g., IntType, StringType)
   └── createRegexBuilder() + orchestrates constraints

ConstraintRegexBuilder
   ├── Collects min, max, regex override, char class, sign, Unicode
   ├── Decides quantifiers, greedy vs lazy
   └── build() → final regex
```

---

## ConstraintRegexBuilder Example

```php
class ConstraintRegexBuilder {
    private ?int $minWidth = null;
    private ?int $maxWidth = null;
    private ?string $basePattern = null;
    private bool $lazy = false;
    private bool $unicode = false;
    private bool $allowSign = false;

    public function setMinWidth(int $min): self {
        $this->minWidth = max($this->minWidth ?? 0, $min);
        return $this;
    }

    public function setMaxWidth(int $max): self {
        $this->maxWidth = $this->maxWidth 
            ? min($this->maxWidth, $max)
            : $max;
        $this->lazy = true;
        return $this;
    }

    public function setBasePattern(string $pattern): self {
        $this->basePattern = $pattern;
        return $this;
    }

    public function enableUnicode(): self {
        $this->unicode = true;
        return $this;
    }

    public function allowSign(bool $flag = true): self {
        $this->allowSign = $flag;
        return $this;
    }

    public function build(): string {
        $pattern = $this->basePattern ?? '[^/]';
        $quantifier = $this->buildQuantifier();
        $full = $pattern . $quantifier;
        if ($this->allowSign) {
            $full = '[-+]?' . $full;
        }
        return $full;
    }

    public function isGreedy(): bool {
        return !$this->lazy;
    }

    private function buildQuantifier(): string {
        $min = $this->minWidth ?? 1;
        if ($this->maxWidth !== null) {
            return '{' . $min . ',' . $this->maxWidth . '}?';
        } elseif ($this->minWidth !== null) {
            return '{' . $min . ',}';
        }
        return '+';
    }
}
```

---

## Example Constraints

```php
interface Constraint {
    public function apply(ConstraintRegexBuilder $builder): void;
}

class MinLengthConstraint implements Constraint {
    public function __construct(private int $min) {}
    public function apply(ConstraintRegexBuilder $builder): void {
        $builder->setMinWidth($this->min);
    }
}

class MaxLengthConstraint implements Constraint {
    public function __construct(private int $max) {}
    public function apply(ConstraintRegexBuilder $builder): void {
        $builder->setMaxWidth($this->max);
    }
}

class SignConstraint implements Constraint {
    public function apply(ConstraintRegexBuilder $builder): void {
        $builder->allowSign();
    }
}
```

---

## Type Integration

```php
class IntType {
    public function getRegex(array $constraints): string {
        $builder = (new ConstraintRegexBuilder())
            ->setBasePattern('\d');
        foreach ($constraints as $c) $c->apply($builder);
        return $builder->build();
    }

    public function isGreedy(array $constraints): bool {
        $builder = new ConstraintRegexBuilder();
        foreach ($constraints as $c) $c->apply($builder);
        return $builder->isGreedy();
    }
}

class StringType {
    public function getRegex(array $constraints): string {
        $builder = (new ConstraintRegexBuilder())
            ->setBasePattern('[^/]')
            ->enableUnicode();
        foreach ($constraints as $c) $c->apply($builder);
        return $builder->build();
    }
}
```

---

## Pitfalls & Solutions

| Pitfall                             | Solution                                                                                      |
| ----------------------------------- | --------------------------------------------------------------------------------------------- |
| **Double quantifier wrapping**      | Builder owns all quantifier logic; constraints only set properties.                           |
| **Conflicting min/max constraints** | Builder merges values (`min = max(mins)`, `max = min(maxes)`).                                |
| **Regex override + constraints**    | If `RegexConstraint` sets full pattern, Builder respects override and skips quantifier logic. |
| **Performance risk**                | Builder can analyze bounds; warn if adjacent group product `maxA × maxB` > threshold.         |
| **Signed integers**                 | Builder adds sign prefix only if type/constraint enables it.                                  |
| **Unicode correctness**             | Builder toggles `/u` mode for strings; quantifiers count characters.                          |

---

## Example DSL

```php
$pattern = "{name:str(minLen=3,maxLen=5)}{num:int(max=99)}";
```

Builder output:

```
Regex: [^/]{3,5}?\d{1,2}?
```

---

## GreedyValidator Simplification

```php
if ($child instanceof TypeNodeInterface) {
    $isGreedy = $child->getType()->isGreedy($child->getConstraints());
    if ($isGreedy) { ... }
}
```

Now, all greedy/non-greedy decisions flow from **Builder** state.

---

## Testing Strategy

* **Unit Tests**:

    * Min/Max merging
    * Sign handling
    * Unicode correctness
    * Regex override precedence
* **Integration Tests**:

    * Adjacent bounded groups
    * Performance with large bounds
    * Legacy patterns without constraints
* **Property-Based Tests**:

    * Random patterns with overlapping constraints to ensure builder never produces invalid regex.

---

## Migration Plan

1. Replace `modifyPattern()` with `apply(ConstraintRegexBuilder)` in all constraints.
2. Move quantifier logic from constraints/types → builder.
3. Update tests to validate builder outputs.
4. Roll out incrementally with fallback for old constraints (optional).
