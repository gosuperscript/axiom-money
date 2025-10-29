<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Types;

use Brick\Money\Currency;
use Brick\Money\Money;
use InvalidArgumentException;
use Superscript\Interval\Interval;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Superscript\MonetaryInterval\IntervalNotation;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Schema\Types\Type;

use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\attempt;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * @implements Type<MonetaryInterval>
 */
final readonly class MonetaryIntervalType implements Type
{
    public function __construct(public Currency $currency) {}

    /**
     * @return Result<Option<MonetaryInterval>, TransformValueException>
     */
    public function assert(mixed $value): Result
    {
        if (!$value instanceof MonetaryInterval) {
            return Err(new TransformValueException(type: 'monetary-interval', value: $value));
        }

        if (!$value->left->getCurrency()->is($this->currency)) {
            return Err(new TransformValueException(type: 'monetary-interval', value: $value));
        }

        return Ok(Some($value));
    }

    /**
     * @return Result<Option<MonetaryInterval>, TransformValueException>
     */
    public function coerce(mixed $value): Result
    {
        return (match (true) {
            $value instanceof MonetaryInterval => $value->left->getCurrency()->is($this->currency)
                ? Ok($value)
                : Err(new InvalidArgumentException(sprintf("Mismatching currencies: expected %s, got %s", $this->currency->getCurrencyCode(), $value->left->getCurrency()->getCurrencyCode()))),
            is_string($value) => attempt(fn() => Interval::fromString($value))
                ->map(fn(Interval $interval) => new MonetaryInterval(
                    left: Money::of($interval->left->toInt(), $this->currency),
                    right: Money::of($interval->right->toInt(), $this->currency),
                    notation: IntervalNotation::from($interval->notation->value),
                )),
            default => Err(new TransformValueException(type: 'monetary-interval', value: $value)),
        })
            ->map(fn(MonetaryInterval $interval) => Some($interval))
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
