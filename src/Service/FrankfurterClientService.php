<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use App\Contracts\ExchangeRateClientInterface;
use JsonException;

readonly class FrankfurterClientService implements ExchangeRateClientInterface
{

    public function __construct(
        private HttpClientInterface $http,
        private string              $apiBase = 'https://api.frankfurter.app'
    ) {}

    /**
     * {}
     * @throws TransportExceptionInterface
     */
    public function fetchRates(string $base, array $targets): array
    {
        $to = implode(',', $targets);

        $response = $this->http->request('GET', sprintf('%s/latest', $this->apiBase), [
            'query' => [
                'from' => $base,
                'to'   => $to,
            ],
        ]);

        try {
            $data = $response->toArray();
        } catch (ClientExceptionInterface|DecodingExceptionInterface|RedirectionExceptionInterface|ServerExceptionInterface|TransportExceptionInterface $e) {

        }

        return $data['rates'] ?? [];
    }
}