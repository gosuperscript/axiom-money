<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Tests\Types;

use Brick\Money\Currency;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Superscript\MonetaryInterval\IntervalNotation;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Schema\Money\MoneyParser;
use Superscript\Schema\Money\Types\MonetaryIntervalType;

#[CoversClass(MonetaryIntervalType::class)]
#[UsesClass(MoneyParser::class)]
class MonetaryIntervalTypeTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_transform_a_value(mixed $value, MonetaryInterval $expected)
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $this->assertTrue($type->transform($value)->unwrap()->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            ['[1,2]', new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed)],
            [MonetaryInterval::fromString('[1,2]'), new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed)],
        ];
    }

    #[Test]
    public function it_returns_err_if_it_fails_to_transform(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->transform($value = 'foobar');
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: $value), $result->unwrapErr());
        $this->assertEquals('Unable to transform into [monetary-interval] from [\'foobar\']', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_if_value_is_not_a_string(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->transform(123);
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: '123'), $result->unwrapErr());
        $this->assertEquals('Unable to transform into [monetary-interval] from [\'123\']', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_if_value_is_monetary_interval_of_different_currency(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->transform(new MonetaryInterval(Money::of(1, 'USD'), Money::of(2, 'USD'), IntervalNotation::Closed));
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: '[USD 1.00,USD 2.00]'), $result->unwrapErr());
    }

    #[DataProvider('compareProvider')]
    #[Test]
    public function it_can_compare_two_values(string $a, string $b, bool $expected): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $a = $type->transform($a)->unwrap()->unwrap();
        $b = $type->transform($b)->unwrap()->unwrap();
        $this->assertSame($expected, $type->compare($a, $b));
    }

    public static function compareProvider(): array
    {
        return [
            ['[1,2]', '[1,2]', true],
            ['(1,2)', '(1,2)', true],
            ['[1,2]', '(1,2)', false],
        ];
    }

    #[DataProvider('formatProvider')]
    #[Test]
    public function it_can_format_value(string $value, string $currency, string $expected): void
    {
        $type = new MonetaryIntervalType(Currency::of($currency));
        $value = $type->transform($value)->unwrap()->unwrap();
        $this->assertSame($expected, $type->format($value));
    }

    public static function formatProvider(): array
    {
        return [
            ['[1,2]', 'EUR', '[EUR 1.00,EUR 2.00]'],
            ['(1,2)', 'GBP', '(GBP 1.00,GBP 2.00)'],
        ];
    }
}
