# Contributing to Saccharine BPMN

Thank you for your interest in contributing to **Saccharine BPMN**! Whether you are reporting an edge-case bug, proposing an architectural RFC, or submitting code for a new BPMN 2.0 element handler, your contributions help make native process orchestration accessible to the Laravel ecosystem.

This document outlines the development workflow, testing standards, and pull request lifecycle for this repository.

---

## Architectural Focus & Roadmap

Before proposing major new features, review our current implementation boundaries:

* **Supported Core Patterns**: Exclusive Gateways (via Symfony Expression Language), Parallel Gateways (AND splits/joins via concurrent child coroutines), User Tasks (stateful signal suspension), Service Tasks (Activity classes), Message Start Events (Eloquent triggers), and Boundary Error/Timer events.

* **Active Roadmap / High-Priority Areas**:
* Multi-instance activity loops (sequential and parallel).
* Complex event sub-processes and compensation events.
* Dynamic form schema bindings (e.g., `form-js`) for User Tasks.
* Workflow version migration tooling for in-flight instances.

If you are planning an architectural change or a new BPMN node handler, please **open an Issue or RFC first** so we can coordinate design direction before you write code.

---

## Local Development Setup

Saccharine BPMN is developed as a standard Composer package backed by Orchestra Testbench. You do not need to configure external database servers (MySQL/PostgreSQL) or run Docker containers to execute tests.

### 1. Prerequisites

* **PHP 8.3+**
* **Composer 2.x**
* **Node.js 18+** & **npm** (for bundling Vite frontend assets)
* **SQLite PHP Extension** (`pdo_sqlite` for in-memory Testbench execution)

### 2. Installation

Clone the repository and install dependencies:

```bash
git clone https://github.com/saccharine-app/bpmn.git
cd bpmn

# Install PHP dependencies
composer install

# Install JS dependencies
npm install
```

### 3. Compiling Frontend Assets

The package bundles `bpmn-js`, `bpmn-properties-panel`, and custom canvas styles via Vite. Source assets live under `resources/js/` and compile directly into `public/`:

```bash
# Compile and minify assets for production (emits saccharine-bpmn.js and saccharine-bpmn.css)
npm run build

# Watch for asset changes during active frontend development
npm run dev
```

Note: Whenever you modify frontend files under `resources/js/` or styling rules, ensure `npm run build` is run and committed prior to opening a pull request.

---

## Testing Guidelines

We use [Pest PHP](https://pestphp.com/) along with Orchestra Testbench to simulate an isolated Laravel environment.

All migrations (including core `durable-workflow` tables and internal graph schema) are loaded in-memory (`:memory:`) with a deterministic queue driver during test runs.

### Running the Test Suite

```bash
# Run the complete test suite
./vendor/bin/pest

# Run a specific test suite
./vendor/bin/pest tests/Feature/Engine/BpmnInterpreterTest.php

# Filter by a specific test name
./vendor/bin/pest --filter="evaluates expression logic"
```

### Writing Tests for New Handlers

When implementing a new BPMN shape or extending an existing handler:

1. **Determinism**: Because execution runs through `durable-workflow`, handlers must remain deterministic. Any external database mutations or token updates must be wrapped in `WorkflowStub::sideEffect()`.

2. **Generator Return Types**: Handlers must implement `BpmnNodeHandlerInterface` and return a PHP Generator (`\Generator`), yielding execution back to the master engine loop.

3. **Pest Expectations**: Add corresponding feature tests under `tests/Feature/Nodes/` to verify that the pointer advances to the expected target node and mutates the `$userData` payload accurately.

---

## Coding Standards & Style

We follow the standard Laravel code style and PSR-12 conventions:

* **Strict Typing**: Use strict typing and typed properties/returns across all services, contracts, and handler classes.

* **Formatting**: Ensure your code is formatted before committing:
```bash
./vendor/bin/pint
```

* **Git Commit Conventions**: We recommend following [Conventional Commits](https://www.conventionalcommits.org/):
* `feat:` A new feature or BPMN node handler.
* `fix:` A bug fix in parsing, routing, or execution.
* `docs:` Documentation improvements.
* `refactor:` Code restructuring without behavioral changes.
* `test:` Adding or updating Pest test coverage.

---

## Submitting Pull Requests

1. **Fork the Repository** and create a descriptive branch:
```bash
git checkout -b feat/multi-instance-loop
```

2. **Write Focused Commits**: Keep changes scoped to the problem you are solving.
3. **Verify Tests**: Ensure `./vendor/bin/pest` passes with zero regressions.
4. **Build Assets**: If editing files inside `resources/js/` or `resources/views/`, ensure `npm run build` has run cleanly.
5. **Open a PR against the `main` branch**:
* Clearly explain what problem the PR solves.
* Reference any relevant Issue numbers (e.g., `Closes #12`).
* Include sample BPMN XML snippets if the PR introduces support for a new XML node type or attribute.

---

## Reporting Issues & Requesting Features

* **Bug Reports**: Please include the minimum valid BPMN XML snippet that triggers the failure, your PHP/Laravel versions, and the exact exception stack trace from `storage/logs/laravel.log`.

* **Security Vulnerabilities**: If you discover a security issue (such as an expression sandbox escape in `Symfony\Component\ExpressionLanguage`), please email the maintainers directly rather than opening a public issue.