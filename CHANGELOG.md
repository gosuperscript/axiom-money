# Changelog

All notable changes to this project will be documented in this file.

## [0.5.0] - 2026-06-30

### Changed (BREAKING)

- `MoneyOverloader`: multiplication (`*`) and division (`/`) now require **exactly one numeric
  operand**. Multiplying or dividing two `Money` values (previously `100 EUR * 50 EUR =>
  5000 EUR`) is dimensionally meaningless and is no longer supported. The numeric operand may be
  on either side for `*`; division must be `Money / number`.
- `*` and `/` now return a `Brick\Money\RationalMoney` (an exact rational amount) instead of a
  `Money`, so no precision is lost on inexact results such as `10 USD / 3`. Collapse the result
  to a spendable `Money` with `->to($context, $roundingMode)` when you need a fixed-scale value.
- The operators accept `Brick\Money\AbstractMoney` (either `Money` or `RationalMoney`) so that a
  `RationalMoney` result can chain into further expressions. Addition/subtraction follow a
  "rational is contagious" rule: `Money + Money` stays `Money`; if either operand is a
  `RationalMoney`, the result is a `RationalMoney`.

### Migration

- Replace `evaluate($money, $money, '*'|'/')` with a numeric operand: `evaluate($money, 2, '*')`.
- If you consumed the result of `*`/`/` as a `Money`, it is now a `RationalMoney`; call
  `$result->to($context, $roundingMode)` (e.g. `new DefaultContext()`, `RoundingMode::HALF_UP`)
  to obtain a `Money`.
- `MonetaryIntervalOverloader` still compares an interval against a `Money` only; a
  `RationalMoney` must be collapsed with `->to(...)` before comparing it against an interval.
