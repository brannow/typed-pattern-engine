# Architecture Overview

This document provides technical implementation details for the TypedPatternEngine library.

## System Architecture

The TypedPatternEngine follows a layered architecture with clear separation of concerns:

```
┌─────────────────────────────────────┐
│         TypedPatternEngine          │  ← Entry Point
├─────────────────────────────────────┤
│  PatternCompiler  │ HeuristicCompiler│  ← Compilation Layer
├─────────────────────────────────────┤
│     PatternParser     │  Validation  │  ← Processing Layer  
├─────────────────────────────────────┤
│    AST Nodes     │     Types        │  ← Data Model Layer
├─────────────────────────────────────┤
│  CompiledPattern  │   MatchResult   │  ← Execution Layer
└─────────────────────────────────────┘
```

## Core Components

### 1. Entry Point

**TypedPatternEngine** (`src/TypedPatternEngine.php`)
- Main facade providing access to compilers
- Manages TypeRegistry instance
- Lazy-loads compiler instances

### 2. Compilation Layer

**PatternCompiler** (`src/Pattern/PatternCompiler.php`)
- Orchestrates pattern compilation workflow
- Integrates parsing, validation, and factory creation
- Provides serialization capabilities (hydrate/dehydrate)

**HeuristicCompiler** (`src/Heuristic/HeuristicCompiler.php`)  
- Creates performance optimization heuristics
- Pre-filters inputs for pattern matching
- Analyzes pattern prefixes and constraints

### 3. Processing Layer

**PatternParser** (`src/Pattern/PatternParser.php`)
- Converts DSL patterns into AST nodes
- Handles syntax normalization (`{group}?` → `({group})`)
- Validates basic syntax rules

**Validation Pipeline** (`src/Validation/`)
- Multi-stage validation system
- Pluggable validator architecture
- Validates greediness, duplicates, tree context

### 4. Data Model Layer

**AST Nodes** (`src/Nodes/`)
- Abstract Syntax Tree representation
- Hierarchical node structure
- Interface-based design for extensibility

**Type System** (`src/Types/`)
- Type definitions with pattern generation
- Constraint system for validation
- Registry pattern for type management

### 5. Execution Layer

**CompiledPattern** (`src/Compiler/CompiledPattern.php`)
- Executable pattern with regex and metadata
- Bidirectional processing (match ↔ generate)
- Immutable after compilation

**MatchResult** (`src/Compiler/MatchResult.php`)
- Container for extracted values
- Type casting and validation results
- Error reporting for constraint violations

## Data Flow

### Compilation Flow
```
Pattern String → Parser → AST → Validator → CompiledPattern
     ↓              ↓       ↓        ↓           ↓
"PAGE{id:int}" → Nodes → Tree → Valid → Executable
```

### Matching Flow  
```
Input String → CompiledPattern → Regex Match → Value Extraction → Type Casting → Constraint Validation → MatchResult
     ↓               ↓              ↓              ↓                ↓                    ↓               ↓
"PAGE123" → /^PAGE(?P<g1>\d+)$/ → ['g1'=>'123'] → ['id'=>'123'] → ['id'=>123] → Validated → Result Object
```

### Generation Flow
```
Values Array → CompiledPattern → Template → Value Casting → String Interpolation → Generated String  
     ↓               ↓              ↓            ↓               ↓                    ↓
['id'=>123] → Template → "PAGE{id}" → "PAGE123" → Interpolation → "PAGE123"
```

## AST Node Architecture

### Node Hierarchy
```
AstNodeInterface
├── AstNode (base implementation)
├── LiteralNode (static text)
├── GroupNode (dynamic capture)  
├── SequenceNode (container)
└── NamedAstNode
    ├── NestedAstNode  
    └── SubSequenceNode (optional logic)
```

### Node Interfaces
- **AstNodeInterface**: Core node contract
- **TypeNodeInterface**: Type-aware nodes  
- **NamedNodeInterface**: Named capture groups
- **NestedNodeInterface**: Container nodes
- **NodeValidationInterface**: Validation context
- **BoundaryProviderInterface**: Pattern boundaries

### Node Responsibilities
- **LiteralNode**: Static text, regex escaping
- **GroupNode**: Value capture, type enforcement
- **SequenceNode**: Child node management  
- **SubSequenceNode**: Optional section logic

## Type System

### Type Architecture
```
TypeInterface
└── Type (base implementation)
    ├── IntType  
    └── StringType
```

