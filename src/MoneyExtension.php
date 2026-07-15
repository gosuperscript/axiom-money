<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money;

use Brick\Math\RoundingMode;
use Brick\Money\Context\DefaultContext;
use Brick\Money\Currency;
use Brick\Money\Money;
use Superscript\Axiom\Extension;
use Superscript\Axiom\Money\Types\MonetaryIntervalType;
use Superscript\Axiom\Money\Types\MonetaryType;
use Superscript\Axiom\Operators\Operator;
use Superscript\Axiom\Types\BooleanType;
use Superscript\Axiom\Types\NumberType;
use Superscript\Axiom\Types\Type;
use Superscript\MonetaryInterval\MonetaryInterval;

use function Psl\Type\instance_of;
use function Superscript\Monads\Result\attempt;

/**
 * The money package's contribution to a {@see \Superscript\Axiom\Dialect}.
 *
 * Money's typing is *parameterized* by currency — `Money<'GBP'> + Money<'GBP'>`
 * is money, `Money<'GBP'> + Money<'USD'>` must be refused — and a signature's
 * return type is fixed, so the rules are declared by **enumeration over the
 * host's configured currencies**. Same-currency pairs resolve to their row;
 * a cross-currency pair matches no row and the compiler refuses it by name.
 * The currency set is host-finite at composition time:
 *
 * ```php
 * $dialect = Dialect::core()->with(new MoneyExtension(['GBP', 'USD', 'EUR']));
 * ```
 *
 * Scalar multiplication and division carry the money into Brick's exact
 * rational domain and round back to the currency's scale with the extension's
 * rounding mode — the result is a `Money` of the same currency, not a rational
 * intermediate: each operator has one honest return type. Division by zero is a
 * value-dependent error, returned as an `Err`, not a compile-time refusal.
 *
 * Equality is the package's own: core refuses `==` on opaque operands, so the
 * aliases are declared here over same-currency money and over monetary
 * intervals, binding Brick's domain comparison rather than object identity.
 */
final class MoneyExtension extends Extension
{
    /**
     * @param non-empty-list<string> $currencies the host's configured currency codes
     */
    public function __construct(
        private readonly array $currencies,
        private readonly RoundingMode $roundingMode = RoundingMode::HALF_UP,
    ) {}

    public function operators(): array
    {
        $number = new NumberType();
        $boolean = new BooleanType();
        $rules = [];

        foreach ($this->currencies as $code) {
            $money = new MonetaryType(Currency::of($code));
            $interval = new MonetaryIntervalType(Currency::of($code));

            // Addition and subtraction: same currency in, same currency out.
            $rules[] = Operator::infix('+')->signature($money, $money)->returns($money)
                ->evaluate(fn(Money $left, Money $right) => $left->plus($right));
            $rules[] = Operator::infix('-')->signature($money, $money)->returns($money)
                ->evaluate(fn(Money $left, Money $right) => $left->minus($right));

            // Scalar multiplication (either order) and division: exact in the
            // rational domain, rounded back to a Money of the same currency.
            $rules[] = Operator::infix('*')->signature($money, $number)->returns($money)
                ->evaluate(fn(Money $left, int|float $right) => $this->scale($left, $right));
            $rules[] = Operator::infix('*')->signature($number, $money)->returns($money)
                ->evaluate(fn(int|float $left, Money $right) => $this->scale($right, $left));
            $rules[] = Operator::infix('/')->signature($money, $number)->returns($money)
                ->evaluate(fn(Money $left, int|float $right) => attempt(
                    fn() => $left->toRational()->dividedBy($right)->to(new DefaultContext(), $this->roundingMode),
                ));

            // Ordering: same currency.
            $rules[] = Operator::infix('<')->signature($money, $money)->returns($boolean)
                ->evaluate(fn(Money $left, Money $right) => $left->isLessThan($right));
            $rules[] = Operator::infix('<=')->signature($money, $money)->returns($boolean)
                ->evaluate(fn(Money $left, Money $right) => $left->isLessThanOrEqualTo($right));
            $rules[] = Operator::infix('>')->signature($money, $money)->returns($boolean)
                ->evaluate(fn(Money $left, Money $right) => $left->isGreaterThan($right));
            $rules[] = Operator::infix('>=')->signature($money, $money)->returns($boolean)
                ->evaluate(fn(Money $left, Money $right) => $left->isGreaterThanOrEqualTo($right));

            // Equality: Brick's amount-and-currency comparison, not identity.
            foreach (['=', '==', '==='] as $operator) {
                $rules[] = Operator::infix($operator)->signature($money, $money)->returns($boolean)
                    ->evaluate(fn(Money $left, Money $right) => $left->isAmountAndCurrencyEqualTo($right));
            }
            foreach (['!=', '!=='] as $operator) {
                $rules[] = Operator::infix($operator)->signature($money, $money)->returns($boolean)
                    ->evaluate(fn(Money $left, Money $right) => !$left->isAmountAndCurrencyEqualTo($right));
            }

            // A monetary interval compared against a money of its currency.
            $rules[] = Operator::infix('<')->signature($interval, $money)->returns($boolean)
                ->evaluate(fn(MonetaryInterval $left, Money $right) => $left->isLessThan($right));
            $rules[] = Operator::infix('<=')->signature($interval, $money)->returns($boolean)
                ->evaluate(fn(MonetaryInterval $left, Money $right) => $left->isLessThanOrEqualTo($right));
            $rules[] = Operator::infix('>')->signature($interval, $money)->returns($boolean)
                ->evaluate(fn(MonetaryInterval $left, Money $right) => $left->isGreaterThan($right));
            $rules[] = Operator::infix('>=')->signature($interval, $money)->returns($boolean)
                ->evaluate(fn(MonetaryInterval $left, Money $right) => $left->isGreaterThanOrEqualTo($right));

            // Equality between two monetary intervals of the same currency.
            foreach (['=', '==', '==='] as $operator) {
                $rules[] = Operator::infix($operator)->signature($interval, $interval)->returns($boolean)
                    ->evaluate(fn(MonetaryInterval $left, MonetaryInterval $right) => $left->isEqualTo($right));
            }
            foreach (['!=', '!=='] as $operator) {
                $rules[] = Operator::infix($operator)->signature($interval, $interval)->returns($boolean)
                    ->evaluate(fn(MonetaryInterval $left, MonetaryInterval $right) => !$left->isEqualTo($right));
            }
        }

        return $rules;
    }

    /**
     * @return array<class-string, callable(object): Type>
     */
    public function literals(): array
    {
        return [
            Money::class => fn(object $value) => new MonetaryType(instance_of(Money::class)->assert($value)->getCurrency()),
            MonetaryInterval::class => fn(object $value) => new MonetaryIntervalType(instance_of(MonetaryInterval::class)->assert($value)->left->getCurrency()),
        ];
    }

    private function scale(Money $money, int|float $scalar): Money
    {
        return $money->toRational()->multipliedBy($scalar)->to(new DefaultContext(), $this->roundingMode);
    }
}
