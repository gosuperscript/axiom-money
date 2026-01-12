# Contributing to Axiom Money

Thank you for your interest in contributing to Axiom Money! We welcome contributions from the community.

## Getting Started

1. Fork the repository
2. Clone your fork locally
3. Create a new branch for your feature or bugfix
4. Make your changes
5. Test your changes thoroughly
6. Submit a pull request

## Development Setup

### Prerequisites

- PHP 8.4 or higher
- Composer
- ext-intl extension

### Installation

```bash
# Clone your fork
git clone https://github.com/YOUR_USERNAME/axiom-money.git
cd axiom-money

# Install dependencies
composer install
```

## Code Quality Standards

This project maintains high code quality standards:

- **100% code coverage** requirement for all code
- **PHPStan** static analysis (must pass)
- **Laravel Pint** code style (must pass)
- **Infection** mutation testing (must pass)

### Running Tests

```bash
# Run all tests
composer test

# Run unit tests only
composer test:unit

# Run type checking
composer test:types

# Run mutation testing
composer test:infection
```

### Code Style

We use Laravel Pint for code formatting:

```bash
vendor/bin/pint
```

Please run Pint before committing your changes.

## Pull Request Process

1. Ensure all tests pass and code coverage remains at 100%
2. Update documentation if you're adding new features
3. Add tests for any new functionality
4. Update the README.md if needed
5. Ensure your code follows the existing code style
6. Write a clear and descriptive pull request description

## Code Review

All submissions require review. We use GitHub pull requests for this purpose. Your pull request will be reviewed for:

- Code quality and style
- Test coverage
- Documentation
- Adherence to project standards

## Reporting Bugs

If you find a bug, please open an issue with:

- A clear title and description
- Steps to reproduce the bug
- Expected behavior
- Actual behavior
- Your environment (PHP version, OS, etc.)

## Feature Requests

We welcome feature requests! Please open an issue with:

- A clear title and description
- Use case for the feature
- How it would benefit the project

## Questions?

Feel free to open an issue for any questions about contributing.

## License

By contributing to Axiom Money, you agree that your contributions will be licensed under the MIT License.
