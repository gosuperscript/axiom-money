<?php

declare(strict_types=1);

namespace Superscript\Schema\Money\Types;

use Brick\Money\Currency;
use Brick\Money\Money;
use Superscript\Monads\Option\Some;
use Superscript\Monads\Result\Result;
use Superscript\Schema\Exceptions\TransformValueException;
use Superscript\Schema\Money\MoneyParser;
use Superscript\Schema\Types\Type;

use function Psl\Type\float;
use function Psl\Type\int;
use function Psl\Type\non_empty_string;
use function Psl\Type\string;
use function Psl\Type\union;
use function Superscript\Monads\Result\attempt;

/**
 * @implements Type<Money>
 */
final readonly class MonetaryType implements Type
{
    public function __construct(public Currency $currency)
    {
    }

    public function transform(mixed $value): Result
    {
        return attempt(function () use ($value) {
            union(string(), float(), int())->assert($value);
            return Money::of($value, $this->currency);
        })->map(fn(Money $money) => new Some($money))->mapErr(fn() => new TransformValueException(type: 'money', value: $value));
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
