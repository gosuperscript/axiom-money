# Axiom Money

[![Tests](https://github.com/gosuperscript/axiom-money/workflows/Tests/badge.svg)](https://github.com/gosuperscript/axiom-money/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A monetary extension for [Axiom](https://github.com/gosuperscript/axiom), providing schema types, parsers, and operators for monetary values with strong type safety and currency validation.

**Note:** This library extends [Axiom](https://github.com/gosuperscript/axiom) (built on [gosuperscript/schema](https://github.com/gosuperscript/schema)) with monetary value support.

## Features

- **Schema Types**: Type-safe monetary value handling with currency validation
- **Money Parser**: Parse monetary values from various string formats (e.g., "EUR 100", "£50.25")
- **Operator Overloading**: Mathematical operations on Money objects — addition, subtraction, and comparisons between monies, plus multiplication/division by a numeric scalar (returning an exact `RationalMoney`)
- **Multiple Type Variants**:
  - `MonetaryType`: Standard monetary type with currency validation
  - `MinorMonetaryType`: Money from minor units (cents, pence, etc.)
  - `DynamicMonetaryType`: Flexible parsing that auto-detects currency
  - `MonetaryIntervalType`: Intervals of monetary values
- **Monetary Intervals**: Support for ranges of monetary values

## Requirements

- PHP 8.4 or higher
- ext-intl extension

## Installation

Install via Composer:

```bash
composer require gosuperscript/axiom-money
```

## Usage

### Basic Money Types

```php
use Brick\Money\Currency;
use Brick\Money\Money;
use Superscript\Axiom\Money\Types\MonetaryType;

// Create a monetary type for EUR
$eurType = new MonetaryType(Currency::of('EUR'));

// Coerce values to Money objects
$money = $eurType->coerce('100.50')->unwrap()->unwrap();
// Result: Money object with EUR 100.50

// Assert existing Money objects
$result = $eurType->assert(Money::of(50, 'EUR'));
// Result: Ok(Some(Money))

// Format money for display
$formatted = $eurType->format(Money::of(100.50, 'EUR'));
// Result: "€100.50"
```

### Money Parser

Parse money from various string formats:

```php
use Superscript\Axiom\Money\MoneyParser;
use Brick\Money\Money;

// Parse from "CURRENCY AMOUNT" format
$result = MoneyParser::parse('EUR 100');
$money = $result->unwrap(); // Money object: EUR 100

// Parse from currency symbol format
$result = MoneyParser::parse('£50.25');
$money = $result->unwrap(); // Money object: GBP 50.25

// Parse from "CURRENCY AMOUNT" format
$result = MoneyParser::parse('USD 1000.50');
$money = $result->unwrap(); // Money object: USD 1000.50

// Already a Money object? Just returns it
$existing = Money::of(100, 'EUR');
$result = MoneyParser::parse($existing);
$money = $result->unwrap(); // Same Money object
```

### Minor Units

Work with minor currency units (cents, pence, etc.):

```php
use Brick\Money\Currency;
use Superscript\Axiom\Money\Types\MinorMonetaryType;

$gbpType = new MinorMonetaryType(Currency::of('GBP'));

// Coerce from minor units (100 pence = £1.00)
$money = $gbpType->coerce(100)->unwrap()->unwrap();
// Result: Money object with GBP 1.00

// Coerce from string of minor units
$money = $gbpType->coerce('2550')->unwrap()->unwrap();
// Result: Money object with GBP 25.50
```

### Dynamic Monetary Type

Automatically detect and parse currency from string:

```php
use Superscript\Axiom\Money\Types\DynamicMonetaryType;

$dynamicType = new DynamicMonetaryType();

// Automatically detects currency
$money = $dynamicType->coerce('USD 100')->unwrap()->unwrap();
// Result: Money object with USD 100

$money = $dynamicType->coerce('€50.25')->unwrap()->unwrap();
// Result: Money object with EUR 50.25
```

### Money Operations

Perform mathematical operations on Money objects:

```php
use Brick\Money\Money;
use Superscript\Axiom\Money\Operators\MonetaryOverloader;

$overloader = new MonetaryOverloader();

$a = Money::of(100, 'EUR');
$b = Money::of(50, 'EUR');

// Addition
$sum = $overloader->evaluate($a, $b, '+');
// Result: EUR 150.00

// Subtraction
$diff = $overloader->evaluate($a, $b, '-');
// Result: EUR 50.00

// Multiplication and division require ONE numeric operand (multiplying or dividing two
// monies is dimensionally meaningless and is not supported). The numeric operand may be
// on either side for multiplication; division must be Money / number.
//
// To preserve precision, `*` and `/` return an exact Brick\Money\RationalMoney.
$product = $overloader->evaluate($a, 3, '*');        // RationalMoney, exactly 300
$product = $overloader->evaluate(3, $a, '*');        // commutative
$quotient = $overloader->evaluate($a, 3, '/');       // RationalMoney, exactly 100/3 (no rounding)

// Addition/subtraction: Money + Money stays Money; if either operand is a RationalMoney the
// result is a RationalMoney ("rational is contagious"), so chained arithmetic never rounds mid-way.
$sum = $overloader->evaluate($a, $b, '+');           // Money: EUR 150.00

// Comparisons work across Money and RationalMoney, returning bool.
$isEqual = $overloader->evaluate($a, $b, '==');     // false
$isLess = $overloader->evaluate($b, $a, '<');       // true
$isGreater = $overloader->evaluate($a, $b, '>');    // true
```

A `RationalMoney` becomes a concrete `Money` at the **type boundary**: when it lands in a
`MonetaryType`, `MinorMonetaryType`, or `DynamicMonetaryType` field, `coerce()` rounds it to the
currency scale — the rounding mode is configurable on the type and defaults to
`RoundingMode::HALF_UP`, so a `RationalMoney` never leaves the type system. If you consume a
`*`/`/` result directly (outside a type), collapse it yourself:

```php
use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;

$money = $quotient->unwrap()->to(new DefaultContext(), RoundingMode::HALF_UP); // EUR 33.33
```

> **Note:** `MonetaryIntervalOverloader` compares an interval against a `Money` only. A
> `RationalMoney` produced by `*`/`/` cannot be compared against an interval directly —
> collapse it to a `Money` first.

### Allocation

`Allocation` proportionally allocates or equally splits a `Money`, delegating to brick/money's
exact minor-unit algorithm: any remainder is distributed over the earliest parts, so the parts
always sum to the original amount — no penny drift.

```php
use Brick\Money\Money;
use Superscript\Axiom\Money\Allocation;

Allocation::allocate(Money::of('25.00', 'GBP'), 5000, 4000)->unwrap(); // [GBP 13.89, GBP 11.11]
Allocation::split(Money::of('100.00', 'GBP'), 3)->unwrap();            // [GBP 33.34, GBP 33.33, GBP 33.33]
```

Both return a `Result`; invalid input (no ratios, all-zero ratios, a negative ratio, or fewer
than one part) is an `Err(InvalidArgumentException)`.

### Monetary Intervals

Work with ranges of monetary values:

```php
use Brick\Money\Currency;
use Brick\Money\Money;
use Superscript\Axiom\Money\Types\MonetaryIntervalType;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\MonetaryInterval\IntervalNotation;

$intervalType = new MonetaryIntervalType(Currency::of('EUR'));

// Parse interval from string notation
$interval = $intervalType->coerce('[100,200]')->unwrap()->unwrap();
// Result: MonetaryInterval from EUR 100 to EUR 200 (inclusive)

// Create MonetaryInterval directly
$interval = new MonetaryInterval(
    left: Money::of(100, 'EUR'),
    right: Money::of(200, 'EUR'),
    notation: IntervalNotation::CLOSED
);

// Format interval
$formatted = $intervalType->format($interval);
// Result: "[EUR 100.00, EUR 200.00]"
```

### Monetary Interval Operations

```php
use Superscript\Axiom\Money\Operators\MonetaryIntervalOverloader;

$overloader = new MonetaryIntervalOverloader();

$interval1 = new MonetaryInterval(
    left: Money::of(100, 'EUR'),
    right: Money::of(200, 'EUR'),
    notation: IntervalNotation::CLOSED
);

$interval2 = new MonetaryInterval(
    left: Money::of(150, 'EUR'),
    right: Money::of(250, 'EUR'),
    notation: IntervalNotation::CLOSED
);

// Check if intervals overlap
$overlaps = $overloader->evaluate($interval1, $interval2, '&&');
// Result: true (they overlap from EUR 150 to EUR 200)

// Union of intervals
$union = $overloader->evaluate($interval1, $interval2, '||');
// Result: MonetaryInterval from EUR 100 to EUR 250
```

## Development

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

### Code Quality

The project enforces 100% code coverage and uses:
- **PHPUnit**: Unit testing
- **PHPStan**: Static analysis
- **Laravel Pint**: Code style
- **Infection**: Mutation testing

### Linting

```bash
vendor/bin/pint
```

## Dependencies

This library builds on several excellent packages:

- [brick/money](https://github.com/brick/money): Robust money and currency library
- [gosuperscript/schema](https://github.com/gosuperscript/schema): Schema validation framework
- [superscript/interval](https://github.com/superscript/interval): Interval mathematics
- [superscript/monetary-interval](https://github.com/superscript/monetary-interval): Monetary interval support

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate and maintain the existing code quality standards.
