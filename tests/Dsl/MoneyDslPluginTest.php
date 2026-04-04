<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests\Dsl;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Dsl\FunctionRegistry;
use Superscript\Axiom\Dsl\OperatorRegistry;
use Superscript\Axiom\Dsl\TypeRegistry;
use Superscript\Axiom\Money\Dsl\MoneyDslPlugin;
use Superscript\Axiom\Money\Operators\MoneyOverloader;
use Superscript\Axiom\Money\Types\DynamicMonetaryType;

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
        $this->assertInstanceOf(DynamicMonetaryType::class, $registry->resolve('money'));
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

        $this->assertCount(1, $overloaders);
        $this->assertInstanceOf(MoneyOverloader::class, $overloaders[0]);
    }
}
