# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Development Commands

- **Build/Install**: `composer install`
- **Run CLI**: `bin/console work:report --from YYYY-MM-DD --to YYYY-MM-DD`
- **Linting**: `make cs` (check), `make cs-fix` (fix)
- **Static Analysis**: `make stat-analyze`
- **Tests**:
  - Run all: `make unit` or `vendor/bin/phpunit`
  - Run single test: `vendor/bin/phpunit tests/Path/To/Test.php`
  - Run single test method: `vendor/bin/phpunit --filter testMethodName tests/Path/To/Test.php`
- **Mutation Testing**: `make mutation` (Infection, manual; not part of `make check-all`). CI enforces `min-msi=70` and `min-covered-msi=70`; raise these thresholds as test quality improves.
- **Safety**: After any logical changes, always run `make check-all` to ensure code quality.

## Code Style & Conventions

- **PHP Version**: 8.4+ required with `declare(strict_types=1);`
- **Coding Standard**: PSR12 (Max line 120, 4 spaces)
- **Architecture**: Source-Destination pattern (Input → `TimeEntry` → Output)
- **Type Declarations**: Always use strict return types and typed properties. Use `readonly` for value objects.
- **Null Usage**: Avoid `null` unless it represents a meaningful optional state. Prefer sensible defaults over nullable types.
- **Use Statements**: Sort alphabetically: native first, then external, then local. No unused uses.
- **Error Handling**: Use domain exceptions (`SourceException`, `DestinationException`).
- **Class Structure**: 1. `declare`, 2. Namespace, 3. Use, 4. Constants, 5. Properties, 6. Constructor, 7. Methods.

## Testing Guidelines

- **Location**: `tests/` mirrors `src/`.
- **Structure**: Use AAA Pattern (`// Arrange`, `// Act`, `// Assert`).
- **Attributes**: Use `#[CoversClass]` and `#[DataProvider]`.
- **Assertions**: Use `assertSame()` (strict) over `assertEquals()`.

## Architecture & Structure

The application is a CLI tool built with Symfony Console that follows a Source-Destination pattern for reporting work time.

### Core Components
- `src/WorkReportCommand.php`: The main entry point. Orchestrates fetching from a source, filtering/grouping, and sending to a destination.
- `src/TimeEntry.php`: Value object representing a single worklog entry.
- `src/Duration.php`: Utility for parsing and converting time (e.g., "1h 30m" to minutes).

### Interfaces
- `src/Source/TimeEntriesSource.php`: Interface for time tracking data providers.
    - Implementations: `PlainJsonTimeEntriesSource`, `SuperProductivitySyncSource`.
- `src/Destination/Destination.php`: Interface for task tracker export.
    - Implementation: `YouTrackDestination` (uses `amphp` for asynchronous HTTP requests).

### Key Patterns
- **Async I/O**: `YouTrackDestination` uses `amphp/v3` for concurrent API calls (projects, work item types, and reporting).
- **Filtering & Grouping**: `TimeEntryCollection` handles logic for merging similar entries or filtering by minimum duration.
- **Error Handling**: Custom exceptions are used (`SourceException`, `DestinationException`) to wrap implementation-specific errors.
