<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money;

use Brick\Money\Money;
use InvalidArgumentException;
use Superscript\Monads\Result\Result;

use function Psl\Type\instance_of;
use function Psl\Type\non_empty_vec;
use function Superscript\Monads\Result\Err;
use function Superscript\Monads\Result\Ok;

/**
 * Proportional allocation and equal splitting of monetary amounts.
 *
 * A thin mirror of brick/money's `Money::allocate()` / `Money::split()`: allocation is exact in
 * minor units, any remainder is distributed over the earliest parts, and the parts always sum to
 * the original amount.
 */
final readonly class Allocation
{
    /**
     * Allocates a money proportionally to a list of integer ratios.
     *
     * @return Result<non-empty-list<Money>, InvalidArgumentException>
     */
    public static function allocate(Money $money, int ...$ratios): Result
    {
        try {
            return Ok(non_empty_vec(instance_of(Money::class))->coerce($money->allocate(...$ratios)));
        } catch (InvalidArgumentException $exception) {
            return Err($exception);
        }
    }

    /**
     * Splits a money into a number of equal parts.
     *
     * @return Result<non-empty-list<Money>, InvalidArgumentException>
     */
    public static function split(Money $money, int $parts): Result
    {
        try {
            return Ok(non_empty_vec(instance_of(Money::class))->coerce($money->split($parts)));
        } catch (InvalidArgumentException $exception) {
            return Err($exception);
        }
    }
}
