<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests;

use Brick\Math\RoundingMode;
use Brick\Money\Currency;
use Brick\Money\Money;
use Generator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Dialect;
use Superscript\Axiom\Expression;
use Superscript\Axiom\Money\MoneyExtension;
use Superscript\Axiom\Money\Types\MonetaryIntervalType;
use Superscript\Axiom\Money\Types\MonetaryType;
use Superscript\Axiom\Source;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Types\Type;
use Superscript\MonetaryInterval\IntervalNotation;
use Superscript\MonetaryInterval\MonetaryInterval;

#[CoversClass(MoneyExtension::class)]
#[UsesClass(MonetaryType::class)]
#[UsesClass(MonetaryIntervalType::class)]
class MoneyExtensionTest extends TestCase
{
    private function dialect(RoundingMode $rounding = RoundingMode::HALF_UP): Dialect
    {
        return Dialect::core()->with(new MoneyExtension(['GBP', 'USD', 'EUR'], $rounding));
    }

    /**
     * @param array<string, Type> $declarations
     * @param array<string, mixed> $bindings
     */
    private function evaluate(Source $source, array $declarations, array $bindings, RoundingMode $rounding = RoundingMode::HALF_UP): mixed
    {
        return (new Expression($source, dialect: $this->dialect($rounding), declarations: $declarations))
            ->compile()->unwrap()($bindings)->unwrap()->unwrap();
    }

    private function gbp(): MonetaryType
    {
        return new MonetaryType(Currency::of('GBP'));
    }

    // ---- arithmetic --------------------------------------------------------

