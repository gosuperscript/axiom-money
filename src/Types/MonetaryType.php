<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Types;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Currency;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use InvalidArgumentException;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Axiom\Types\Shapes\LiteralShape;
use Superscript\Axiom\Types\Shapes\OpaqueShape;
use Superscript\Axiom\Types\Shapes\Shape;
use Superscript\Axiom\Types\Type;

use function Psl\Type\float;
use function Psl\Type\int;
use function Psl\Type\instance_of;
use function Psl\Type\union;
use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\attempt;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * @implements Type<Money>
 */
final readonly class MonetaryType implements Type
{
    public function __construct(public Currency $currency, public RoundingMode $roundingMode = RoundingMode::HalfUp) {}

    /**
     * @return Result<Option<Money>, TransformValueException>
     */
    public function assert(mixed $value): Result
    {
        if (!$value instanceof Money) {
            return Err(new TransformValueException(type: 'money', value: $value));
        }

        if (!$value->getCurrency()->isEqualTo($this->currency)) {
            return Err(new TransformValueException(type: 'money', value: $value));
        }

        return Ok(Some($value));
    }

    /**
     * @return Result<Option<Money>, TransformValueException>
     */
    public function coerce(mixed $value): Result
    {
        $candidate = $value instanceof RationalMoney
            ? $value->toContext(new DefaultContext(), $this->roundingMode)
            : $value;

        return (match (true) {
            $candidate instanceof Money => Ok($candidate),
            is_string($candidate) => MoneyParser::parse($candidate)
                ->orElse(fn() => attempt(fn() => Money::of(MoneyParser::exact($candidate), $this->currency))),
            default => attempt(function () use ($candidate) {
                $amount = union(float(), int())->assert($candidate);
                return Money::of(MoneyParser::exact($amount), $this->currency);
            }),
        })
            ->andThen(fn(Money $money) => $money->getCurrency()->isEqualTo($this->currency)
                ? Ok(Some($money))
                : Err(new InvalidArgumentException(sprintf("Mismatching currencies: expected %s, got %s", $this->currency->getCurrencyCode(), $money->getCurrency()->getCurrencyCode()))))
            ->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
    }

    /**
     * A whole amount drops its pence, so it reads "£1,000" rather than
     * "£1,000.00"; an amount that has pence keeps them. Formatted amounts end
     * up in front of a customer — on a quote, in a document — where trailing
     * zeroes only add noise.
     */
    public function format(mixed $value): string
    {
        return instance_of(Money::class)->assert($value)->formatToLocale('en_GB', allowWholeNumber: true);
    }

    /**
     * Money is an object-valued domain type: an opaque `money` identity
     * parameterized by its currency. `Money<'GBP'>` is assignable to a
     * `Money<'GBP' | 'USD'>` slot and shares no values with `Money<'USD'>`,
     * all without a single relation rule mentioning money. The arithmetic,
     * ordering and equality it supports are contributed per currency by
     * {@see \Superscript\Axiom\Money\MoneyExtension}.
     */
    public function shape(): Shape
    {
        return new OpaqueShape('money', [
            'currency' => new LiteralShape($this->currency->getCurrencyCode()),
        ]);
    }
}
