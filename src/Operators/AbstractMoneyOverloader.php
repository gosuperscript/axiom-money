<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Operators;

use Brick\Money\AbstractMoney;
use Brick\Money\Money;
use Brick\Money\RationalMoney;
use InvalidArgumentException;
use Superscript\Axiom\Operators\OperatorOverloader;
use Superscript\Monads\Result\Result;

use function Psl\Type\instance_of;
use function Psl\Type\num;
use function Psl\Type\numeric_string;
use function Superscript\Monads\Result\attempt;

/**
 * Shared arithmetic/comparison behaviour for the money overloaders.
 *
 * Multiplication and division always return an exact {@see RationalMoney} so precision is never
 * lost mid-chain; comparisons return a bool. The only behaviour that differs between concrete
 * overloaders is which operands they claim ({@see supportsOverloading()}) and how addition and
 * subtraction combine them ({@see addOrSubtract()}).
 */
abstract readonly class AbstractMoneyOverloader implements OperatorOverloader
{
    abstract public function supportsOverloading(mixed $left, mixed $right, string $operator): bool;

    public function evaluate(mixed $left, mixed $right, string $operator): Result
    {
        return attempt(function () use ($left, $right, $operator) {
            return match ($operator) {
                '*' => $this->multiply($left, $right),
                '/' => $this->divide($left, $right),
                '+', '-' => $this->addOrSubtract($left, $right, $operator),
                '==', '!=', '<', '>', '<=', '>=' => $this->compare($left, $right, $operator),
                default => throw new InvalidArgumentException("Unsupported operator: {$operator}"),
            };
        });
    }

    abstract protected function addOrSubtract(mixed $left, mixed $right, string $operator): AbstractMoney;

    private function multiply(mixed $left, mixed $right): RationalMoney
    {
        [$money, $scalar] = $left instanceof AbstractMoney ? [$left, $right] : [$right, $left];

        return $this->toRational(instance_of(AbstractMoney::class)->coerce($money))->multipliedBy($this->toNumber($scalar));
    }

    private function divide(mixed $left, mixed $right): RationalMoney
    {
        return $this->toRational(instance_of(AbstractMoney::class)->coerce($left))->dividedBy($this->toNumber($right));
    }

    /**
     * @param  '=='|'!='|'<'|'>'|'<='|'>='  $operator
     */
    private function compare(mixed $left, mixed $right, string $operator): bool
    {
        instance_of(AbstractMoney::class)->assert($left);
        instance_of(AbstractMoney::class)->assert($right);

        return match ($operator) {
            '==' => $left->isEqualTo($right),
            '!=' => !$left->isEqualTo($right),
            '<' => $left->isLessThan($right),
            '>' => $left->isGreaterThan($right),
            '<=' => $left->isLessThanOrEqualTo($right),
            '>=' => $left->isGreaterThanOrEqualTo($right),
        };
    }

    protected function toRational(AbstractMoney $money): RationalMoney
    {
        return $money instanceof RationalMoney ? $money : instance_of(Money::class)->coerce($money)->toRational();
    }

    /**
     * Coerces a numeric operand to a value Brick accepts. A numeric string is trimmed (is_numeric()
     * tolerates surrounding whitespace that Brick's parser rejects) and passed through verbatim so
     * Brick parses it exactly, rather than via a float that would cap precision at ~14 digits.
     */
    private function toNumber(mixed $value): int|float|string
    {
        return is_string($value) ? numeric_string()->coerce(trim($value)) : num()->coerce($value);
    }
}
