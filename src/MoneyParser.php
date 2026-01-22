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
            \Safe\preg_match('/(?P<currency>[A-Z]{3}) (?P<amount>\d+(?:\.\d+)?)\b/', $money, $matches);

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
                    return Ok(Money::of($amount, non_empty_string()->assert($currency)));
                } catch (RoundingNecessaryException $exception) {
                    return Err(new InvalidArgumentException(sprintf('Could not parse [%s] as money', new Exporter()->shortenedExport($money)), previous: $exception));
                }
            }
        }

        return Err(new InvalidArgumentException(
            message: sprintf('Could not parse [%s] as money', new Exporter()->shortenedExport($money)),
        ));
    }
}
