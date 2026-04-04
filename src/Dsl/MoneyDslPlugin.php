<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Dsl;

use Brick\Money\Currency;
use Superscript\Axiom\Dsl\DslLiteralExtension;
use Superscript\Axiom\Dsl\DslPlugin;
use Superscript\Axiom\Dsl\FunctionRegistry;
use Superscript\Axiom\Dsl\OperatorRegistry;
use Superscript\Axiom\Dsl\TypeRegistry;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Axiom\Patterns\PatternMatcher;
use Superscript\Schema\Money\Operators\MonetaryIntervalOverloader;
use Superscript\Schema\Money\Operators\MoneyOverloader;
use Superscript\Schema\Money\Types\DynamicMonetaryType;
use Superscript\Schema\Money\Types\MinorMonetaryType;
use Superscript\Schema\Money\Types\MonetaryIntervalType;
use Superscript\Schema\Money\Types\MonetaryType;

final class MoneyDslPlugin implements DslPlugin
{
    public function operators(OperatorRegistry $operators): void {}

    public function types(TypeRegistry $types): void
    {
        $types->register('money', fn (mixed ...$args) => new MonetaryType(Currency::of($args[0])));

        $types->register('minor_money', fn (mixed ...$args) => new MinorMonetaryType(Currency::of($args[0])));

        $types->register('dynamic_money', fn () => new DynamicMonetaryType());

        $types->register('monetary_interval', fn (mixed ...$args) => new MonetaryIntervalType(Currency::of($args[0])));
    }

    public function functions(FunctionRegistry $functions): void {}

    /** @return list<PatternMatcher> */
    public function patterns(): array
    {
        return [];
    }

    /** @return list<DslLiteralExtension> */
    public function literals(): array
    {
        return [];
    }

    /** @return list<OperatorOverloader> */
    public function overloaders(): array
    {
        return [new MoneyOverloader(), new MonetaryIntervalOverloader()];
    }
}
