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
final readonly class MinorMonetaryType implements Type
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
                ->orElse(fn() => attempt(fn() => Money::ofMinor(MoneyParser::exact($candidate), $this->currency))),
            default => attempt(function () use ($candidate) {
                $amount = union(float(), int())->assert($candidate);
                return Money::ofMinor(MoneyParser::exact($amount), $this->currency);
            }),
        })
            ->andThen(fn(Money $money) => $money->getCurrency()->isEqualTo($this->currency)
                ? Ok(Some($money))
                : Err(new InvalidArgumentException(sprintf("Mismatching currencies: expected %s, got %s", $this->currency->getCurrencyCode(), $money->getCurrency()->getCurrencyCode()))))
            ->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
    }

    /** Pence are dropped from a whole amount, as in {@see MonetaryType::format}. */
    public function format(mixed $value): string
    {
        return instance_of(Money::class)->assert($value)->formatToLocale('en_GB', allowWholeNumber: true);
    }

    /**
     * The same opaque `money` identity as {@see MonetaryType}: the two
     * differ only in how they read raw input at the boundary (minor units
     * vs. major), and a value of either is the same Money of the same
     * currency, so they share every operator rule.
     */
    public function shape(): Shape
    {
        return new OpaqueShape('money', [
            'currency' => new LiteralShape($this->currency->getCurrencyCode()),
        ]);
    }
}
