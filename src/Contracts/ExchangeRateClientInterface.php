<?php

namespace App\Contracts;
interface ExchangeRateClientInterface
{
    public function fetchRates(string $base, array $targets): array;
}
