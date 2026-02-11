<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Operators;

use Brick\Money\Money;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Monads\Result\Result;
use Superscript\MonetaryInterval\MonetaryInterval;

use function Psl\Type\instance_of;
use function Superscript\Monads\Result\attempt;

final readonly class MonetaryIntervalOverloader implements OperatorOverloader
{
    public function supportsOverloading(mixed $left, mixed $right, string $operator): bool
    {
        return $left instanceof MonetaryInterval && $right instanceof Money && in_array($operator, ['>', '<', '>=', '<=']);
    }

    /**
     * Evaluates the comparison between two intervals based on the operator.
     *
     * @param  MonetaryInterval  $left  The left interval.
     * @param  Money  $right  The right money value.
     * @param  string  $operator  The operator to use for comparison.
     * @return Result<bool, \Throwable>
     */
    public function evaluate(mixed $left, mixed $right, string $operator): Result
    {
        return attempt(function () use ($left, $right, $operator) {
            instance_of(MonetaryInterval::class)->assert($left);
            instance_of(Money::class)->assert($right);

            return match ($operator) {
                '<' => $left->isLessThan($right),
                '<=' => $left->isLessThanOrEqualTo($right),
                '>' => $left->isGreaterThan($right),
                '>=' => $left->isGreaterThanOrEqualTo($right),
                default => throw new \InvalidArgumentException("Unsupported operator: $operator"),
            };
        });
    }
}
