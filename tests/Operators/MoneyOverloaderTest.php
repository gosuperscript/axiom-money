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
use Superscript\Axiom\Money\Operators\MoneyOverloader;

#[CoversClass(MoneyOverloader::class)]
#[CoversClass(AbstractMoneyOverloader::class)]
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
        yield 'add' => [Money::of(1, 'GBP'), '+', Money::of(2, 'GBP'), Money::of(3, 'GBP')];
        yield 'subtract' => [Money::of(5, 'EUR'), '-', Money::of(2, 'EUR'), Money::of(3, 'EUR')];
        yield 'multiply' => [Money::of(10, 'USD'), '*', 2, Money::of(20, 'USD')];
        yield 'multiply commutative' => [2, '*', Money::of(10, 'USD'), Money::of(20, 'USD')];
        yield 'multiply by decimal string' => [Money::of(10, 'USD'), '*', '0.5', Money::of(5, 'USD')];
        yield 'divide' => [Money::of(20, 'JPY'), '/', 2, Money::of(10, 'JPY')];
    }

    #[Test]
    public function it_returns_money_from_addition_and_subtraction_but_rational_money_from_multiplication_and_division(): void
    {
        $overloader = new MoneyOverloader();

        $this->assertInstanceOf(Money::class, $overloader->evaluate(Money::of(1, 'USD'), Money::of(2, 'USD'), '+')->unwrap());
        $this->assertInstanceOf(Money::class, $overloader->evaluate(Money::of(5, 'USD'), Money::of(2, 'USD'), '-')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(Money::of(10, 'USD'), 2, '*')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(2, Money::of(10, 'USD'), '*')->unwrap());
        $this->assertInstanceOf(RationalMoney::class, $overloader->evaluate(Money::of(20, 'USD'), 2, '/')->unwrap());
    }

    #[Test]
    public function it_preserves_high_precision_numeric_string_scalars_exactly(): void
    {
        $overloader = new MoneyOverloader();

        // An 18-digit multiplier exceeds float precision; passing the numeric string straight to
        // Brick keeps it exact.
        $result = $overloader->evaluate(Money::of(1, 'USD'), '0.123456789012345678', '*');

        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo(RationalMoney::of('0.123456789012345678', 'USD')));
    }

    #[Test]
    public function it_accepts_whitespace_padded_numeric_string_scalars(): void
    {
        $overloader = new MoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(Money::of(10, 'USD'), ' 2 ', '*'));

        $result = $overloader->evaluate(Money::of(10, 'USD'), ' 2 ', '*');
        $this->assertTrue($result->isOk());
        $this->assertTrue($result->unwrap()->isEqualTo(Money::of(20, 'USD')));
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
    #[DataProvider('unsupportedCombinations')]
    public function it_does_not_support(mixed $left, string $operator, mixed $right): void
    {
        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));
    }

    public static function unsupportedCombinations(): Generator
    {
        yield 'money * money' => [Money::of(10, 'USD'), '*', Money::of(2, 'USD')];
        yield 'money / money' => [Money::of(20, 'JPY'), '/', Money::of(2, 'JPY')];
        yield 'numeric / money' => [2, '/', Money::of(10, 'USD')];
        yield 'non-money * numeric' => ['foo', '*', 5];
        yield 'numeric * non-money' => [5, '*', 'foo'];
        yield 'non-money / numeric' => ['foo', '/', 5];
        yield 'unknown operator' => [Money::of(1, 'EUR'), '%', Money::of(1, 'EUR')];
        // A RationalMoney operand belongs to RationalMoneyOverloader, not here.
        yield 'rational money add' => [RationalMoney::of(1, 'EUR'), '+', Money::of(1, 'EUR')];
        yield 'rational money times numeric' => [RationalMoney::of(1, 'EUR'), '*', 2];
    }

    #[Test]
    #[DataProvider('crossCurrencyCombinations')]
    public function it_returns_err_when_combining_different_currencies(string $operator): void
    {
        $overloader = new MoneyOverloader();
        $result = $overloader->evaluate(Money::of(1, 'USD'), Money::of(1, 'EUR'), $operator);

        $this->assertTrue($result->isErr());
    }

    public static function crossCurrencyCombinations(): Generator
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

        $this->assertTrue($overloader->evaluate('not a money', Money::of(1, 'EUR'), '+')->isErr());
    }

    #[Test]
    public function it_returns_err_for_unsupported_right_side(): void
    {
        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading(Money::of(1, 'EUR'), 'not a money', '+'));

        $this->assertTrue($overloader->evaluate(Money::of(1, 'EUR'), 'not a money', '+')->isErr());
    }
}
