<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Operators;

use Brick\Money\Money;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Axiom\Money\Operators\MonetaryIntervalOverloader;
use PHPUnit\Framework\TestCase;

#[CoversClass(MonetaryIntervalOverloader::class)]
class MonetaryIntervalOverloaderTest extends TestCase
{
    #[Test]
    #[DataProvider('supports')]
    public function it_supports_overloading(mixed $left, mixed $right, mixed $operator, bool $expected): void
    {
        $overloader = new MonetaryIntervalOverloader();
        $this->assertEquals($expected, $overloader->supportsOverloading(
            left: $left,
            right: $right,
            operator: $operator,
        ));
    }

    public static function supports(): Generator
    {
        yield [
            MonetaryInterval::fromString('[EUR 1, EUR 2]'),
            Money::of(1, 'EUR'),
            '>',
            true,
        ];
        yield [
            'not-money-interval',
            Money::of(1, 'EUR'),
            '>',
            false,
        ];
        yield [
            MonetaryInterval::fromString('[EUR 1, EUR 2]'),
            'not-money',
            '>',
            false,
        ];
        yield [
            MonetaryInterval::fromString('[EUR 1, EUR 2]'),
            Money::of(1, 'EUR'),
            'unsupported-operator',
            false,
        ];
    }

    #[Test]
    #[DataProvider('comparisons')]
    public function it_evaluates_comparisons(MonetaryInterval $left, string $operator, Money $right, mixed $expected): void
    {
        $overloader = new MonetaryIntervalOverloader();
        $this->assertTrue($overloader->supportsOverloading(left: $left, right: $right, operator: $operator));

        $result = $overloader->evaluate(left: $left, right: $right, operator: $operator);
        $this->assertTrue($result->isOk());
        $this->assertSame($expected, $result->unwrap());
    }

    public static function comparisons(): Generator
    {
        yield [
            MonetaryInterval::fromString('[GBP 1, GBP 2]'),
            '<',
            Money::of(3, 'GBP'),
            true,
        ];
        yield [
            MonetaryInterval::fromString('[GBP 1, GBP 2]'),
            '<=',
            Money::of(2, 'GBP'),
            true,
        ];
        yield [
            MonetaryInterval::fromString('[GBP 1, GBP 2]'),
            '>',
            Money::of(0.5, 'GBP'),
            true,
        ];
        yield [
            MonetaryInterval::fromString('[GBP 1, GBP 2]'),
            '>=',
            Money::of(1, 'GBP'),
            true,
        ];
    }

    #[Test]
    public function it_returns_err_for_unsupported_operator(): void
    {
        $overloader = new MonetaryIntervalOverloader();
        $result = $overloader->evaluate(MonetaryInterval::fromString('[EUR 1,EUR 2]'), Money::of(2, 'EUR'), '%');

        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(\InvalidArgumentException::class, $result->unwrapErr());
        $this->assertSame('Unsupported operator: %', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_for_unsupported_left_side(): void
    {
        $overloader = new MonetaryIntervalOverloader();
        $this->assertFalse($overloader->supportsOverloading('not a money interval', Money::of(1, 'EUR'), '+'));

        $result = $overloader->evaluate('not a money interval', Money::of(1, 'EUR'), '+');
        $this->assertTrue($result->isErr());
    }

    #[Test]
    public function it_returns_err_for_unsupported_right_side(): void
    {
        $overloader = new MonetaryIntervalOverloader();
        $this->assertFalse($overloader->supportsOverloading(MonetaryInterval::fromString('[EUR 1, EUR 2]'), 'not a money', '+'));

        $result = $overloader->evaluate(MonetaryInterval::fromString('[EUR 1, EUR 2]'), 'not a money', '+');
        $this->assertTrue($result->isErr());
    }
}
