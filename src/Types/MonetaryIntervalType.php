<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Types;

use Brick\Money\Currency;
use Brick\Money\Money;
use Superscript\Interval\Interval;
use Superscript\Monads\Option\Some;
use Superscript\Monads\Result\Result;
use Superscript\MonetaryInterval\IntervalNotation;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Schema\Types\Type;

use function Psl\Type\string;
use function Superscript\Monads\Result\attempt;

/**
 * @implements Type<MonetaryInterval>
 */
final readonly class MonetaryIntervalType implements Type
{
    public function __construct(public Currency $currency) {}

    public function transform(mixed $value): Result
    {
        return attempt(fn() => Interval::fromString(string()->assert($value)))
            ->map(fn(Interval $interval) => new MonetaryInterval(
                left: Money::of($interval->left->toInt(), $this->currency),
                right: Money::of($interval->right->toInt(), $this->currency),
                notation: IntervalNotation::from($interval->notation->value),
            ))
            ->map(fn(MonetaryInterval $interval) => new Some($interval))
            ->mapErr(fn() => new TransformValueException(type: 'monetary-interval', value: $value));
    }

    public function compare(mixed $a, mixed $b): bool
    {
        return $a->isEqualTo($b);
    }

    public function format(mixed $value): string
    {
        return (string) $value;
    }
}
