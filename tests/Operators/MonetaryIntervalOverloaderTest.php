<?php

declare(strict_types=1);

namespace Operators;

use Brick\Money\Money;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Psl\Type\Exception\AssertException;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Schema\Money\Operators\MonetaryIntervalOverloader;
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
        $this->assertSame($expected, $overloader->evaluate(left: $left, right: $right, operator: $operator));
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
    public function it_throws_exception_for_unsupported_operator(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Unsupported operator: %');

        $overloader = new MonetaryIntervalOverloader();
        $overloader->evaluate(MonetaryInterval::fromString('[EUR 1,EUR 2]'), Money::of(2, 'EUR'), '%');
    }

    #[Test]
    public function it_throws_exception_for_unsupported_left_side(): void
    {
        $this->expectException(AssertException::class);

        $overloader = new MonetaryIntervalOverloader();
        $this->assertFalse($overloader->supportsOverloading('not a money interval', Money::of(1, 'EUR'), '+'));
        $overloader->evaluate('not a money interval', Money::of(1, 'EUR'), '+');
    }

    #[Test]
    public function it_throws_exception_for_unsupported_right_side(): void
    {
        $this->expectException(AssertException::class);

        $overloader = new MonetaryIntervalOverloader();
        $this->assertFalse($overloader->supportsOverloading(MonetaryInterval::fromString('[EUR 1, EUR 2]'), 'not a money', '+'));
        $overloader->evaluate(Money::of(1, 'EUR'), 'not a money', '+');
    }
}
