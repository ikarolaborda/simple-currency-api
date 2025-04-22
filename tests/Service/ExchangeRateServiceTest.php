<?php

namespace App\Tests\Service;

use App\Entity\CurrencyRate;
use App\Service\ExchangeRateService;
use App\Contracts\ExchangeRateClientInterface;
use App\Repository\CurrencyRateRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ExchangeRateServiceTest extends TestCase
{
    /** @throws InvalidArgumentException
     * @throws Exception
     */
    public function testGetRatesCacheHit(): void
    {
        $rates = ['USD' => 1.1, 'GBP' => 0.9];

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(true);
        $cacheItem->method('get')->willReturn($rates);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);

        $em     = $this->createMock(EntityManagerInterface::class);
        $client = $this->createMock(ExchangeRateClientInterface::class);

        $svc    = new ExchangeRateService($em, $client, $cache);

        $this->assertSame($rates, $svc->getRates('EUR', ['USD', 'GBP']));
    }

    /** @throws InvalidArgumentException
     * @throws Exception
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

        $em     = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($repo);

        $client = $this->createMock(ExchangeRateClientInterface::class);

        $svc    = new ExchangeRateService($em, $client, $cache);
        $this->assertSame(['USD' => 1.1, 'GBP' => 0.9], $svc->getRates('EUR', ['USD', 'GBP']));
    }

    /** @throws InvalidArgumentException
     * @throws ExceptionInterface
     * @throws Exception
     */
    public function testUpdateRatesPersistsAndCaches(): void
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
        $ref = new \ReflectionMethod($svc, 'makeCacheKey');
        $key = $ref->invoke($svc, 'EUR', ['GBP', 'USD']);
        $this->assertSame('exchange_rates_eur_gbp_usd', $key);
    }
    /** Async Function Tests */
    /**
     * @throws Exception
     * @throws InvalidArgumentException
     * @throws ExceptionInterface
     */
    public function testDispatchesMessageOnRateChange(): void
    {
        $rates = ['USD' => 1.20];

        $client = $this->createMock(ExchangeRateClientInterface::class);
        $client->method('fetchRates')->willReturn($rates);

        $prev = new CurrencyRate();
        $prev->setBaseCurrency('EUR')
            ->setTargetCurrency('USD')
            ->setRate(1.00)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'));

        $repo = $this->createMock(CurrencyRateRepository::class);
        $repo->method('findOneBy')->willReturn($prev);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(CurrencyRate::class)
            ->willReturn($repo);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(fn($msg) =>
                $msg instanceof \App\Message\CurrencyRateChangedMessage
                && $msg->getBaseCurrency() === 'EUR'
                && count($msg->getChanges()) === 1
            ))
            ->willReturnCallback(fn($msg) => new Envelope($msg));

        $svc = new ExchangeRateService($em, $client, $cache, $bus);
        $svc->updateRates('EUR', ['USD']);
    }

    /**
     * @throws ExceptionInterface
     * @throws InvalidArgumentException
     * @throws Exception
     */
    public function testDoesNotDispatchWhenNoChange(): void
    {
        $rates = ['USD' => 1.00];

        $client = $this->createMock(ExchangeRateClientInterface::class);
        $client->method('fetchRates')->willReturn($rates);

        $prev = new CurrencyRate();
        $prev->setBaseCurrency('EUR')
            ->setTargetCurrency('USD')
            ->setRate(1.00)
            ->setFetchedAt(new \DateTimeImmutable('-1 hour'));

        $repo = $this->createMock(CurrencyRateRepository::class);
        $repo->method('findOneBy')->willReturn($prev);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')
            ->with(CurrencyRate::class)
            ->willReturn($repo);
        $em->expects($this->once())->method('persist');
        $em->expects($this->once())->method('flush');

        $cacheItem = $this->createMock(CacheItemInterface::class);
        $cacheItem->method('isHit')->willReturn(false);

        $cache = $this->createMock(CacheItemPoolInterface::class);
        $cache->method('getItem')->willReturn($cacheItem);
        $cache->expects($this->once())->method('save');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $svc = new ExchangeRateService($em, $client, $cache, $bus);
        $svc->updateRates('EUR', ['USD']);
    }
}
