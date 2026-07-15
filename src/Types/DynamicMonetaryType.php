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

use function Psl\Type\non_empty_string;
use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * @implements Type<Money>
 */
final readonly class DynamicMonetaryType implements Type
{
    public function __construct(public RoundingMode $roundingMode = RoundingMode::HALF_UP) {}

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
            return Ok(Some($value->to(new DefaultContext(), $this->roundingMode)));
        }

        return MoneyParser::parse($value)->map(fn(Money $money) => Some($money))
            ->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
    }

    public function format(mixed $value): string
    {
        $formatter = new \NumberFormatter('en_GB', \NumberFormatter::CURRENCY);
        $result = $formatter->formatCurrency($value->getAmount()->toFloat(), $value->getCurrency()->getCurrencyCode());
        return non_empty_string()->assert($result);
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
