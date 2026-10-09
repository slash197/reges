<?php

declare(strict_types=1);

namespace slash197\Reges\Tests\Support;

use Psr\Clock\ClockInterface;

final class FixedClock implements ClockInterface
{
    public function __construct(private \DateTimeImmutable $now)
    {
    }

    public function now(): \DateTimeImmutable
    {
        return $this->now;
    }

    public function advance(string $modifier): void
    {
        $this->now = $this->now->modify($modifier);
    }
}
