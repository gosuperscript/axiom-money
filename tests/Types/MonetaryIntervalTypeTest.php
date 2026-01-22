<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Types;

use Brick\Money\Currency;
use Brick\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Superscript\MonetaryInterval\IntervalNotation;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Axiom\Money\Types\MonetaryIntervalType;

#[CoversClass(MonetaryIntervalType::class)]
#[UsesClass(MoneyParser::class)]
class MonetaryIntervalTypeTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_transform_a_value(mixed $value, MonetaryInterval $expected)
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $this->assertTrue($type->coerce($value)->unwrap()->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            ['[1,2]', new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed)],
            [MonetaryInterval::fromString('[EUR 1,EUR 2]'), new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed)],
        ];
    }

    #[Test]
    public function it_returns_err_if_it_fails_to_transform(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->coerce($value = 'foobar');
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: $value), $result->unwrapErr());
        $this->assertEquals('Unable to transform into [monetary-interval] from [\'foobar\']', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_if_value_is_not_a_string(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->coerce(123);
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: 123), $result->unwrapErr());
        $this->assertEquals('Unable to transform into [monetary-interval] from [123]', $result->unwrapErr()->getMessage());
    }

    #[Test]
    public function it_returns_err_if_value_is_monetary_interval_of_different_currency(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->coerce(new MonetaryInterval(Money::of(1, 'USD'), Money::of(2, 'USD'), IntervalNotation::Closed));
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: '[USD 1.00,USD 2.00]'), $result->unwrapErr());
    }

    #[DataProvider('compareProvider')]
    #[Test]
    public function it_can_compare_two_values(string $a, string $b, bool $expected): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $a = $type->coerce($a)->unwrap()->unwrap();
        $b = $type->coerce($b)->unwrap()->unwrap();
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
        $value = $type->coerce($value)->unwrap()->unwrap();
        $this->assertSame($expected, $type->format($value));
    }

    public static function formatProvider(): array
    {
        return [
            ['[1,2]', 'EUR', '[EUR 1.00,EUR 2.00]'],
            ['(1,2)', 'GBP', '(GBP 1.00,GBP 2.00)'],
        ];
    }

    #[Test]
    public function it_can_assert_a_monetary_interval_with_correct_currency(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $value = new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed);
        $result = $type->assert($value);
        $this->assertTrue($result->unwrap()->unwrap()->isEqualTo($value));
    }

    #[Test]
    public function it_returns_err_when_asserting_non_monetary_interval_value(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->assert($value = 'not interval');
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: $value), $result->unwrapErr());
    }

    #[Test]
    public function it_returns_err_when_asserting_monetary_interval_with_wrong_currency(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->assert($value = new MonetaryInterval(Money::of(1, 'USD'), Money::of(2, 'USD'), IntervalNotation::Closed));
        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: $value), $result->unwrapErr());
    }
}
