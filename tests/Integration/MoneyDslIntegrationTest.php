<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Integration;

use Brick\Money\Money;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Money\Operators\MoneyOverloader;
use Superscript\Axiom\Money\Types\DynamicMonetaryType;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Axiom\Resolvers\DelegatingResolver;
use Superscript\Axiom\Resolvers\InfixResolver;
use Superscript\Axiom\Resolvers\StaticResolver;
use Superscript\Axiom\Resolvers\SymbolResolver;
use Superscript\Axiom\Resolvers\ValueResolver;
use Superscript\Axiom\Sources\InfixExpression;
use Superscript\Axiom\Sources\StaticSource;
use Superscript\Axiom\Sources\SymbolSource;
use Superscript\Axiom\Sources\TypeDefinition;
use Superscript\Axiom\SymbolRegistry;

#[CoversNothing]
class MoneyDslIntegrationTest extends TestCase
{
    private DelegatingResolver $resolver;

    protected function setUp(): void
    {
        $this->resolver = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
            InfixExpression::class => InfixResolver::class,
            TypeDefinition::class => ValueResolver::class,
            SymbolSource::class => SymbolResolver::class,
        ]);

        $this->resolver->instance(OperatorOverloader::class, new MoneyOverloader());
        $this->resolver->instance(SymbolRegistry::class, new SymbolRegistry());
    }

    #[Test]
    public function it_coerces_string_to_money_through_resolver(): void
    {
        $source = new TypeDefinition(
            type: new DynamicMonetaryType(),
            source: new StaticSource('GBP 100.00'),
        );

        $result = $this->resolver->resolve($source)->unwrap()->unwrap();

        $this->assertInstanceOf(Money::class, $result);
        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('100.00', 'GBP')));
    }

    #[Test]
    public function it_adds_two_money_values(): void
    {
        $source = new InfixExpression(
            left: new StaticSource(Money::of('100.00', 'GBP')),
            operator: '+',
            right: new StaticSource(Money::of('50.00', 'GBP')),
        );

        $result = $this->resolver->resolve($source)->unwrap()->unwrap();

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('150.00', 'GBP')));
    }

    #[Test]
    #[DataProvider('comparisonProvider')]
    public function it_compares_money_values(Money $left, string $operator, Money $right, bool $expected): void
    {
        $source = new InfixExpression(
            left: new StaticSource($left),
            operator: $operator,
            right: new StaticSource($right),
        );

        $result = $this->resolver->resolve($source)->unwrap()->unwrap();

        $this->assertSame($expected, $result);
    }

    public static function comparisonProvider(): Generator
    {
        yield 'GBP 100 > GBP 50' => [Money::of('100.00', 'GBP'), '>', Money::of('50.00', 'GBP'), true];
        yield 'GBP 50 < GBP 100' => [Money::of('50.00', 'GBP'), '<', Money::of('100.00', 'GBP'), true];
        yield 'GBP 100 == GBP 100' => [Money::of('100.00', 'GBP'), '==', Money::of('100.00', 'GBP'), true];
        yield 'GBP 100 != GBP 50' => [Money::of('100.00', 'GBP'), '!=', Money::of('50.00', 'GBP'), true];
        yield 'GBP 50 >= GBP 100' => [Money::of('50.00', 'GBP'), '>=', Money::of('100.00', 'GBP'), false];
    }

    #[Test]
    public function it_coerces_and_adds_in_single_expression(): void
    {
        $source = new InfixExpression(
            left: new TypeDefinition(
                type: new DynamicMonetaryType(),
                source: new StaticSource('GBP 75.00'),
            ),
            operator: '+',
            right: new TypeDefinition(
                type: new DynamicMonetaryType(),
                source: new StaticSource('GBP 25.00'),
            ),
        );

        $result = $this->resolver->resolve($source)->unwrap()->unwrap();

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('100.00', 'GBP')));
    }

    #[Test]
    public function it_subtracts_money_using_symbols(): void
    {
        $this->resolver->instance(SymbolRegistry::class, new SymbolRegistry([
            'price' => new StaticSource(Money::of('200.00', 'GBP')),
            'discount' => new StaticSource(Money::of('30.00', 'GBP')),
        ]));

        $source = new InfixExpression(
            left: new SymbolSource('price'),
            operator: '-',
            right: new SymbolSource('discount'),
        );

        $result = $this->resolver->resolve($source)->unwrap()->unwrap();

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('170.00', 'GBP')));
    }
}
