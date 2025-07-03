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
use SebastianBergmann\Exporter\Exporter;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Schema\Money\MoneyParser;
use Superscript\Schema\Money\Types\MinorMonetaryType;
use Superscript\Schema\Money\Types\DynamicMonetaryType;

#[CoversClass(MinorMonetaryType::class)]
#[UsesClass(MoneyParser::class)]
class MinorMonetaryTypeTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_transform_a_value(mixed $value, string $currency, Money $expected)
    {
        $type = new MinorMonetaryType(Currency::of($currency));
        $this->assertTrue($type->transform($value)->unwrap()->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            [150, 'EUR', Money::ofMinor(150, 'EUR')],
            [150, 'GBP', Money::ofMinor(150, 'GBP')],
            ['150', 'EUR', Money::ofMinor(150, 'EUR')],
            ['100.000', 'EUR', Money::ofMinor(100, 'EUR')],
        ];
    }

    #[Test]
    #[DataProvider('errorProvider')]
    public function it_returns_err_if_it_fails_to_transform(mixed $value)
    {
        $type = new MinorMonetaryType(Currency::of('EUR'));
        $result = $type->transform($value);
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
        $formattedValue = (new Exporter())->shortenedExport($value);
        $this->assertEquals('Unable to transform into [money] from ['.$formattedValue.']', $result->unwrapErr()->getMessage());

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
        ];
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
}
