# Axiom Money

[![Tests](https://github.com/gosuperscript/axiom-money/workflows/Tests/badge.svg)](https://github.com/gosuperscript/axiom-money/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)

A monetary extension for [Axiom](https://github.com/gosuperscript/axiom), providing schema types, a parser, and operator rules for monetary values with strong type safety and currency validation.

## Features

- **Schema Types**: Type-safe monetary value handling with currency validation
- **Money Parser**: Parse monetary values from various string formats (e.g., "EUR 100", "£50.25")
- **Operator rules**: Addition, subtraction, comparison and equality between monies of the same currency, plus multiplication/division by a numeric scalar — resolved and type-checked at compile time, declared per currency by a `MoneyExtension`
- **Multiple Type Variants**:
  - `MonetaryType`: Standard monetary type with currency validation
  - `MinorMonetaryType`: Money from minor units (cents, pence, etc.)
  - `DynamicMonetaryType`: Flexible parsing that auto-detects currency (boundary type only)
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

Every money type projects into Axiom's shape algebra as an opaque `money` identity parameterized by its currency (`Money<'EUR'>`). `Money<'EUR'>` fills a `Money<'EUR' | 'USD'>` slot and shares no values with `Money<'USD'>` — all without a single core relation rule mentioning money.

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
```

`MinorMonetaryType` projects to the *same* opaque `money<currency>` shape as `MonetaryType`: the two differ only in how they read raw input at the boundary, and a value of either is the same `Money`, so both resolve the same operator rules.

### Dynamic Monetary Type

Automatically detect and parse currency from string:

```php
use Superscript\Axiom\Money\Types\DynamicMonetaryType;

$dynamicType = new DynamicMonetaryType();

$money = $dynamicType->coerce('USD 100')->unwrap()->unwrap();
// Result: Money object with USD 100
```

`DynamicMonetaryType` admits any currency at the boundary, so its currency is *not* statically known — it projects to an opaque `money` with **no** currency parameter. That makes it a coercion/boundary type only: it is deliberately not assignable to the currency-parameterized `Money<C>` the operator rules resolve for. Declare a concrete `MonetaryType` where you need arithmetic or comparison.

### Operator rules — the `MoneyExtension`

Money's typing is *parameterized* by currency, and a signature's return type is fixed, so the rules are declared by **enumeration over the host's configured currencies**. Compose the extension onto the core dialect and hand it to an expression; the compiler resolves and type-checks every operator, and the compiled `Program` runs what it resolved with no runtime dispatch.

```php
use Brick\Money\Money;
use Superscript\Axiom\Dialect;
use Superscript\Axiom\Expression;
use Superscript\Axiom\Money\MoneyExtension;
use Superscript\Axiom\Money\Types\MonetaryType;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\SymbolSource;

$dialect = Dialect::core()->with(new MoneyExtension(['GBP', 'USD', 'EUR']));

$expression = new Expression(
    new InfixExpression(new SymbolSource('a'), '+', new SymbolSource('b')),
    dialect: $dialect,
    declarations: ['a' => new MonetaryType(Currency::of('GBP')), 'b' => new MonetaryType(Currency::of('GBP'))],
);

$program = $expression->compile()->unwrap();
$program(['a' => Money::of(1, 'GBP'), 'b' => Money::of(2, 'GBP')])->unwrap()->unwrap(); // GBP 3.00
```

The rules, per configured currency:

- **`+` / `-`** — same currency in, same currency out.
- **`*` / `/`** — by a numeric scalar (multiplication on either side; division is `Money / number`). The calculation runs in Brick's exact rational domain and is **rounded back to the currency scale**, so the result is a `Money` of the same currency — each operator has one honest return type. The rounding mode is the extension's second constructor argument (default `RoundingMode::HALF_UP`). Division by zero is a *value-dependent* error, returned as an `Err`, not a compile-time refusal.
- **`<` / `<=` / `>` / `>=`** — ordering between two monies of the same currency → boolean.
- **`=` / `==` / `===` and `!=` / `!==`** — equality via Brick's amount-and-currency comparison. Core refuses equality on opaque operands, so the money package owns its own.

A **cross-currency** operation (`Money<'GBP'> + Money<'USD'>`) matches no rule and is refused at compile time with a named diagnostic — no program containing it can be compiled, let alone run.

> **Rounding note (breaking change):** previous versions returned an exact `Brick\Money\RationalMoney` from `*`/`/` and propagated it through `+`/`-` ("rational is contagious"). Under the typed model each operator has a fixed return type, so `*`/`/` now round to a `Money` at the currency scale using the extension's rounding mode. Chain in the rational domain yourself (via Brick) if you need to defer rounding.

### Monetary Intervals

Work with ranges of monetary values:

```php
use Brick\Money\Currency;
use Brick\Money\Money;
use Superscript\Axiom\Money\Types\MonetaryIntervalType;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\MonetaryInterval\IntervalNotation;

$intervalType = new MonetaryIntervalType(Currency::of('EUR'));

// Parse an interval from string notation
$interval = $intervalType->coerce('[100,200]')->unwrap()->unwrap();

// Or construct one directly
$interval = new MonetaryInterval(
    left: Money::of(100, 'EUR'),
    right: Money::of(200, 'EUR'),
    notation: IntervalNotation::Closed,
);

$intervalType->format($interval); // "[EUR 100.00, EUR 200.00]"
```

`MonetaryIntervalType` projects to an opaque `monetary-interval<currency>` shape. The `MoneyExtension` contributes, per currency, the comparison of a monetary interval against a money of that currency (`<`, `<=`, `>`, `>=` → boolean) and equality between two monetary intervals of the same currency (`=`/`==`/`===`, `!=`/`!==`).

```php
$dialect = Dialect::core()->with(new MoneyExtension(['EUR']));

$expression = new Expression(
    new InfixExpression(new SymbolSource('range'), '>', new SymbolSource('amount')),
    dialect: $dialect,
    declarations: [
        'range' => new MonetaryIntervalType(Currency::of('EUR')),
        'amount' => new MonetaryType(Currency::of('EUR')),
    ],
);
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

## Dependencies

This library builds on several excellent packages:

- [brick/money](https://github.com/brick/money): Robust money and currency library
- [gosuperscript/axiom](https://github.com/gosuperscript/axiom): The expression language it extends
- [superscript/interval](https://github.com/superscript/interval): Interval mathematics
- [superscript/monetary-interval](https://github.com/superscript/monetary-interval): Monetary interval support

## License

MIT License - see [LICENSE](LICENSE) file for details.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request. For major changes, please open an issue first to discuss what you would like to change.

Please make sure to update tests as appropriate and maintain the existing code quality standards.
