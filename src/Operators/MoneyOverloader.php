<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Operators;

use Brick\Money\AbstractMoney;
use Brick\Money\Money;

use function Psl\Type\instance_of;

/**
 * Overloads arithmetic and comparison operators on {@see Money} operands.
 *
 * Addition and subtraction of two monies stay a Money; multiplication and division require exactly
 * one numeric operand (multiplying or dividing two monies is dimensionally meaningless) and return
 * an exact {@see \Brick\Money\RationalMoney} so precision is preserved. A RationalMoney operand is
 * handled by {@see RationalMoneyOverloader}, not here.
 */
final readonly class MoneyOverloader extends AbstractMoneyOverloader
{
    public function supportsOverloading(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '+', '-', '==', '!=', '<', '>', '<=', '>=' => $left instanceof Money && $right instanceof Money,
            '*' => ($left instanceof Money && is_numeric($right)) || (is_numeric($left) && $right instanceof Money),
            '/' => $left instanceof Money && is_numeric($right),
            default => false,
        };
    }

    protected function addOrSubtract(mixed $left, mixed $right, string $operator): AbstractMoney
    {
        $left = instance_of(Money::class)->coerce($left);
        $right = instance_of(Money::class)->coerce($right);

        return $operator === '+' ? $left->plus($right) : $left->minus($right);
    }
}
