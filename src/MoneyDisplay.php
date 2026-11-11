<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money;

use Brick\Money\Formatter\MoneyLocaleFormatter;
use Brick\Money\Money;

/**
 * How a Money reads in prose: en_GB, with a whole amount dropping its pence,
 * so "£50,000" rather than "£50,000.00" while "£12.50" keeps them.
 *
 * Type descriptions format money on every refused operation, and a compiler
 * refuses many candidate rules before it finds one that applies, so the intl
 * formatter is built once per process: constructing one costs far more than
 * formatting with it. Reuse is safe because the formatter sets its fraction
 * digits on every call.
 */
final class MoneyDisplay
{
    public static function format(Money $money): string
    {
        /** @var MoneyLocaleFormatter|null $formatter */
        static $formatter = null;

        return ($formatter ??= new MoneyLocaleFormatter('en_GB', allowWholeNumber: true))->format($money);
    }
}
