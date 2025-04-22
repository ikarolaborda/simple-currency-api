<?php declare(strict_types=1);

namespace App\Message;

final class CurrencyRateChangedMessage
{
    public function __construct(
        private readonly string $baseCurrency,
        private readonly array  $changes
    ) {}

    public function getBaseCurrency(): string
    {
        return $this->baseCurrency;
    }

    public function getChanges(): array
    {
        return $this->changes;
    }
}