# 🧪 Tests Folder

## Purpose

Quality assurance through unit, integration, and end-to-end testing.

## Structure

```
tests/
├── unit/           # Unit tests (PHPUnit)
├── integration/    # Integration tests
└── e2e/           # End-to-end tests
```

## Framework

- **PHPUnit** for PHP testing
- **Jest** for JavaScript testing
- **Selenium** for browser automation

## Running Tests

```bash
# All tests
./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit tests/unit

# Integration tests
./vendor/bin/phpunit tests/integration

# E2E tests
npm run test:e2e
```

## Test Coverage

- Target: 80%+ code coverage
- Critical paths: 100% coverage
- Generate reports: `phpunit --coverage-html coverage/`

## Best Practices

- One assertion per test when possible
- Use descriptive test names
- Mock external dependencies
- Clean up test data

**Last Updated:** November 24, 2025
