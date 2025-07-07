<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Operators;

use Brick\Money\Money;
use Superscript\MonetaryInterval\MonetaryInterval;

use function Psl\Type\instance_of;

final readonly class MonetaryIntervalOverloader
{
    public function supportsOverloading(mixed $left, mixed $right, string $operator): bool
    {
        return $left instanceof MonetaryInterval && $right instanceof Money && in_array($operator, ['>', '<', '>=', '<=']);
    }

    /**
     * Evaluates the comparison between two intervals based on the operator.
     *
     * @param  MonetaryInterval  $left  The left interval.
     * @param  int|float  $right  The right interval.
     * @param  string  $operator  The operator to use for comparison.
     * @return bool Returns true or false based on the comparison.
     */
    public function evaluate(mixed $left, mixed $right, string $operator): mixed
    {
        instance_of(MonetaryInterval::class)->assert($left);
        instance_of(Money::class)->assert($right);

        return match ($operator) {
            '<' => $left->isLessThan($right),
            '<=' => $left->isLessThanOrEqualTo($right),
            '>' => $left->isGreaterThan($right),
            '>=' => $left->isGreaterThanOrEqualTo($right),
            default => throw new \InvalidArgumentException("Unsupported operator: $operator"),
        };
    }
}
