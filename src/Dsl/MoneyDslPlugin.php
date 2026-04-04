<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Dsl;

use Superscript\Axiom\Dsl\DslLiteralExtension;
use Superscript\Axiom\Dsl\DslPlugin;
use Superscript\Axiom\Dsl\FunctionRegistry;
use Superscript\Axiom\Dsl\OperatorRegistry;
use Superscript\Axiom\Dsl\TypeRegistry;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Axiom\Patterns\PatternMatcher;
use Superscript\Axiom\Money\Operators\MoneyOverloader;
use Superscript\Axiom\Money\Types\DynamicMonetaryType;

final class MoneyDslPlugin implements DslPlugin
{
    public function operators(OperatorRegistry $operators): void {}

    public function types(TypeRegistry $types): void
    {
        $types->register('money', fn () => new DynamicMonetaryType());
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
        return [new MoneyOverloader()];
    }
}
