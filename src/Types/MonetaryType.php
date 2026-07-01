<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Types;

use Brick\Money\AbstractMoney;
use Brick\Money\Currency;
use Brick\Money\Money;
use InvalidArgumentException;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Superscript\Axiom\Exceptions\TransformValueException;
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
 * @implements Type<AbstractMoney>
 */
final readonly class MonetaryType implements Type
{
    public function __construct(public Currency $currency) {}

    /**
     * @return Result<Option<AbstractMoney>, TransformValueException>
     */
    public function assert(mixed $value): Result
    {
        if (!$value instanceof AbstractMoney) {
            return Err(new TransformValueException(type: 'money', value: $value));
        }

        if (!$value->getCurrency()->is($this->currency)) {
            return Err(new TransformValueException(type: 'money', value: $value));
        }

        return Ok(Some($value));
    }

    /**
     * @return Result<Option<AbstractMoney>, TransformValueException>
     */
    public function coerce(mixed $value): Result
    {
        // A RationalMoney (the exact, unrounded result of an expression such as `salary / 12`) is
        // accepted alongside Money and passed through unchanged; rounding to a fixed-scale amount
        // is deferred to the output boundary (e.g. format()), not forced here.
        return (match (true) {
            $value instanceof AbstractMoney => $value->getCurrency()->is($this->currency)
                ? Ok($value)
                : Err(new InvalidArgumentException(sprintf("Mismatching currencies: expected %s, got %s", $this->currency->getCurrencyCode(), $value->getCurrency()->getCurrencyCode()))),
            default => attempt(function () use ($value) {
                union(string(), float(), int())->assert($value);
                return Money::of($value, $this->currency);
            }),
        })
            ->map(fn(AbstractMoney $money) => Some($money))
            ->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
    }

    public function compare(mixed $a, mixed $b): bool
    {
        return $a->isAmountAndCurrencyEqualTo($b);
    }

    public function format(mixed $value): string
    {
        $formatter = new \NumberFormatter('en_GB', \NumberFormatter::CURRENCY);
        $result = $formatter->formatCurrency($value->getAmount()->toFloat(), $value->getCurrency()->getCurrencyCode());
        return non_empty_string()->assert($result);
    }
}