    #[Test]
    public function it_adds_two_monies_of_the_same_currency(): void
    {
        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('a'), '+', new SymbolSource('b')),
            ['a' => $this->gbp(), 'b' => $this->gbp()],
            ['a' => Money::of(1, 'GBP'), 'b' => Money::of(2, 'GBP')],
        );

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of(3, 'GBP')));
    }

    #[Test]
    public function it_subtracts_two_monies_of_the_same_currency(): void
    {
        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('a'), '-', new SymbolSource('b')),
            ['a' => $this->gbp(), 'b' => $this->gbp()],
            ['a' => Money::of(5, 'GBP'), 'b' => Money::of(2, 'GBP')],
        );

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of(3, 'GBP')));
    }

    #[Test]
    public function it_multiplies_money_by_a_scalar_on_the_right(): void
    {
        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('m'), '*', new StaticSource(2)),
            ['m' => $this->gbp()],
            ['m' => Money::of(10, 'GBP')],
        );

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of(20, 'GBP')));
    }

    #[Test]
    public function it_multiplies_a_scalar_by_money_on_the_left(): void
    {
        $result = $this->evaluate(
            new InfixExpression(new StaticSource(2), '*', new SymbolSource('m')),
            ['m' => $this->gbp()],
            ['m' => Money::of(10, 'GBP')],
        );

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of(20, 'GBP')));
    }

    #[Test]
    public function it_divides_money_by_a_scalar(): void
    {
        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('m'), '/', new StaticSource(2)),
            ['m' => $this->gbp()],
            ['m' => Money::of(20, 'GBP')],
        );

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of(10, 'GBP')));
    }

    #[Test]
    #[DataProvider('roundingCases')]
    public function it_rounds_scalar_arithmetic_back_to_the_currency_scale(RoundingMode $rounding, string $expectedQuotient, string $expectedProduct): void
    {
        // 10.00 / 7 = 1.4285…  and  10.00 * 0.6667 = 6.667  — both need rounding
        // to two decimals, so the extension's rounding mode is observable.
        $quotient = $this->evaluate(
            new InfixExpression(new SymbolSource('m'), '/', new StaticSource(7)),
            ['m' => $this->gbp()],
            ['m' => Money::of(10, 'GBP')],
            $rounding,
        );
        $this->assertTrue($quotient->isAmountAndCurrencyEqualTo(Money::of($expectedQuotient, 'GBP')));

        $product = $this->evaluate(
            new InfixExpression(new SymbolSource('m'), '*', new StaticSource(0.6667)),
            ['m' => $this->gbp()],
            ['m' => Money::of(10, 'GBP')],
            $rounding,
        );
        $this->assertTrue($product->isAmountAndCurrencyEqualTo(Money::of($expectedProduct, 'GBP')));
    }

    public static function roundingCases(): Generator
    {
        yield 'half up' => [RoundingMode::HALF_UP, '1.43', '6.67'];
        yield 'down' => [RoundingMode::DOWN, '1.42', '6.66'];
    }

    #[Test]
    public function it_returns_an_error_when_dividing_by_zero(): void
    {
        // A value-dependent error — the types were fine, the value wasn't — so
        // it is a reported evaluation result, not a compile-time refusal.
        $result = (new Expression(
            new InfixExpression(new SymbolSource('m'), '/', new StaticSource(0)),
            dialect: $this->dialect(),
            declarations: ['m' => $this->gbp()],
        ))->compile()->unwrap()(['m' => Money::of(10, 'GBP')]);

        $this->assertTrue($result->isErr());
    }

    // ---- ordering and equality --------------------------------------------

    #[Test]
    #[DataProvider('comparisons')]
    public function it_compares_two_monies(string $operator, int $left, int $right, bool $expected): void
    {
        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('a'), $operator, new SymbolSource('b')),
            ['a' => $this->gbp(), 'b' => $this->gbp()],
            ['a' => Money::of($left, 'GBP'), 'b' => Money::of($right, 'GBP')],
        );

        $this->assertSame($expected, $result);
    }

    public static function comparisons(): Generator
    {
        yield '1 < 2'   => ['<', 1, 2, true];
        yield '2 < 1'   => ['<', 2, 1, false];
        yield '1 <= 1'  => ['<=', 1, 1, true];
        yield '2 > 1'   => ['>', 2, 1, true];
        yield '1 >= 1'  => ['>=', 1, 1, true];
        yield '1 = 1'   => ['=', 1, 1, true];
        yield '1 == 1'  => ['==', 1, 1, true];
        yield '1 === 1' => ['===', 1, 1, true];
        yield '1 == 2'  => ['==', 1, 2, false];
        yield '1 != 2'  => ['!=', 1, 2, true];
        yield '1 !== 1' => ['!==', 1, 1, false];
    }

    // ---- monetary intervals -----------------------------------------------

    #[Test]
    #[DataProvider('intervalComparisons')]
    public function it_compares_a_monetary_interval_against_a_money(string $operator, int $money, bool $expected): void
    {
        $interval = new MonetaryInterval(Money::of(2, 'GBP'), Money::of(4, 'GBP'), IntervalNotation::Closed);

        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('iv'), $operator, new SymbolSource('m')),
            ['iv' => new MonetaryIntervalType(Currency::of('GBP')), 'm' => $this->gbp()],
            ['iv' => $interval, 'm' => Money::of($money, 'GBP')],
        );

        $this->assertSame($expected, $result);
    }

    public static function intervalComparisons(): Generator
    {
        yield '[2,4] > 1'  => ['>', 1, true];
        yield '[2,4] > 5'  => ['>', 5, false];
        yield '[2,4] >= 2' => ['>=', 2, true];
        yield '[2,4] < 5'  => ['<', 5, true];
        yield '[2,4] <= 4' => ['<=', 4, true];
        yield '[2,4] < 4'  => ['<', 4, false];
    }

    #[Test]
    #[DataProvider('intervalEqualities')]
    public function it_compares_two_monetary_intervals_for_equality(string $operator, bool $sameRight, bool $expected): void
    {
        $left = new MonetaryInterval(Money::of(1, 'GBP'), Money::of(2, 'GBP'), IntervalNotation::Closed);
        $right = $sameRight
            ? new MonetaryInterval(Money::of(1, 'GBP'), Money::of(2, 'GBP'), IntervalNotation::Closed)
            : new MonetaryInterval(Money::of(1, 'GBP'), Money::of(3, 'GBP'), IntervalNotation::Closed);

        $result = $this->evaluate(
            new InfixExpression(new SymbolSource('a'), $operator, new SymbolSource('b')),
            ['a' => new MonetaryIntervalType(Currency::of('GBP')), 'b' => new MonetaryIntervalType(Currency::of('GBP'))],
            ['a' => $left, 'b' => $right],
        );

        $this->assertSame($expected, $result);
    }

    public static function intervalEqualities(): Generator
    {
        yield 'equal =' => ['=', true, true];
        yield 'equal ==' => ['==', true, true];
        yield 'equal ===' => ['===', true, true];
        yield 'unequal ==' => ['==', false, false];
        yield 'unequal !=' => ['!=', false, true];
        yield 'equal !==' => ['!==', true, false];
    }

    // ---- literals ----------------------------------------------------------

    #[Test]
    public function it_registers_the_money_literal_with_its_currency(): void
    {
        $type = (new Expression(new StaticSource(Money::of(100, 'GBP')), dialect: $this->dialect()))
            ->infer()->unwrap();

        $this->assertEquals($this->gbp()->shape(), $type->shape());
    }

    #[Test]
    public function it_registers_the_monetary_interval_literal_with_its_currency(): void
    {
        $interval = new MonetaryInterval(Money::of(1, 'GBP'), Money::of(2, 'GBP'), IntervalNotation::Closed);

        $type = (new Expression(new StaticSource($interval), dialect: $this->dialect()))
            ->infer()->unwrap();

        $this->assertEquals((new MonetaryIntervalType(Currency::of('GBP')))->shape(), $type->shape());
    }

    // ---- refusals ----------------------------------------------------------

    #[Test]
    public function it_refuses_cross_currency_arithmetic_at_compile_time(): void
    {
        $result = (new Expression(
            new InfixExpression(new SymbolSource('a'), '+', new SymbolSource('b')),
            dialect: $this->dialect(),
            declarations: ['a' => $this->gbp(), 'b' => new MonetaryType(Currency::of('USD'))],
        ))->compile();

        $this->assertTrue($result->isErr());
    }
}
