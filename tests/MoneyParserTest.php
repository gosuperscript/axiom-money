<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests;

use Brick\Money\Money;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use SebastianBergmann\Exporter\Exporter;
use Superscript\Axiom\Money\MoneyParser;

#[CoversClass(MoneyParser::class)]
class MoneyParserTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_parse_a_value(mixed $value, Money $expected)
    {
        $this->assertTrue(MoneyParser::parse($value)->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            ['EUR 1', Money::of(1, 'EUR')],
            ['£1.23', Money::of('1.23', 'GBP')],
            ['USD 100.50', Money::of('100.50', 'USD')],
            [Money::of('100.50', 'EUR'), Money::of('100.50', 'EUR')],
            'a symbol amount with thousands separators' => ['£1,000,000', Money::of(1000000, 'GBP')],
            'an ISO amount of seven digits' => ['GBP 1000000', Money::of(1000000, 'GBP')],
            'a symbol amount surrounded by whitespace' => ['  £1,000,000  ', Money::of(1000000, 'GBP')],
            'an ISO amount surrounded by whitespace' => ['  GBP 5  ', Money::of(5, 'GBP')],
        ];
    }

    #[DataProvider('errors')]
    #[Test]
    public function it_returns_err_if_it_fails_to_parse(mixed $value)
    {
        $result = MoneyParser::parse($value);
        $this->assertTrue($result->isErr());
        $this->assertEquals('Could not parse [' . new Exporter()->shortenedExport($value) . '] as money', $result->unwrapErr()->getMessage());
    }

    public static function errors(): array
    {
        return [
            ['foobar'],
            [123],
            ['123'],
            ['EUR 123.456'],
            ['€123.456'],
            ['€foobar'],
            ['EUR foobar'],
            ['1 EUR'],
            ['GBP'],
            'an ISO code run into a shorthand amount' => ['GBP1M'],
            'a symbol amount followed by a shorthand suffix' => ['£1m'],
            'an ISO amount followed by a shorthand suffix' => ['GBP 1M'],
            'an ISO amount preceded by text' => ['xx GBP 5'],
            'an ISO amount followed by text' => ['GBP 5 yy'],
            'a bare amount with thousands separators' => ['1,000,000'],
        ];
    }

    #[DataProvider('exactAmounts')]
    #[Test]
    public function it_renders_a_raw_amount_in_the_form_brick_accepts(string|float|int $amount, string|int $expected)
    {
        $this->assertSame($expected, MoneyParser::exact($amount));
    }

    public static function exactAmounts(): array
    {
        return [
            'a float becomes its shortest round-tripping decimal' => [1.23, '1.23'],
            'a float with a trailing zero drops it' => [100.50, '100.5'],
            'a whole float keeps no decimal point' => [2.0, '2'],
            // Past 14 significant digits a (string) cast truncates to 1.2345678901235E+14.
            'a float past the default precision keeps every digit' => [123456789012345.67, '123456789012345.67'],
            'a float that is all residue keeps it' => [0.30000000000000004, '0.30000000000000004'],
            'an int passes through as an int' => [1234, 1234],
            'a numeric string passes through untouched' => ['1234.5678', '1234.5678'],
        ];
    }

    #[Test]
    public function it_refuses_a_float_that_names_no_amount(): void
    {
        $this->expectException(InvalidArgumentException::class);

        MoneyParser::exact(INF);
    }
}
