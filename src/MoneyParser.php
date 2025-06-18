<?php

declare(strict_types=1);

namespace Superscript\Schema\Money;

use Brick\Math\Exception\RoundingNecessaryException;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use InvalidArgumentException;
use NumberFormatter;
use SebastianBergmann\Exporter\Exporter;
use Superscript\Monads\Result\Err;
use Superscript\Monads\Result\Ok;
use Superscript\Monads\Result\Result;

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
            return new Ok($money);
        }

        if ($money instanceof RationalMoney) {
            try {
                return new Ok($money->to(new DefaultContext()));
            } catch (RoundingNecessaryException $exception) {
                return Err(new InvalidArgumentException(sprintf('Fractional money [%s] cannot be parsed to simple Money because it would lose precision', (new Exporter())->shortenedExport($money)), previous: $exception));
            }
        }

        if (is_numeric($money)) {
            return Err(new InvalidArgumentException(sprintf('Numeric without currency [%s] cannot be parsed as money.', $money)));
        }

        if (is_string($money)) {
            \Safe\preg_match('/(?P<currency>[A-Z]{3}) (?P<amount>\d+(?:\.\d{1,2})?)\b/', $money, $matches);
            if (!empty($matches['currency']) && !empty($matches['amount'])) {
                return Ok(Money::of(amount: $matches['amount'], currency: $matches['currency']));
            }
            $formatter = new NumberFormatter('en_GB', NumberFormatter::CURRENCY);
            $currency = '';

            $amount = $formatter->parseCurrency($money, $currency);

            if ($amount !== false) {
                try {
                    return Ok(Money::of($amount, $currency));
                } catch (RoundingNecessaryException $exception) {
                    return Err(new InvalidArgumentException(sprintf('Could not parse [%s] without rounding', new Exporter()->shortenedExport($money)), previous: $exception));
                }
            }
        }

        return new Err(new InvalidArgumentException(
            message: sprintf('Could not parse [%s] as money', new Exporter()->shortenedExport($money)),
        ));
    }
}