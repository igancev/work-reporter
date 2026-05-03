# Contributing to Work Reporter

Thank you for your interest in contributing! This document describes the rules
and expectations for any code change submitted to this repository.

## How to Contribute

1. **Fork** the repository.
2. Create a **feature branch** from `main`:
   ```bash
   git checkout -b feature/your-change
   ```
3. Implement your change following the rules below.
4. Open a **Pull Request** against `main` with a clear description of *what*
   and *why*.

---

## Mandatory Rules

### 1. Code must be covered by tests

Every proposed change **must be accompanied by tests** that prove the new or
modified behavior works as intended.

Tests are not a formality — they are the primary evidence that the code works.
Without them, every future change to the codebase would require **manual
re-verification of your feature**, which is unsustainable and unacceptable for
this project.

- New features → add tests covering the new behavior.
- Bug fixes → add a regression test that fails without the fix and passes with it.
- Refactoring → existing tests must continue to pass; add new ones if behavior
  boundaries shift.

Tests live under `tests/` and mirror the structure of `src/`.
Follow the conventions described in [CLAUDE.md](CLAUDE.md) (AAA pattern,
`#[CoversClass]`, `assertSame()`, etc.).

### 2. All CI quality checks must pass

A Pull Request will not be merged until **every check in the CI pipeline
passes**. The current set of checks (see
[`.github/workflows/quality-checks.yaml`](.github/workflows/quality-checks.yaml)
for the authoritative, up-to-date list) includes:

| Check              | Local command          | Purpose                          |
|--------------------|------------------------|----------------------------------|
| Code style (PHPCS) | `composer cs`          | PSR-12 compliance                |
| Static analysis    | `composer stat-analyze`| PHPStan checks                   |
| Unit tests         | `composer unit`        | Fast, isolated unit tests        |
| Functional tests   | `composer functional`  | End-to-end / integration tests   |

> ⚠️ The list above may become outdated. **Always treat the CI workflow as the
> source of truth** — if CI runs a check, your PR must pass it.

You can run all local checks at once:

```bash
make check-all
```

This executes code style, static analysis, and unit tests locally before you push.

---

## Pull Request Checklist

Before opening a PR, please verify:

- [ ] New / changed behavior is covered by tests.
- [ ] `make check-all` passes locally.
- [ ] Functional tests pass: `composer functional`.
- [ ] The PR description explains the motivation and scope of the change.

---

## Coding Standards

- PHP 8.4+ with `declare(strict_types=1);`
- PSR-12 (max line length 120, 4-space indentation)
- Strict typed properties and return types; `readonly` for value objects
- Avoid `null` unless it represents a meaningful optional state
- Use domain exceptions (`SourceException`, `DestinationException`)

For the full list of conventions, see [CLAUDE.md](CLAUDE.md).