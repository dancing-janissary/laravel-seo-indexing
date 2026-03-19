# Contributing

Thank you for considering contributing to `laravel-seo-indexing`!

## Workflow

1. Fork the repository
2. Create a feature branch from `develop`: `git checkout -b feature/your-feature`
3. Write your code and tests
4. Ensure all tests pass: `composer test`
5. Commit using Conventional Commits: `feat:`, `fix:`, `docs:`, etc.
6. Push and open a Pull Request against `develop`

## Local Setup
```bash
git clone https://github.com/dancing-janissary/laravel-seo-indexing
cd laravel-seo-indexing
composer install
```

## Running Tests
```bash
composer test
```

## Branching

- `main` — stable releases only
- `develop` — integration branch, all PRs target this
- `feature/*` — new features
- `fix/*` — bug fixes

## Coding Standards

- PSR-12
- PHP 8.2+ features encouraged (readonly properties, enums, match, etc.)
- Every new feature needs a test