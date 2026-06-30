<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Operators;

use Brick\Money\AbstractMoney;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Monads\Result\Result;

use function Psl\Type\instance_of;
use function Psl\Type\num;
use function Superscript\Monads\Result\attempt;

final readonly class MoneyOverloader implements OperatorOverloader
{
    public function supportsOverloading(mixed $left, mixed $right, string $operator): bool
    {
        return match ($operator) {
            '+', '-', '==', '!=', '<', '>', '<=', '>=' => $left instanceof AbstractMoney && $right instanceof AbstractMoney,
            '*' => ($left instanceof AbstractMoney && is_numeric($right)) || (is_numeric($left) && $right instanceof AbstractMoney),
            '/' => $left instanceof AbstractMoney && is_numeric($right),
            default => false,
        };
    }

    public function evaluate(mixed $left, mixed $right, string $operator): Result
    {
        return attempt(function () use ($left, $right, $operator) {
            return match ($operator) {
                '*' => $this->multiply($left, $right),
                '/' => $this->divide($left, $right),
                '+', '-' => $this->addOrSubtract($left, $right, $operator),
                '==', '!=', '<', '>', '<=', '>=' => $this->compare($left, $right, $operator),
                default => throw new \InvalidArgumentException("Unsupported operator: {$operator}"),
            };
        });
    }

    /**
     * Multiplication by a numeric scalar; the money operand may be on either side. The result is
     * an exact RationalMoney so no precision is lost — collapse it with ->to($context,
     * $roundingMode) for a fixed-scale Money.
     */
    private function multiply(mixed $left, mixed $right): RationalMoney
    {
        [$money, $scalar] = $left instanceof AbstractMoney ? [$left, $right] : [$right, $left];

        return $this->toRational(instance_of(AbstractMoney::class)->coerce($money))->multipliedBy(num()->coerce($scalar));
    }

    /**
     * Division of a money by a numeric scalar (Money / number). Returns an exact RationalMoney;
     * collapse it with ->to($context, $roundingMode) for a fixed-scale Money.
     */
    private function divide(mixed $left, mixed $right): RationalMoney
    {
        return $this->toRational(instance_of(AbstractMoney::class)->coerce($left))->dividedBy(num()->coerce($right));
    }

    /**
     * Addition/subtraction. "Rational is contagious": Money + Money stays Money, but if either
     * operand is a RationalMoney the calculation is carried out — and returned — in the exact
     * rational domain.
     */
    private function addOrSubtract(mixed $left, mixed $right, string $operator): AbstractMoney
    {
        instance_of(AbstractMoney::class)->assert($left);
        instance_of(AbstractMoney::class)->assert($right);

        if ($left instanceof RationalMoney || $right instanceof RationalMoney) {
            $left = $this->toRational($left);

            return $operator === '+' ? $left->plus($right) : $left->minus($right);
        }

        $left = instance_of(Money::class)->coerce($left);

        return $operator === '+' ? $left->plus($right) : $left->minus($right);
    }

    private function compare(mixed $left, mixed $right, string $operator): bool
    {
        instance_of(AbstractMoney::class)->assert($left);
        instance_of(AbstractMoney::class)->assert($right);

        return match ($operator) {
            '==' => $left->isEqualTo($right),
            '!=' => !$left->isEqualTo($right),
            '<' => $left->isLessThan($right),
            '>' => $left->isGreaterThan($right),
            '<=' => $left->isLessThanOrEqualTo($right),
            '>=' => $left->isGreaterThanOrEqualTo($right),
            default => throw new \InvalidArgumentException("Unsupported operator: {$operator}"),
        };
    }

    private function toRational(AbstractMoney $money): RationalMoney
    {
        return $money instanceof RationalMoney ? $money : instance_of(Money::class)->coerce($money)->toRational();
    }
}
