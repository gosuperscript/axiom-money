<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Operators;

use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Money\Operators\AbstractMoneyOverloader;
use Superscript\Axiom\Money\Operators\RationalMoneyOverloader;

#[CoversClass(RationalMoneyOverloader::class)]
#[CoversClass(AbstractMoneyOverloader::class)]
class RationalMoneyOverloaderTest extends TestCase
{
    #[Test]
    #[DataProvider('calculations')]
    public function it_evaluates_calculations(mixed $left, string $operator, mixed $right, Money $expected): void
    {
        $overloader = new RationalMoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));

        $result = $overloader->evaluate(left: $left, right: $right, operator: $operator);
        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo($expected));
    }

    public static function calculations(): Generator
    {
        // "Rational is contagious": once a RationalMoney is involved, +/- stay rational.
        yield 'rational + money' => [RationalMoney::of('2.5', 'EUR'), '+', Money::of(1, 'EUR'), Money::of('3.5', 'EUR')];
        yield 'money + rational' => [Money::of(1, 'EUR'), '+', RationalMoney::of('2.5', 'EUR'), Money::of('3.5', 'EUR')];
        yield 'rational + rational' => [RationalMoney::of('2.5', 'EUR'), '+', RationalMoney::of('1.5', 'EUR'), Money::of(4, 'EUR')];
        yield 'rational - money' => [RationalMoney::of('2.5', 'EUR'), '-', Money::of(1, 'EUR'), Money::of('1.5', 'EUR')];
        yield 'rational * numeric' => [RationalMoney::of('2.5', 'EUR'), '*', 2, Money::of(5, 'EUR')];
        yield 'numeric * rational' => [2, '*', RationalMoney::of('2.5', 'EUR'), Money::of(5, 'EUR')];
        yield 'rational / numeric' => [RationalMoney::of('2.5', 'EUR'), '/', 2, Money::of('1.25', 'EUR')];
    }

    #[Test]
    public function it_keeps_arithmetic_results_in_the_rational_domain(): void
    {
        $overloader = new RationalMoneyOverloader();
        $rational = RationalMoney::of('2.5', 'EUR');

        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate($rational, Money::of(1, 'EUR'), '+')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate($rational, Money::of(1, 'EUR'), '-')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate($rational, 2, '*')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate($rational, 2, '/')->unwrap());
    }

    #[Test]
    public function it_keeps_division_exact_across_a_chain(): void
    {
        $overloader = new RationalMoneyOverloader();

        // 10/3 has no finite decimal; multiplying the exact rational back by 3 round-trips to 10.
        $third = RationalMoney::of(10, 'EUR')->dividedBy(3);
        $result = $overloader->evaluate($third, 3, '*');

        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo(Money::of(10, 'EUR')));
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function it_evaluates_comparisons(mixed $left, string $operator, mixed $right, bool $expected): void
    {
        $overloader = new RationalMoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));

        $result = $overloader->evaluate(left: $left, right: $right, operator: $operator);
        $this->assertTrue($result->isOk());
        $this->assertSame($expected, $result->unwrap());
    }

    public static function comparisons(): Generator
    {
        yield 'rational == money' => [RationalMoney::of('2.5', 'EUR'), '==', Money::of('2.5', 'EUR'), true];
        yield 'rational != money' => [RationalMoney::of('2.5', 'EUR'), '!=', Money::of(3, 'EUR'), true];
        yield 'rational < money' => [RationalMoney::of('2.5', 'EUR'), '<', Money::of(3, 'EUR'), true];
        yield 'money > rational' => [Money::of(3, 'EUR'), '>', RationalMoney::of('2.5', 'EUR'), true];
        yield 'rational <= rational' => [RationalMoney::of('2.5', 'EUR'), '<=', RationalMoney::of('2.5', 'EUR'), true];
        yield 'rational >= money' => [RationalMoney::of('2.5', 'EUR'), '>=', Money::of(1, 'EUR'), true];
    }

    #[Test]
    #[DataProvider('unsupportedCombinations')]
    public function it_does_not_support(mixed $left, string $operator, mixed $right): void
    {
        $overloader = new RationalMoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));
    }

    public static function unsupportedCombinations(): Generator
    {
        // Two monies (of any kind) still cannot be multiplied or divided.
        yield 'rational * rational' => [RationalMoney::of(1, 'EUR'), '*', RationalMoney::of(2, 'EUR')];
        yield 'rational / rational' => [RationalMoney::of(1, 'EUR'), '/', RationalMoney::of(2, 'EUR')];
        yield 'rational / money' => [RationalMoney::of(1, 'EUR'), '/', Money::of(2, 'EUR')];
        yield 'numeric / rational' => [2, '/', RationalMoney::of(1, 'EUR')];
        // No RationalMoney involved — that is MoneyOverloader's job, not this one.
        yield 'money + money' => [Money::of(1, 'EUR'), '+', Money::of(2, 'EUR')];
        yield 'money * numeric' => [Money::of(1, 'EUR'), '*', 2];
        // Discriminating shapes.
        yield 'non-money * numeric' => ['foo', '*', 5];
        yield 'numeric * non-money' => [5, '*', 'foo'];
        yield 'non-money / numeric' => ['foo', '/', 5];
        yield 'non-money + rational' => ['foo', '+', RationalMoney::of(1, 'EUR')];
        yield 'rational + non-money' => [RationalMoney::of(1, 'EUR'), '+', 'foo'];
        yield 'unknown operator' => [RationalMoney::of(1, 'EUR'), '%', RationalMoney::of(1, 'EUR')];
    }

    #[Test]
    public function it_returns_err_for_unsupported_operator(): void
    {
        $overloader = new RationalMoneyOverloader();
        $result = $overloader->evaluate(RationalMoney::of(1, 'EUR'), RationalMoney::of(2, 'EUR'), '%');

        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(\InvalidArgumentException::class, $result->unwrapErr());
        $this->assertSame('Unsupported operator: %', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_when_combining_different_currencies(): void
    {
        $overloader = new RationalMoneyOverloader();
        $this->assertTrue($overloader->evaluate(RationalMoney::of(1, 'USD'), Money::of(1, 'EUR'), '+')->isErr());
    }
}
