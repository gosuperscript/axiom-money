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
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Axiom\Money\Types\MonetaryType;

#[CoversClass(MonetaryType::class)]
#[UsesClass(MoneyParser::class)]
class MonetaryTypeTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_transform_a_value(mixed $value, string $currency, Money $expected)
    {
        $type = new MonetaryType(Currency::of($currency));
        $this->assertTrue($type->coerce($value)->unwrap()->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            [150, 'EUR', Money::of(150, 'EUR')],
            ['150', 'EUR', Money::of(150, 'EUR')],
            [150, 'GBP', Money::of(150, 'GBP')],
            [1234.56, 'EUR', Money::of(1234.56, 'EUR')],
            ['1234.56', 'EUR', Money::of(1234.56, 'EUR')],
            ['100.000', 'EUR', Money::of(100, 'EUR')],
            ['1234.567', 'IQD', Money::of(1234.567, 'IQD')],
            ['1234.5678', 'UYW', Money::of(1234.5678, 'UYW')],
            [Money::of(100, 'EUR'), 'EUR', Money::of(100, 'EUR')],
        ];
    }

    #[DataProvider('errorProvider')]
    #[Test]
    public function it_returns_err_if_it_fails_to_transform(mixed $value, string $currency = 'EUR')
    {
        $type = new MonetaryType(Currency::of($currency));
        $result = $type->coerce($value);
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
        $this->assertEquals('Unable to transform into [money] from [' . TransformValueException::format($value) . ']', $result->unwrapErr()->getMessage());

    }

    public static function errorProvider(): array
    {
        return [
            ['foobar'],
            ['EUR 123.456'],
            ['€123.456'],
            ['€foobar'],
            ['EUR foobar'],
            ['1 EUR'],
            ['GBP'],
            ['1234.567', 'EUR'],
            ['1234.5678', 'IQD'],
            ['1234.56789', 'UYU'],
            [[]],
            [null],
            [Money::of(100, 'USD'), 'EUR'], // Different currency
        ];
    }

    #[Test]
    public function it_can_compare_two_values(): void
    {
        $type = new MonetaryType(Currency::of('EUR'));
        $a = Money::of(100, 'EUR');
        $b = Money::of(100, 'EUR');
        $this->assertSame(true, $type->compare($a, $b));
    }

    #[DataProvider('formatProvider')]
    #[Test]
    public function it_can_format_value(Money $value, string $expected): void
    {
        $type = new MonetaryType(Currency::of('EUR'));
        $this->assertSame($expected, $type->format($value));
    }

    public static function formatProvider(): array
    {
        return [
            [Money::of(1234.56, 'EUR'), '€1,234.56'],
            [Money::of(1234.56, 'GBP'), '£1,234.56'],
            [Money::of(1000000, 'EUR'), '€1,000,000.00'],
        ];
    }

    #[Test]
    public function it_can_assert_a_money_instance_with_correct_currency(): void
    {
        $type = new MonetaryType(Currency::of('EUR'));
        $value = Money::of(100, 'EUR');
        $result = $type->assert($value);
        $this->assertTrue($result->unwrap()->unwrap()->isEqualTo($value));
    }

    #[Test]
    public function it_returns_err_when_asserting_non_money_value(): void
    {
        $type = new MonetaryType(Currency::of('EUR'));
        $result = $type->assert($value = 'not money');
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
    }

    #[Test]
    public function it_returns_err_when_asserting_money_with_wrong_currency(): void
    {
        $type = new MonetaryType(Currency::of('EUR'));
        $result = $type->assert($value = Money::of(100, 'USD'));
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
    }
}
