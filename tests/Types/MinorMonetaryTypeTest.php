<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Types;

use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Axiom\Money\Types\MinorMonetaryType;

#[CoversClass(MinorMonetaryType::class)]
#[UsesClass(MoneyParser::class)]
class MinorMonetaryTypeTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_transform_a_value(mixed $value, string $currency, Money $expected)
    {
        $type = new MinorMonetaryType(Currency::of($currency));
        $this->assertTrue($type->coerce($value)->unwrap()->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            [150, 'EUR', Money::ofMinor(150, 'EUR')],
            [150, 'GBP', Money::ofMinor(150, 'GBP')],
            ['150', 'EUR', Money::ofMinor(150, 'EUR')],
            ['100.000', 'EUR', Money::ofMinor(100, 'EUR')],
            [Money::ofMinor(100, 'GBP'), 'GBP', Money::ofMinor(100, 'GBP')],
        ];
    }

    #[Test]
    #[DataProvider('errorProvider')]
    public function it_returns_err_if_it_fails_to_transform(mixed $value, string $currency = 'EUR')
    {
        $type = new MinorMonetaryType(Currency::of($currency));
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
            [[]],
            [null],
            [Money::ofMinor(100, 'USD'), 'EUR'], // Mismatching currency
        ];
    }

    #[Test]
    public function it_coerces_a_rational_money_by_rounding_to_the_currency_scale(): void
    {
        // A RationalMoney is already a money value (major units), so it is collapsed via ->to(),
        // not treated as minor units. 10/3 EUR rounds to 3.33 (HALF_UP by default).
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $rational = RationalMoney::of(10, 'EUR')->dividedBy(3);
        $this->assertTrue($type->coerce($rational)->unwrap()->unwrap()->isEqualTo(Money::of('3.33', 'EUR')));

        // Custom rounding mode is honored.
        $down = new MinorMonetaryType(Currency::of('EUR'), RoundingMode::DOWN);
        $twoThirds = RationalMoney::of(2, 'EUR')->dividedBy(3);
        $this->assertTrue($down->coerce($twoThirds)->unwrap()->unwrap()->isEqualTo(Money::of('0.66', 'EUR')));

        // Currency mismatch still fails.
        $this->assertTrue($type->coerce(RationalMoney::of(100, 'USD'))->isErr());
    }

    #[Test]
    public function it_can_compare_two_values(): void
    {
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $a = Money::ofMinor(100, 'EUR');
        $b = Money::ofMinor(100, 'EUR');
        $this->assertSame(true, $type->compare($a, $b));
    }

    #[DataProvider('formatProvider')]
    #[Test]
    public function it_can_format_value(Money $value, string $expected): void
    {
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $this->assertSame($expected, $type->format($value));
    }

    public static function formatProvider(): array
    {
        return [
            [Money::ofMinor(123, 'EUR'), '€1.23'],
            [Money::ofMinor(123, 'GBP'), '£1.23'],
            [Money::ofMinor(1000000, 'EUR'), '€10,000.00'],
        ];
    }

    #[Test]
    public function it_can_assert_a_money_instance_with_correct_currency(): void
    {
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $value = Money::ofMinor(100, 'EUR');
        $result = $type->assert($value);
        $this->assertTrue($result->unwrap()->unwrap()->isEqualTo($value));
    }

    #[Test]
    public function it_returns_err_when_asserting_non_money_value(): void
    {
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $result = $type->assert($value = 'not money');
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
    }

    #[Test]
    public function it_returns_err_when_asserting_money_with_wrong_currency(): void
    {
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $result = $type->assert($value = Money::ofMinor(100, 'USD'));
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
    }
}
