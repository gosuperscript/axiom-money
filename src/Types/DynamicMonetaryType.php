<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Types;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Superscript\Axiom\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Axiom\Types\Shapes\OpaqueShape;
use Superscript\Axiom\Types\Shapes\Shape;
use Superscript\Axiom\Types\Type;

use function Psl\Type\instance_of;
use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * @implements Type<Money>
 */
final readonly class DynamicMonetaryType implements Type
{
    public function __construct(public RoundingMode $roundingMode = RoundingMode::HalfUp) {}

    /**
     * @return Result<Option<Money>, TransformValueException>
     */
    public function assert(mixed $value): Result
    {
        if (!$value instanceof Money) {
            return Err(new TransformValueException(type: 'money', value: $value));
        }

        return Ok(Some($value));
    }

    /**
     * @return Result<Option<Money>, TransformValueException>
     */
    public function coerce(mixed $value): Result
    {
        if ($value instanceof RationalMoney) {
            return Ok(Some($value->toContext(new DefaultContext(), $this->roundingMode)));
        }

        return MoneyParser::parse($value)->map(fn(Money $money) => Some($money))
            ->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
    }

    /** Pence are dropped from a whole amount, as in {@see MonetaryType::format}. */
    public function format(mixed $value): string
    {
        return instance_of(Money::class)->assert($value)->formatToLocale('en_GB', allowWholeNumber: true);
    }

    /**
     * An opaque `money` with no currency parameter: it admits any currency
     * at the boundary, so its currency is not statically known. That makes
     * it a coercion/boundary type only — it is deliberately *not* assignable
     * to the currency-parameterized `Money<C>` the arithmetic and comparison
     * rules resolve for (opaque relation requires matching parameter lists),
     * so declare a concrete {@see MonetaryType} where you need operators.
     */
    public function shape(): Shape
    {
        return new OpaqueShape('money');
    }
}
