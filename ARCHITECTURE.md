# Architecture: phpstan-doctrine

## Purpose

A PHPStan extension for Doctrine ORM, DBAL, and ODM that adds static analysis capabilities:
DQL query validation, QueryBuilder type inference, magic repository method recognition,
entity column/relation type checking, and database driver-aware expression type resolution.

## Directory Structure

```
src/
  Classes/            # Forbidden class name extensions (Doctrine proxy detection)
  Doctrine/           # Core Doctrine integration: driver detection, metadata loading
  PhpDoc/             # PHPDoc type node resolver extensions
  Reflection/         # Class reflection extensions (repository methods, Selectable)
  Rules/
    Doctrine/ORM/     # ORM-specific rules (entity validation, DQL checks, finals)
    Gedmo/            # Gedmo doctrine-extensions support
  Stubs/              # Stub file loader for runtime stubs
  Type/Doctrine/
    Collection/       # Collection type narrowing
    DBAL/             # DBAL QueryBuilder and Result types
    Descriptors/      # Doctrine column type → PHPStan type mappings (28 descriptors)
    Query/            # DQL Query result type walker and result type inference
    QueryBuilder/     # ORM QueryBuilder type tracking and DQL accumulation
stubs/
  Collections/        # Stubs for Doctrine Collection and Selectable interfaces
  DBAL/               # Stubs for DBAL types, cache, exceptions
  ORM/                # Stubs for ORM Query, QueryBuilder, Mapping
  Persistence/        # Stubs for persistence-layer interfaces
  runtime/Enum/       # PHP 8.1 enum polyfill stubs
compatibility/        # Shims for multiple Doctrine version support (ORM 2/3, DBAL 3/4)
tests/
  DoctrineIntegration/  # Integration tests (ORM, ODM, Persistence)
  Platform/             # Database platform tests (MySQL, PostgreSQL, SQLite)
  Reflection/           # Reflection extension tests
  Rules/                # Rule tests (entity validation, dead code, properties)
  Type/                 # Type inference tests
```

## Key Design Decisions

### Stubs Over Reflection

Many Doctrine classes change their return types across major versions. PHPStan stubs
in `stubs/` override native reflection, providing stable type signatures regardless of
which Doctrine version is installed. This avoids conditional reflection logic in extensions.

### Type Descriptor Registry

`Type/Doctrine/Descriptors/` contains one descriptor per Doctrine DBAL column type
(e.g., `StringType`, `IntegerType`, `JsonType`). Each descriptor maps between the DBAL
column type and the corresponding PHPStan type. New custom Doctrine types can be supported
by implementing `DoctrineTypeDescriptor` and registering it in `doctrine.neon`.

### DQL Analysis Pipeline

QueryBuilder calls are tracked through a type extension that accumulates DQL parts at
analysis time. When `getQuery()` is called, the accumulated DQL is parsed and validated
against the entity metadata, reporting errors at the call site with accurate line numbers.

### Magic Repository Methods

The reflection extension in `Reflection/` dynamically synthesizes method reflections for
`findBy*`, `findOneBy*`, and `countBy*` patterns using entity field metadata, allowing
PHPStan to infer return types without hand-written stubs.

## Extension Points

- **Custom Doctrine type descriptors** — implement `DoctrineTypeDescriptor` and register
  with the `phpstan.doctrine.typeDescriptor` service tag in `doctrine.neon`.
- **Object manager loaders** — implement `ObjectMetadataResolver` to provide entity
  metadata loading via a custom bootstrap path.
- **DQL custom functions** — registered via Doctrine's standard function registration;
  PHPStan picks them up through the metadata resolver.

## Dependency Flow

```
doctrine.neon
  └─ ObjectMetadataResolver (wraps Doctrine's entity metadata factory)
       ├─ Type extensions (getRepository, find, getResult, etc.)
       ├─ DQL validation rules
       └─ Type descriptor registry
            └─ DoctrineTypeDescriptor[] (built-in + custom)
```
