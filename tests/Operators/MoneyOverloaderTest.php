<?php

namespace Superscript\Schema\Money\Tests\Operators;

use Brick\Money\Money;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psl\Type\Exception\AssertException;
use Superscript\Schema\Money\Operators\MoneyOverloader;
use PHPUnit\Framework\TestCase;

#[CoversClass(MoneyOverloader::class)]
class MoneyOverloaderTest extends TestCase
{
    #[Test]
    #[DataProvider('calculations')]
    public function it_evaluates_calculations(Money $left, string $operator, Money $right, mixed $expected): void
    {
        $overloader = new MoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));
        $this->assertTrue($overloader->evaluate(left: $left, right: $right, operator: $operator)->isEqualTo($expected));
    }

    public static function calculations(): Generator
    {
        yield [Money::of(1, 'GBP'), '+', Money::of(2, 'GBP'), Money::of(3, 'GBP')];
        yield [Money::of(5, 'EUR'), '-', Money::of(2, 'EUR'), Money::of(3, 'EUR')];
        yield [Money::of(10, 'USD'), '*', Money::of(2, 'USD'), Money::of(20, 'USD')];
        yield [Money::of(20, 'JPY'), '/', Money::of(2, 'JPY'), Money::of(10, 'JPY')];
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function it_evaluates_comparisons(Money $left, string $operator, Money $right, mixed $expected): void
    {
        $overloader = new MoneyOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));
        $this->assertSame($expected, $overloader->evaluate(left: $left, right: $right, operator: $operator));
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
    public function it_throws_exception_for_unsupported_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator: %');

        $overloader = new MoneyOverloader();
        $overloader->evaluate(Money::of(1, 'EUR'), Money::of(2, 'EUR'), '%');
    }

    #[Test]
    public function it_throws_exception_for_unsupported_left_side(): void
    {
        $this->expectException(AssertException::class);

        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading('not a money', Money::of(1, 'EUR'), '+'));
        $overloader->evaluate('not a money', Money::of(1, 'EUR'), '+');
    }

    #[Test]
    public function it_throws_exception_for_unsupported_right_side(): void
    {
        $this->expectException(AssertException::class);

        $overloader = new MoneyOverloader();
        $this->assertFalse($overloader->supportsOverloading(Money::of(1, 'EUR'), 'not a money', '+'));
        $overloader->evaluate(Money::of(1, 'EUR'), 'not a money', '+');
    }
}
