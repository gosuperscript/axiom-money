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
use Superscript\Axiom\Types\Shapes\LiteralShape;
use Superscript\Axiom\Types\Shapes\OpaqueShape;
use Superscript\Axiom\Types\Shapes\Shape;
use Superscript\Axiom\Types\Type;

use function Psl\Type\float;
use function Psl\Type\int;
use function Psl\Type\non_empty_string;
use function Psl\Type\string;
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
    public function __construct(public Currency $currency, public RoundingMode $roundingMode = RoundingMode::HALF_UP) {}

    /**
     * @return Result<Option<Money>, TransformValueException>
     */
    public function assert(mixed $value): Result
    {
        if (!$value instanceof Money) {
            return Err(new TransformValueException(type: 'money', value: $value));
        }

        if (!$value->getCurrency()->is($this->currency)) {
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
            ? $value->to(new DefaultContext(), $this->roundingMode)
            : $value;

        return (match (true) {
            $candidate instanceof Money => $candidate->getCurrency()->is($this->currency)
                ? Ok($candidate)
                : Err(new InvalidArgumentException(sprintf("Mismatching currencies: expected %s, got %s", $this->currency->getCurrencyCode(), $candidate->getCurrency()->getCurrencyCode()))),
            default => attempt(function () use ($candidate) {
                union(string(), float(), int())->assert($candidate);
                return Money::of($candidate, $this->currency);
            }),
        })
            ->map(fn(Money $money) => Some($money))
            ->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
    }

    public function format(mixed $value): string
    {
        $formatter = new \NumberFormatter('en_GB', \NumberFormatter::CURRENCY);
        $result = $formatter->formatCurrency($value->getAmount()->toFloat(), $value->getCurrency()->getCurrencyCode());
        return non_empty_string()->assert($result);
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
