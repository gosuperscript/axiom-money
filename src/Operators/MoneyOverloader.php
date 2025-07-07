<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Operators;

use Brick\Money\Money;
use Superscript\Schema\Operators\OperatorOverloader;

use function Psl\Type\instance_of;

final readonly class MoneyOverloader implements OperatorOverloader
{
    public function supportsOverloading(mixed $left, mixed $right, string $operator): bool
    {
        return $left instanceof Money && $right instanceof Money && in_array($operator, ['+', '-', '*', '/', '==', '!=', '<', '>', '<=', '>='], true);
    }

    public function evaluate(mixed $left, mixed $right, string $operator): mixed
    {
        instance_of(Money::class)->assert($left);
        instance_of(Money::class)->assert($right);

        return match ($operator) {
            '+' => $left->plus($right),
            '-' => $left->minus($right),
            '*' => $left->multipliedBy($right->getAmount()->toFloat()),
            '/' => $left->dividedBy($right->getAmount()->toFloat()),
            '==' => $left->isEqualTo($right),
            '!=' => !$left->isEqualTo($right),
            '<' => $left->isLessThan($right),
            '>' => $left->isGreaterThan($right),
            '<=' => $left->isLessThanOrEqualTo($right),
            '>=' => $left->isGreaterThanOrEqualTo($right),
            default => throw new \InvalidArgumentException("Unsupported operator: {$operator}"),
        };
    }
}
