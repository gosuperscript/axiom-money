<?php

declare(strict_types=1);

namespace Superscript\Axiom\Money\Tests;

use Brick\Money\Money;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Superscript\Axiom\Money\MoneyDisplay;

#[CoversClass(MoneyDisplay::class)]
final class MoneyDisplayTest extends TestCase
{
    #[Test]
    public function a_whole_amount_drops_its_pence_and_the_next_amount_keeps_them(): void
    {
        // One formatter serves every call, so an amount formatted after a
        // whole one must not inherit its zero fraction digits.
        $this->assertSame('£50,000', MoneyDisplay::format(Money::of('50000', 'GBP')));
        $this->assertSame('£12.50', MoneyDisplay::format(Money::of('12.50', 'GBP')));
        $this->assertSame('€7', MoneyDisplay::format(Money::of('7', 'EUR')));
    }
}
