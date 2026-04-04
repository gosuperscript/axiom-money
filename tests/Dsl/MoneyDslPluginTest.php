<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Tests\Dsl;

use Brick\Money\Currency;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\UsesClass;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Dsl\FunctionRegistry;
use Superscript\Axiom\Dsl\OperatorRegistry;
use Superscript\Axiom\Dsl\TypeRegistry;
use Superscript\Schema\Money\Dsl\MoneyDslPlugin;
use Superscript\Schema\Money\Operators\MonetaryIntervalOverloader;
use Superscript\Schema\Money\Operators\MoneyOverloader;
use Superscript\Schema\Money\Types\DynamicMonetaryType;
use Superscript\Schema\Money\Types\MinorMonetaryType;
use Superscript\Schema\Money\Types\MonetaryIntervalType;
use Superscript\Schema\Money\Types\MonetaryType;

#[CoversClass(MoneyDslPlugin::class)]
class MoneyDslPluginTest extends TestCase
{
    #[Test]
    public function it_has_no_operators(): void
    {
        $plugin = new MoneyDslPlugin();
        $registry = new OperatorRegistry();

        $plugin->operators($registry);

        $this->assertEmpty($registry->all());
    }

    #[Test]
    public function it_registers_the_money_type(): void
    {
        $plugin = new MoneyDslPlugin();
        $registry = new TypeRegistry();

        $plugin->types($registry);

        $this->assertTrue($registry->has('money'));
        $this->assertInstanceOf(MonetaryType::class, $registry->resolve('money', 'EUR'));
    }

    #[Test]
    public function it_registers_the_minor_money_type(): void
    {
        $plugin = new MoneyDslPlugin();
        $registry = new TypeRegistry();

        $plugin->types($registry);

        $this->assertTrue($registry->has('minor_money'));
        $this->assertInstanceOf(MinorMonetaryType::class, $registry->resolve('minor_money', 'GBP'));
    }

    #[Test]
    public function it_registers_the_dynamic_money_type(): void
    {
        $plugin = new MoneyDslPlugin();
        $registry = new TypeRegistry();

        $plugin->types($registry);

        $this->assertTrue($registry->has('dynamic_money'));
        $this->assertInstanceOf(DynamicMonetaryType::class, $registry->resolve('dynamic_money'));
    }

    #[Test]
    public function it_registers_the_monetary_interval_type(): void
    {
        $plugin = new MoneyDslPlugin();
        $registry = new TypeRegistry();

        $plugin->types($registry);

        $this->assertTrue($registry->has('monetary_interval'));
        $this->assertInstanceOf(MonetaryIntervalType::class, $registry->resolve('monetary_interval', 'EUR'));
    }

    #[Test]
    public function it_has_no_functions(): void
    {
        $plugin = new MoneyDslPlugin();
        $registry = new FunctionRegistry();

        $plugin->functions($registry);

        $this->assertEmpty($registry->all());
    }

    #[Test]
    public function it_has_no_patterns(): void
    {
        $plugin = new MoneyDslPlugin();

        $this->assertEmpty($plugin->patterns());
    }

    #[Test]
    public function it_has_no_literals(): void
    {
        $plugin = new MoneyDslPlugin();

        $this->assertEmpty($plugin->literals());
    }

    #[Test]
    public function it_provides_overloaders(): void
    {
        $plugin = new MoneyDslPlugin();
        $overloaders = $plugin->overloaders();

        $this->assertCount(2, $overloaders);
        $this->assertInstanceOf(MoneyOverloader::class, $overloaders[0]);
        $this->assertInstanceOf(MonetaryIntervalOverloader::class, $overloaders[1]);
    }
}