### Type Responsibilities
- Pattern generation (`\d+`, `[^/]+`)
- Value casting (string → int)
- Constraint integration

### Constraint System
```  
TypeConstraint (interface)
├── BoundingConstraintInterface
├── RefinementConstraintInterface  
└── ModifyPatternAwareInterface

BaseConstraint (implementation)
├── DefaultConstraint
├── NumberConstraints/
│   ├── MinConstraint
│   └── MaxConstraint
└── StringConstraints/
    ├── MinLengthConstraint
    ├── MaxLengthConstraint
    ├── ContainsConstraint
    ├── StartsWithConstraint
    └── EndsWithConstraint
```

## Validation Pipeline

### Validator Chain
```
ValidationPipeline
├── GreedyValidator       ← Prevents adjacent greedy groups
├── DuplicateGroupValidator ← Ensures unique group names
├── TreeContextValidator  ← Validates AST structure
└── ConstraintValidator   ← Validates type constraints
```

### Validation Rules

**GreedyValidator**
- Detects adjacent greedy types (`int`, `str`)
- Requires literal separators between greedy groups
- Validates across sequence boundaries

**DuplicateGroupValidator**
- Ensures group name uniqueness within pattern
- Case-sensitive validation

**TreeContextValidator**  
- Validates AST structure integrity
- Checks node relationships and boundaries

**ConstraintValidator**
- Validates constraint syntax and compatibility
- Ensures `default` constraints only on optional groups

## Pattern Processing Rules

### Greediness Rules
- **Greedy Types**: `int`, `str`
- **Adjacent Restriction**: No adjacent greedy groups
- **Boundary Requirements**: Literal separators required
- **Constraint Independence**: Constraints don't affect greediness

### Optionality Rules  
- **Normalization**: `{group}?` → `({group})`
- **SubSequence Logic**: All-or-nothing satisfaction
- **Cascading**: Nested optionality support
- **Default Values**: Applied when optional groups missing

### Sequence Satisfaction
- **Universal Rule**: ALL elements must be satisfied
- **Exception**: SubSequences are optional
- **Boundaries**: Literals provide safe separation

## Performance Optimizations

### Heuristic System
- Pre-filters inputs using pattern analysis
- Checks string length constraints
- Validates prefix patterns
- Reduces unnecessary regex operations

### Compilation Caching
- CompiledPattern objects are immutable
- Support serialization (dehydrate/hydrate)
- Recommended for production caching

### Memory Management  
- Lazy loading of compiler instances
- Registry pattern for type/constraint reuse
- Immutable compiled patterns

## Extension Points

### Custom Types
1. Implement `TypeInterface`
2. Register with `TypeRegistry`
3. Provide pattern generation and casting logic

### Custom Constraints
1. Implement appropriate constraint interface
2. Register with `ConstraintRegistry`  
3. Define validation logic

### Custom Validators
1. Implement `ValidatorInterface`
2. Add to validation pipeline
3. Define validation rules

### Custom Nodes
1. Implement `AstNodeInterface`
2. Add parser support
3. Define compilation behavior

## Error Handling

### Exception Hierarchy
```
PatternEngineInvalidArgumentException
├── PatternCompilationException
├── PatternRuntimeException  
├── PatternSyntaxException
├── PatternValidationException
└── TypeSystemException
```

### Error Categories
- **Syntax Errors**: Invalid pattern DSL
- **Validation Errors**: Rule violations  
- **Type Errors**: Invalid type casting
- **Runtime Errors**: Execution failures

## Testing Strategy

### Test Coverage
- **Unit Tests**: Component isolation
- **Integration Tests**: End-to-end workflows  
- **Data Providers**: Comprehensive test cases
- **Edge Cases**: Boundary conditions

### Test Categories
- Pattern compilation and execution
- Type casting and validation
- Constraint enforcement  
- Optional section logic
- Error condition handling
- Performance benchmarks

## Development Guidelines

### Code Standards
- PHP 8.1+ type declarations
- Interface-based design
- Immutable data structures  
- Comprehensive error handling

### Design Patterns
- Factory Pattern: CompiledPatternFactory
- Registry Pattern: TypeRegistry, ConstraintRegistry
- Strategy Pattern: Validation pipeline
- Facade Pattern: TypedPatternEngine

### Performance Considerations
- Minimize regex complexity
- Cache compiled patterns
- Use heuristic pre-filtering
- Avoid deep recursion in AST