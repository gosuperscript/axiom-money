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
use Superscript\Axiom\Types\Shapes\LiteralShape;
use Superscript\Axiom\Types\Shapes\OpaqueShape;

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
            ['[EUR 1.00,EUR 2.00]', new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed)],
            ['(EUR 1.00,EUR 2.00)', new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Open)],
            [MonetaryInterval::fromString('[EUR 1,EUR 2]'), new MonetaryInterval(Money::of(1, 'EUR'), Money::of(2, 'EUR'), IntervalNotation::Closed)],
        ];
    }

    /**
     * The inverse property a round trip depends on: a value of this type is
     * serialized by casting it, and a caller that sends it back must have it
     * read rather than rejected.
     */
    #[Test]
    public function it_reads_back_the_notation_a_monetary_interval_casts_itself_to(): void
    {
        $type = new MonetaryIntervalType(Currency::of('GBP'));
        $interval = new MonetaryInterval(Money::of(0, 'GBP'), Money::of(50000, 'GBP'), IntervalNotation::Closed);

        $this->assertTrue($type->coerce((string) $interval)->unwrap()->unwrap()->isEqualTo($interval));
    }

    #[Test]
    public function it_returns_err_if_value_is_a_notated_interval_of_different_currency(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $result = $type->coerce($value = '[USD 1.00,USD 2.00]');

        $this->assertEquals(new TransformValueException(type: 'monetary-interval', value: $value), $result->unwrapErr());
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

    #[Test]
    public function it_projects_to_an_opaque_monetary_interval_shape_parameterized_by_currency(): void
    {
        $type = new MonetaryIntervalType(Currency::of('EUR'));
        $this->assertEquals(
            new OpaqueShape('monetary-interval', ['currency' => new LiteralShape('EUR')]),
            $type->shape(),
        );
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
            'a bounded interval reads as a range' => ['[1,2]', 'EUR', '€1 – €2'],
            'endpoint openness is not shown' => ['(1,2)', 'GBP', '£1 – £2'],
            'thousands are grouped' => ['(50000,100000]', 'GBP', '£50,000 – £100,000'],
            'a missing right endpoint reads as a floor' => ['[5000,)', 'GBP', '£5,000 or more'],
            'a missing left endpoint reads as a ceiling' => ['(,1000]', 'GBP', 'up to £1,000'],
        ];
    }

    #[Test]
    public function it_keeps_the_pence_on_an_endpoint_that_has_them(): void
    {
        $type = new MonetaryIntervalType(Currency::of('GBP'));
        $interval = new MonetaryInterval(
            left: Money::of('1234.56', 'GBP'),
            right: Money::of(2000, 'GBP'),
            notation: IntervalNotation::LeftOpen,
        );

        $this->assertSame('£1,234.56 – £2,000', $type->format($interval));
    }

    #[Test]
    public function it_formats_an_interval_that_is_bounded_on_neither_side(): void
    {
        $type = new MonetaryIntervalType(Currency::of('GBP'));
        $interval = new MonetaryInterval(
            left: Money::of(PHP_INT_MIN, 'GBP'),
            right: Money::of(PHP_INT_MAX, 'GBP'),
            notation: IntervalNotation::Open,
        );

        $this->assertSame('any', $type->format($interval));
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
