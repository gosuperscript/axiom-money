<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Types;

use Brick\Money\Money;
use Superscript\Monads\Option\Some;
use Superscript\Monads\Result\Result;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Schema\Money\MoneyParser;
use Superscript\Schema\Types\Type;

use function Psl\Type\non_empty_string;

/**
 * @implements Type<Money>
 */
final readonly class MoneyType implements Type
{
    public function transform(mixed $value): Result
    {
        return MoneyParser::parse($value)->map(fn(Money $money) => new Some($money))
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
