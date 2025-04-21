<?php

namespace App\Tests\Service;

use App\Service\ExchangeRateService;
use App\Contracts\ExchangeRateClientInterface;
use App\Repository\CurrencyRateRepository;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Psr\Cache\InvalidArgumentException;

class ExchangeRateServiceTest extends TestCase
{
    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function testGetRatesCacheHit(): void
    {
        $rates = ['USD' => 1.1, 'GBP' => 0.9];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($rates);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $em = $this->createMock(EntityManagerInterface::class);
        $client = $this->createMock(ExchangeRateClientInterface::class);

        $svc = new ExchangeRateService($em, $client, $cache);
        $result = $svc->getRates('EUR', ['USD', 'GBP']);

        $this->assertSame($rates, $result);
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function testGetRatesCacheMiss(): void
    {
        $rows = [
            ['targetCurrency' => 'USD', 'rate' => '1.1'],
            ['targetCurrency' => 'GBP', 'rate' => '0.9'],
            ['targetCurrency' => 'USD', 'rate' => '1.2'],
        ];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save');

        $query = $this->getMockBuilder(Query::class)
            ->disableOriginalConstructor()
            ->getMock();
        $query->method('getArrayResult')->willReturn($rows);

        $qb = $this->getMockBuilder(QueryBuilder::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['select','where','andWhere','setParameter','orderBy','getQuery'])
            ->getMock();
        $qb->method('select')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('orderBy')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);

        $repo = $this->createMock(CurrencyRateRepository::class);
        $repo->method('createQueryBuilder')->willReturn($qb);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $client = $this->createMock(ExchangeRateClientInterface::class);

        $svc = new ExchangeRateService($em, $client, $cache);
        $result = $svc->getRates('EUR', ['USD', 'GBP']);

        $this->assertSame(['USD' => 1.1, 'GBP' => 0.9], $result);
    }

    /**
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function testUpdateRates(): void
    {
        $rates = ['USD' => 1.1, 'GBP' => 0.9];

        $client = $this->createMock(ExchangeRateClientInterface::class);
        $client->method('fetchRates')->willReturn($rates);

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist');
        $em->expects($this->once())->method('flush');

        $svc = new ExchangeRateService($em, $client, $cache);
        $svc->updateRates('EUR', ['USD', 'GBP']);
    }

    /**
     * @throws Exception
     * @throws \ReflectionException
     */
    public function testMakeCacheKey(): void
    {
        $client = $this->createMock(ExchangeRateClientInterface::class);
        $cache  = $this->createMock(CacheItemPoolInterface::class);
        $em     = $this->createMock(EntityManagerInterface::class);

        $svc = new ExchangeRateService($em, $client, $cache);
        $method = new \ReflectionMethod($svc, 'makeCacheKey');

        $key = $method->invoke($svc, 'EUR', ['GBP', 'USD']);
        $this->assertSame('exchange_rates_eur_gbp_usd', $key);
    }
}
