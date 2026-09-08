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

        if (!$value->left->getCurrency()->isEqualTo($this->currency)) {
            return Err(new TransformValueException(type: 'monetary-interval', value: $value));
        }

        return Ok(Some($value));
    }

    /**
     * Reads a string in either notation an interval is written in: the
     * currency-carrying one a MonetaryInterval casts itself to
     * ("[GBP 0.00,GBP 50000.00]"), and the plain numeric one a configuration
     * authors ("[0,50000]"), whose endpoints take this type's currency.
     *
     * The first form is what a serialized value of this type looks like on the
     * wire, so it has to be readable: a caller that echoes a value back — the
     * documents endpoint does exactly that — must get it read, not rejected.
     *
     * @return Result<Option<MonetaryInterval>, TransformValueException>
     */
    public function coerce(mixed $value): Result
    {
        return (match (true) {
            $value instanceof MonetaryInterval => Ok($value),
            is_string($value) => attempt(fn() => MonetaryInterval::fromString($value))
                ->orElse(fn() => attempt(fn() => $this->fromNumericNotation($value))),
            default => Err(new TransformValueException(type: 'monetary-interval', value: $value)),
        })
            ->andThen(fn(MonetaryInterval $interval) => $interval->left->getCurrency()->isEqualTo($this->currency)
                ? Ok(Some($interval))
                : Err(new InvalidArgumentException(sprintf("Mismatching currencies: expected %s, got %s", $this->currency->getCurrencyCode(), $interval->left->getCurrency()->getCurrencyCode()))))
            ->mapErr(fn() => new TransformValueException(type: 'monetary-interval', value: $value));
    }

    /**
     * Endpoints of a numeric interval are whole units of this type's currency,
     * not minor ones: "[0,50000]" in GBP is nil to fifty thousand pounds.
     */
    private function fromNumericNotation(string $value): MonetaryInterval
    {
        $interval = Interval::fromString($value);

        return new MonetaryInterval(
            left: Money::of($interval->left->toInt(), $this->currency),
            right: Money::of($interval->right->toInt(), $this->currency),
            notation: IntervalNotation::from($interval->notation->value),
        );
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
        return $money->formatToLocale('en_GB', allowWholeNumber: true);
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
