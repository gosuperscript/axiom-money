<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests;

use Brick\Money\Money;
use Generator;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Money\Allocation;

#[CoversClass(Allocation::class)]
class AllocationTest extends TestCase
{
    #[Test]
    #[DataProvider('allocations')]
    public function it_allocates_proportionally(Money $money, array $ratios, array $expected): void
    {
        $result = Allocation::allocate($money, ...$ratios);

        $this->assertTrue($result->isOk());
        $this->assertSame($expected, array_map(strval(...), $result->unwrap()));
    }

    public static function allocations(): Generator
    {
        yield 'remainder goes to the earliest parts' => [
            Money::of('49.99', 'USD'), [1, 2, 3, 4],
            ['USD 5.00', 'USD 10.00', 'USD 15.00', 'USD 19.99'],
        ];

        yield 'minimum-premium top-up worked example' => [
            Money::of('25.00', 'GBP'), [5000, 4000],
            ['GBP 13.89', 'GBP 11.11'],
        ];

        yield 'parts always sum to the original (no penny drift)' => [
            Money::of('110.00', 'GBP'), [1, 1, 1],
            ['GBP 36.67', 'GBP 36.67', 'GBP 36.66'],
        ];

        yield 'negative amount (remainder also lands on the earliest part)' => [
            Money::of('-10.00', 'GBP'), [1, 2],
            ['GBP -3.34', 'GBP -6.66'],
        ];

        yield 'zero-scale currency (JPY)' => [
            Money::of('1000', 'JPY'), [1, 1, 1],
            ['JPY 334', 'JPY 333', 'JPY 333'],
        ];

        yield 'three-decimal currency (BHD)' => [
            Money::of('1.000', 'BHD'), [1, 1, 1],
            ['BHD 0.334', 'BHD 0.333', 'BHD 0.333'],
        ];

        yield 'zero ratios among non-zero ones receive nothing' => [
            Money::of('10.00', 'GBP'), [0, 1],
            ['GBP 0.00', 'GBP 10.00'],
        ];
    }

    #[Test]
    #[DataProvider('splits')]
    public function it_splits_into_equal_parts(Money $money, int $parts, array $expected): void
    {
        $result = Allocation::split($money, $parts);

        $this->assertTrue($result->isOk());
        $this->assertSame($expected, array_map(strval(...), $result->unwrap()));
    }

    public static function splits(): Generator
    {
        yield 'even split' => [
            Money::of('100.00', 'GBP'), 4,
            ['GBP 25.00', 'GBP 25.00', 'GBP 25.00', 'GBP 25.00'],
        ];

        yield 'remainder goes to the earliest parts' => [
            Money::of('100.00', 'GBP'), 3,
            ['GBP 33.34', 'GBP 33.33', 'GBP 33.33'],
        ];

        yield 'single part' => [
            Money::of('100.00', 'GBP'), 1,
            ['GBP 100.00'],
        ];
    }

    #[Test]
    #[DataProvider('invalidInputs')]
    public function it_returns_an_error_for_invalid_inputs(callable $call): void
    {
        $result = $call();

        $this->assertTrue($result->isErr());
        $this->assertInstanceOf(InvalidArgumentException::class, $result->unwrapErr());
    }

    public static function invalidInputs(): Generator
    {
        yield 'empty ratios' => [fn() => Allocation::allocate(Money::of('10.00', 'GBP'))];
        yield 'all-zero ratios' => [fn() => Allocation::allocate(Money::of('10.00', 'GBP'), 0, 0)];
        yield 'negative ratio' => [fn() => Allocation::allocate(Money::of('10.00', 'GBP'), 1, -1)];
        yield 'split into zero parts' => [fn() => Allocation::split(Money::of('10.00', 'GBP'), 0)];
    }
}
