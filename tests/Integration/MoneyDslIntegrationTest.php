<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Integration;

use Brick\Money\Money;
use Generator;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Dsl\AxiomDsl;
use Superscript\Axiom\Dsl\CoreDslPlugin;
use Superscript\Axiom\Money\Dsl\MoneyDslPlugin;
use Superscript\Axiom\Money\Operators\MoneyOverloader;
use Superscript\Axiom\Operators\DefaultOverloader;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Axiom\Operators\OverloaderManager;
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
    private AxiomDsl $dsl;

    protected function setUp(): void
    {
        $this->dsl = AxiomDsl::fromPlugins(
            new CoreDslPlugin(),
            new MoneyDslPlugin(),
        );
    }

    private function resolve(string $source, string $symbol): mixed
    {
        $compilation = $this->dsl->evaluate($source);

        $resolver = new DelegatingResolver([
            StaticSource::class => StaticResolver::class,
            InfixExpression::class => InfixResolver::class,
            TypeDefinition::class => ValueResolver::class,
            SymbolSource::class => SymbolResolver::class,
        ]);

        $resolver->instance(OperatorOverloader::class, new OverloaderManager([
            new MoneyOverloader(),
            new DefaultOverloader(),
        ]));
        $resolver->instance(SymbolRegistry::class, $compilation->symbols);

        return $resolver->resolve(new SymbolSource($symbol))->unwrap()->unwrap();
    }

    #[Test]
    public function it_coerces_string_to_money(): void
    {
        $result = $this->resolve('premium: money = "GBP 100.00"', 'premium');

        $this->assertInstanceOf(Money::class, $result);
        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('100.00', 'GBP')));
    }

    #[Test]
    public function it_adds_two_money_values(): void
    {
        $source = <<<'AXIOM'
        base: money = "GBP 100.00"
        surcharge: money = "GBP 25.00"
        total: money = base + surcharge
        AXIOM;

        $result = $this->resolve($source, 'total');

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('125.00', 'GBP')));
    }

    #[Test]
    public function it_subtracts_money_values(): void
    {
        $source = <<<'AXIOM'
        price: money = "GBP 200.00"
        discount: money = "GBP 30.00"
        final_price: money = price - discount
        AXIOM;

        $result = $this->resolve($source, 'final_price');

        $this->assertTrue($result->isAmountAndCurrencyEqualTo(Money::of('170.00', 'GBP')));
    }

    #[Test]
    #[DataProvider('comparisonProvider')]
    public function it_compares_money_values(string $dsl, bool $expected): void
    {
        $source = <<<AXIOM
        a: money = "GBP 100.00"
        b: money = "GBP 50.00"
        result: bool = {$dsl}
        AXIOM;

        $this->assertSame($expected, $this->resolve($source, 'result'));
    }

    public static function comparisonProvider(): Generator
    {
        yield 'greater than' => ['a > b', true];
        yield 'less than' => ['a < b', false];
        yield 'equal' => ['a == a', true];
        yield 'not equal' => ['a != b', true];
        yield 'greater than or equal' => ['b >= a', false];
    }

    #[Test]
    public function it_uses_money_in_conditional(): void
    {
        $source = <<<'AXIOM'
        premium: money = "GBP 500.00"
        threshold: money = "GBP 250.00"
        tier: string = if premium > threshold then "high" else "low"
        AXIOM;

        $this->assertSame('high', $this->resolve($source, 'tier'));
    }
}
