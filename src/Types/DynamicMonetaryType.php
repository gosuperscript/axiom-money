<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Types;

use Brick\Money\Money;
use Superscript\Monads\Option\Option;
use Superscript\Monads\Result\Result;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Axiom\Money\MoneyParser;
use Superscript\Schema\Types\Type;

use function Psl\Type\non_empty_string;
use function Superscript\Monads\Option\Some;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * @implements Type<Money>
 */
final readonly class DynamicMonetaryType implements Type
{
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
        return MoneyParser::parse($value)->map(fn(Money $money) => Some($money))
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
