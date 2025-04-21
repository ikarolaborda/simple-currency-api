<?php

namespace App\Tests\Service;

use App\Service\FrankfurterClientService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

class FrankfurterClientServiceTest extends TestCase
{
    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function testFetchRatesSuccess(): void
    {
        $ratesData = ['rates' => ['USD' => 1.2, 'GBP' => 0.8]];

        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn($ratesData);

        $http = $this->createMock(HttpClientInterface::class);
        $http->expects($this->once())
            ->method('request')
            ->with('GET', 'https://api.frankfurter.app/latest', ['query' => ['from' => 'EUR', 'to' => 'USD,GBP']])
            ->willReturn($response);

        $client = new FrankfurterClientService($http);
        $rates = $client->fetchRates('EUR', ['USD', 'GBP']);

        $this->assertSame(['USD' => 1.2, 'GBP' => 0.8], $rates);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws Exception
     */
    public function testFetchRatesMissingRates(): void
    {
        $response = $this->createMock(ResponseInterface::class);
        $response->method('toArray')->willReturn(['foo' => 'bar']);

        $http = $this->createMock(HttpClientInterface::class);
        $http->method('request')->willReturn($response);

        $client = new FrankfurterClientService($http);
        $rates = $client->fetchRates('EUR', ['USD']);

        $this->assertSame([], $rates);
    }
}
