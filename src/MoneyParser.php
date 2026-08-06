<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Money;
use InvalidArgumentException;
use NumberFormatter;
use SebastianBergmann\Exporter\Exporter;
use Superscript\Monads\Result\Result;

use function Psl\Type\non_empty_string;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * @return Result<Money, InvalidArgumentException>
 */
class MoneyParser
{
    /**
     * @return Result<Money, InvalidArgumentException>
     */
    public static function parse(mixed $money): Result
    {
        if ($money instanceof Money) {
            return Ok($money);
        }

        if (is_string($money)) {
            preg_match('/(?P<currency>[A-Z]{3}) (?P<amount>\d+(?:\.\d+)?)\b/', $money, $matches);

            if (!empty($matches)) {
                try {
                    return Ok(Money::of(amount: $matches['amount'], currency: non_empty_string()->assert($matches['currency'])));
                } catch (RoundingNecessaryException $exception) {
                    return Err(new InvalidArgumentException(sprintf('Could not parse [%s] as money', new Exporter()->shortenedExport($money)), previous: $exception));
                }
            }

            $formatter = new NumberFormatter('en_GB', NumberFormatter::CURRENCY);
            $currency = '';

            $amount = $formatter->parseCurrency($money, $currency);

            if ($amount !== false) {
                try {
                    return Ok(Money::of(self::exact($amount), non_empty_string()->assert($currency)));
                } catch (RoundingNecessaryException $exception) {
                    return Err(new InvalidArgumentException(sprintf('Could not parse [%s] as money', new Exporter()->shortenedExport($money)), previous: $exception));
                }
            }
        }

        return Err(new InvalidArgumentException(
            message: sprintf('Could not parse [%s] as money', new Exporter()->shortenedExport($money)),
        ));
    }

    /**
     * Renders a raw scalar amount in the form brick accepts.
     *
     * brick rejects floats throughout its monetary and arithmetic APIs, because
     * a binary float carries no exact decimal value to compute with: the literal
     * 0.1 is not one tenth. A float that reaches here is therefore spelled as
     * the shortest decimal that round-trips back to it, so brick receives the
     * number the caller actually holds.
     *
     * That spelling is `json_encode`'s, because it honours `serialize_precision`
     * (-1). A `(string)` cast honours `precision` instead — 14 significant
     * digits by default — so it truncates and can emit exponent notation:
     * `(string) 123456789012345.67` gives `1.2345678901235E+14`, a different
     * amount. Ints and numeric strings are already exact and pass through
     * untouched.
     *
     * @throws InvalidArgumentException if the float is not finite, and so names no decimal at all
     */
    public static function exact(string|float|int $amount): string|int
    {
        if (! is_float($amount)) {
            return $amount;
        }

        if (! is_finite($amount)) {
            throw new InvalidArgumentException(sprintf('[%s] is not a monetary amount.', $amount));
        }

        return json_encode($amount, JSON_THROW_ON_ERROR);
    }
}
