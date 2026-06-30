<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Operators;

use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Superscript\Axiom\Money\Operators\MoneyOverloader;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoneyOverloader::class)]
class MoneyOverloaderTest extends TestCase
{
    #[Test]
    #[DataProvider('calculations')]
    public function it_evaluates_calculations(mixed $left, string $operator, mixed $right, mixed $expected): void
    {
        $overloader = new MoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));

        $result = $overloader->evaluate(left: $left, right: $right, operator: $operator);
        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo($expected));
    }

    public static function calculations(): Generator
    {
        yield [Money::of(1, 'GBP'), '+', Money::of(2, 'GBP'), Money::of(3, 'GBP')];
        yield [Money::of(5, 'EUR'), '-', Money::of(2, 'EUR'), Money::of(3, 'EUR')];
        yield [Money::of(10, 'USD'), '*', 2, Money::of(20, 'USD')];
        yield [2, '*', Money::of(10, 'USD'), Money::of(20, 'USD')];
        yield [Money::of(10, 'USD'), '*', '0.5', Money::of(5, 'USD')];
        yield [Money::of(20, 'JPY'), '/', 2, Money::of(10, 'JPY')];
    }

    #[Test]
    #[DataProvider('unsupportedCombinations')]
    public function it_does_not_support_money_to_money_multiplication_or_division(mixed $left, string $operator, mixed $right): void
    {
        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));
    }

    public static function unsupportedCombinations(): Generator
    {
        yield 'money * money' => [Money::of(10, 'USD'), '*', Money::of(2, 'USD')];
        yield 'money / money' => [Money::of(20, 'JPY'), '/', Money::of(2, 'JPY')];
        yield 'numeric / money' => [2, '/', Money::of(10, 'USD')];
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function it_evaluates_comparisons(Money $left, string $operator, Money $right, mixed $expected): void
    {
        $overloader = new MoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));

        $result = $overloader->evaluate(left: $left, right: $right, operator: $operator);
        $this->assertTrue($result->isOk());
        $this->assertSame($expected, $result->unwrap());
    }

    public static function comparisons(): Generator
    {
        yield [Money::of(1, 'GBP'), '==', Money::of(1, 'GBP'), true];
        yield [Money::of(1, 'GBP'), '!=', Money::of(2, 'GBP'), true];
        yield [Money::of(1, 'GBP'), '<', Money::of(2, 'GBP'), true];
        yield [Money::of(2, 'GBP'), '>', Money::of(1, 'GBP'), true];
        yield [Money::of(1, 'GBP'), '<=', Money::of(1, 'GBP'), true];
        yield [Money::of(2, 'GBP'), '>=', Money::of(1, 'GBP'), true];
    }

    #[Test]
    public function it_returns_rational_money_from_multiplication_and_division(): void
    {
        $overloader = new MoneyOverloader();

        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(Money::of(10, 'USD'), 2, '*')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(2, Money::of(10, 'USD'), '*')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(Money::of(20, 'USD'), 2, '/')->unwrap());
    }

    #[Test]
    public function it_keeps_money_addition_as_money_but_promotes_when_rational_is_involved(): void
    {
        $overloader = new MoneyOverloader();

        // Money + Money stays Money
        $this->assertInstanceOf(Money::class, $overloader->evaluate(Money::of(1, 'USD'), Money::of(2, 'USD'), '+')->unwrap());

        // Rational is contagious: Money + RationalMoney (either order) -> RationalMoney
        $rational = $overloader->evaluate(Money::of(10, 'USD'), 3, '/')->unwrap();
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(Money::of(1, 'USD'), $rational, '+')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate($rational, Money::of(1, 'USD'), '-')->unwrap());
    }

    #[Test]
    public function it_keeps_division_exact_via_rational_money(): void
    {
        $overloader = new MoneyOverloader();

        // 10 USD / 3 would throw or round for a Money-based impl; RationalMoney keeps it exact,
        // so multiplying back by 3 round-trips to exactly 10 USD.
        $third = $overloader->evaluate(Money::of(10, 'USD'), 3, '/');
        $this->assertTrue($third->isOk());

        $roundTrip = $overloader->evaluate($third->unwrap(), 3, '*');
        $this->assertTrue($roundTrip->isOk());
        $this->assertTrue($roundTrip->unwrap()->isEqualTo(Money::of(10, 'USD')));
    }

    #[Test]
    public function it_chains_rational_results_into_further_expressions(): void
    {
        $overloader = new MoneyOverloader();

        // (10 USD / 3) is a RationalMoney; it must compose with + and < (AbstractMoney broadening).
        $rational = $overloader->evaluate(Money::of(10, 'USD'), 3, '/')->unwrap();

        $this->assertTrue($overloader->supportsOverloading($rational, Money::of(1, 'USD'), '+'));
        $this->assertTrue($overloader->evaluate($rational, Money::of(1, 'USD'), '+')->isOk());

        $this->assertTrue($overloader->supportsOverloading($rational, Money::of(10, 'USD'), '<'));
        $this->assertSame(true, $overloader->evaluate($rational, Money::of(10, 'USD'), '<')->unwrap());
    }

    #[Test]
    public function it_preserves_high_precision_numeric_string_scalars_exactly(): void
    {
        $overloader = new MoneyOverloader();

        // An 18-digit multiplier exceeds float precision (~14 sig digits). Routing it through a
        // float would silently truncate it; passing the numeric string straight to Brick keeps it
        // exact, which is the whole point of returning a RationalMoney.
        $result = $overloader->evaluate(Money::of(1, 'USD'), '0.123456789012345678', '*');

        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo(RationalMoney::of('0.123456789012345678', 'USD')));
    }

    #[Test]
    public function it_accepts_whitespace_padded_numeric_string_scalars(): void
    {
        $overloader = new MoneyOverloader();

        // is_numeric() accepts surrounding whitespace, so supportsOverloading claims it; the scalar
        // is trimmed before reaching Brick (which would otherwise reject it) so evaluate agrees.
        $this->assertTrue($overloader->supportsOverloading(Money::of(10, 'USD'), ' 2 ', '*'));

        $result = $overloader->evaluate(Money::of(10, 'USD'), ' 2 ', '*');
        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo(Money::of(20, 'USD')));
    }

    #[Test]
    #[DataProvider('crossCurrencyComparisons')]
    public function it_returns_err_when_comparing_or_combining_different_currencies(string $operator): void
    {
        $overloader = new MoneyOverloader();

        // Brick treats cross-currency comparison/combination as undefined and throws; we surface
        // that as an Err rather than silently answering false/true. This is intentional and pinned.
        $result = $overloader->evaluate(Money::of(1, 'USD'), Money::of(1, 'EUR'), $operator);

        $this->assertTrue($result->isErr());
    }

    public static function crossCurrencyComparisons(): Generator
    {
        yield 'equal' => ['=='];
        yield 'not equal' => ['!='];
        yield 'less than' => ['<'];
        yield 'add' => ['+'];
    }

    #[Test]
    public function it_returns_err_when_dividing_by_zero(): void
    {
        $overloader = new MoneyOverloader();

        // Divide-by-zero is a runtime value error, not a type error: supportsOverloading still
        // claims it (the shape is valid), and evaluate surfaces the failure as an Err.
        $this->assertTrue($overloader->supportsOverloading(Money::of(10, 'USD'), 0, '/'));
        $this->assertTrue($overloader->evaluate(Money::of(10, 'USD'), 0, '/')->isErr());
    }

    #[Test]
    public function it_returns_err_for_unsupported_operator(): void
    {
        $overloader = new MoneyOverloader();
        $result = $overloader->evaluate(Money::of(1, 'EUR'), Money::of(2, 'EUR'), '%');

        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(\InvalidArgumentException::class, $result->unwrapErr());
        $this->assertSame('Unsupported operator: %', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_for_unsupported_left_side(): void
    {
        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading('not a money', Money::of(1, 'EUR'), '+'));

        $result = $overloader->evaluate('not a money', Money::of(1, 'EUR'), '+');
        $this->assertTrue($result->isErr());
    }

    #[Test]
    public function it_returns_err_for_unsupported_right_side(): void
    {
        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading(Money::of(1, 'EUR'), 'not a money', '+'));

        $result = $overloader->evaluate(Money::of(1, 'EUR'), 'not a money', '+');
        $this->assertTrue($result->isErr());
    }
}
