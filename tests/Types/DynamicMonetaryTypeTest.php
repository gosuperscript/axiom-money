<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Types;

use Brick\Math\RoundingMode;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Axiom\Money\Types\DynamicMonetaryType;
use Superscript\Axiom\Types\Shapes\OpaqueShape;

#[CoversClass(DynamicMonetaryType::class)]
#[UsesClass(MoneyParser::class)]
class DynamicMonetaryTypeTest extends TestCase
{
    #[DataProvider('transformProvider')]
    #[Test]
    public function it_can_transform_a_value(mixed $value, Money $expected)
    {
        $type = new DynamicMonetaryType();
        $this->assertTrue($type->coerce($value)->unwrap()->unwrap()->isEqualTo($expected));
    }

    public static function transformProvider(): array
    {
        return [
            ['EUR 1', Money::of(1, 'EUR')],
            ['£1.23', Money::of(1.23, 'GBP')],
            ['USD 100.50', Money::of(100.50, 'USD')],
            [Money::of(100, 'EUR'), Money::of(100, 'EUR')],
        ];
    }

    #[Test]
    public function it_returns_err_if_it_fails_to_transform()
    {
        $type = new DynamicMonetaryType();
        $result = $type->coerce($value = 'foobar');
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
        $this->assertEquals('Unable to transform into [money] from [\'foobar\']', $result->unwrapErr()->getMessage());

    }

    #[Test]
    public function it_rounds_a_rational_money_to_its_currency_scale_on_coerce(): void
    {
        // No fixed currency: the RationalMoney carries its own. 10/3 EUR rounds to 3.33 (HALF_UP).
        $rational = RationalMoney::of(10, 'EUR')->dividedBy(3);

        $this->assertTrue((new DynamicMonetaryType())->coerce($rational)->unwrap()->unwrap()->isEqualTo(Money::of('3.33', 'EUR')));
    }

    #[Test]
    public function it_honors_a_custom_rounding_mode_when_coercing_rational_money(): void
    {
        $twoThirds = RationalMoney::of(2, 'EUR')->dividedBy(3); // 0.666...

        $this->assertTrue((new DynamicMonetaryType(RoundingMode::DOWN))->coerce($twoThirds)->unwrap()->unwrap()->isEqualTo(Money::of('0.66', 'EUR')));
    }

    #[Test]
    public function it_projects_to_an_opaque_money_shape_without_a_currency_parameter(): void
    {
        // Boundary/coercion type only: currency is not statically known, so
        // it carries no currency parameter and resolves no operator rules.
        $this->assertEquals(new OpaqueShape('money'), (new DynamicMonetaryType())->shape());
    }

    #[DataProvider('formatProvider')]
    #[Test]
    public function it_can_format_value(string $value, string $expected)
    {
        $type = new DynamicMonetaryType();
        $value = $type->coerce($value)->unwrap()->unwrap();
        $this->assertSame($expected, $type->format($value));
    }

    public static function formatProvider(): array
    {
        return [
            ['EUR 1.23', '€1.23'],
            ['£1.23', '£1.23'],
            ['EUR 10000', '€10,000.00'],
        ];
    }

    #[Test]
    public function it_can_assert_a_money_instance(): void
    {
        $type = new DynamicMonetaryType();
        $value = Money::of(100, 'EUR');
        $result = $type->assert($value);
        $this->assertTrue($result->unwrap()->unwrap()->isEqualTo($value));
    }

    #[Test]
    public function it_returns_err_when_asserting_non_money_value(): void
    {
        $type = new DynamicMonetaryType();
        $result = $type->assert($value = 'not money');
        $this->assertEquals(new TransformValueException(type: 'money', value: $value), $result->unwrapErr());
    }
}
