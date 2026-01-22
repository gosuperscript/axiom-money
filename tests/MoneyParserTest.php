<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests;

use Brick\Money\Money;
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
            ['£1.23', Money::of(1.23, 'GBP')],
            ['USD 100.50', Money::of(100.50, 'USD')],
            [Money::of(100.50, 'EUR'), Money::of(100.50, 'EUR')],
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
        ];
    }
}
