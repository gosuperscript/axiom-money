<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Types;

use Brick\Money\Currency;
use Brick\Money\Money;
use InvalidArgumentException;
use Superscript\Interval\Interval;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Superscript\MonetaryInterval\IntervalNotation;
use Superscript\MonetaryInterval\MonetaryInterval;
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Types\Shapes\LiteralShape;
use Superscript\Axiom\Types\Shapes\OpaqueShape;
use Superscript\Axiom\Types\Shapes\Shape;
use Superscript\Axiom\Types\Type;

use function Psl\Type\instance_of;
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

    /**
     * Reads as the band it is, not as the notation it was written in: a
     * formatted interval ends up in front of a customer — on a quote, in a
     * document — where "(GBP 50000.00,GBP 100000.00]" says nothing. Endpoint
     * openness is dropped, because prose has no natural way to say it and no
     * display has needed the distinction; cast the value itself when the exact
     * interval matters ({@see MonetaryInterval::__toString}).
     *
     * A half-bounded interval carries the PHP_INT_MIN/PHP_INT_MAX sentinel
     * {@see MonetaryInterval::fromString} writes for the endpoint that was left
     * out, so the missing side becomes "or more"/"up to" rather than printing
     * the sentinel as if it were a real amount.
     */
    public function format(mixed $value): string
    {
        $interval = instance_of(MonetaryInterval::class)->assert($value);

        $left = $interval->left->isEqualTo(PHP_INT_MIN) ? null : $interval->left;
        $right = $interval->right->isEqualTo(PHP_INT_MAX) ? null : $interval->right;

        return match (true) {
            $left !== null && $right !== null => sprintf('%s – %s', self::amount($left), self::amount($right)),
            $left !== null => sprintf('%s or more', self::amount($left)),
            $right !== null => sprintf('up to %s', self::amount($right)),
            default => 'any',
        };
    }

    /**
     * A whole endpoint drops its pence, so a band reads "£50,000 – £100,000"
     * rather than "£50,000.00 – £100,000.00"; an endpoint that has pence keeps
     * them — the same treatment a single amount gets in
     * {@see MonetaryType::format}.
     */
    private static function amount(Money $money): string
    {
        return $money->formatTo('en_GB', allowWholeNumber: true);
    }

    /**
     * An opaque `monetary-interval` identity parameterized by its currency,
     * mirroring {@see MonetaryType}. Its comparison against a Money of the
     * same currency is contributed per currency by
     * {@see \Superscript\Axiom\Money\MoneyExtension}.
     */
    public function shape(): Shape
    {
        return new OpaqueShape('monetary-interval', [
            'currency' => new LiteralShape($this->currency->getCurrencyCode()),
        ]);
    }
}
