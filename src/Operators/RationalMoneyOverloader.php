<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Operators;

use Brick\Money\AbstractMoney;
use Brick\Money\RationalMoney;

use function Psl\Type\instance_of;

/**
 * Overloads operators when at least one operand is a {@see RationalMoney} — i.e. the result of a
 * previous multiplication or division chaining into a further expression.
 *
 * "Rational is contagious": once a RationalMoney is involved, addition and subtraction are carried
 * out — and returned — in the exact rational domain (a Money operand is lifted first). Comparisons
 * work across Money and RationalMoney and return a bool; multiplication and division by a numeric
 * scalar return a RationalMoney. Two monies (of either kind) still cannot be multiplied or divided.
 */
final readonly class RationalMoneyOverloader extends AbstractMoneyOverloader
{
    public function supportsOverloading(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '+', '-', '==', '!=', '<', '>', '<=', '>=' => ($left instanceof RationalMoney || $right instanceof RationalMoney)
                && $left instanceof AbstractMoney && $right instanceof AbstractMoney,
            '*' => ($left instanceof RationalMoney && is_numeric($right)) || (is_numeric($left) && $right instanceof RationalMoney),
            '/' => $left instanceof RationalMoney && is_numeric($right),
            default => false,
        };
    }

    protected function addOrSubtract(mixed $left, mixed $right, string $operator): AbstractMoney
    {
        $left = $this->toRational(instance_of(AbstractMoney::class)->coerce($left));
        $right = instance_of(AbstractMoney::class)->coerce($right);

        return $operator === '+' ? $left->plus($right) : $left->minus($right);
    }
}
