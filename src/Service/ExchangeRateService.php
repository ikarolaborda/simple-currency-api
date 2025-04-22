<?php

namespace App\Service;

use App\Contracts\ExchangeRateClientInterface;
use App\Entity\CurrencyRate;
use App\Message\CurrencyRateChangedMessage;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Messenger\Exception\ExceptionInterface;
use Symfony\Component\Messenger\MessageBusInterface;

class ExchangeRateService
{
    public function __construct(
        private readonly EntityManagerInterface      $em,
        private readonly ExchangeRateClientInterface $client,
        private readonly CacheItemPoolInterface      $cache,
        private readonly ?MessageBusInterface        $bus = null
    ) {}

    /**
     * Fetch fresh rates, detect changes, dispatch async notification if bus is available,
     * persist to the DB, and prime the cache.
     *
     * @param string[] $targets
     * @throws ExceptionInterface|InvalidArgumentException
     */
    public function updateRates(string $base, array $targets): void
    {
        $rates = $this->client->fetchRates($base, $targets);
        $now   = new \DateTimeImmutable();

        $changes = [];
        $repo    = $this->em->getRepository(CurrencyRate::class);
        foreach ($rates as $currency => $rate) {
            $prev = $repo->findOneBy(
                ['baseCurrency' => $base, 'targetCurrency' => $currency],
                ['fetchedAt'    => 'DESC']
            );
            if ($prev && (float) $prev->getRate() !== $rate) {
                $changes[] = sprintf(
                    '%s: %0.6f → %0.6f',
                    $currency,
                    $prev->getRate(),
                    $rate
                );
            }
        }

        if ($this->bus !== null && !empty($changes)) {
            $this->bus->dispatch(new CurrencyRateChangedMessage($base, $changes));
        }

        foreach ($rates as $currency => $rate) {
            $entity = new CurrencyRate($base, $currency, $rate, $now);
            $this->em->persist($entity);
        }
        $this->em->flush();

        $cacheKey = $this->getCacheKey($base, $targets);
        $item     = $this->cache->getItem($cacheKey);
        $item->set($rates)
            ->expiresAfter(3600);
        $this->cache->save($item);
    }

    /**
     * Returns cached rates if available, otherwise loads from DB and caches them.
     *
     * @param string[] $targets
     * @throws InvalidArgumentException
     */
    public function getRates(string $base, array $targets): array
    {
        $cacheKey = $this->getCacheKey($base, $targets);
        $item     = $this->cache->getItem($cacheKey);

        if ($item->isHit()) {
            return $item->get();
        }

        $repo = $this->em->getRepository(CurrencyRate::class);
        $qb   = $repo->createQueryBuilder('c')
            ->select('c.targetCurrency, c.rate')
            ->where('c.baseCurrency = :base')
            ->andWhere('c.targetCurrency IN (:targets)')
            ->setParameter('base', $base)
            ->setParameter('targets', $targets)
            ->orderBy('c.fetchedAt', 'DESC');

        $rows  = $qb->getQuery()->getArrayResult();
        $rates = [];
        foreach ($rows as $row) {
            if (!isset($rates[$row['targetCurrency']])) {
                $rates[$row['targetCurrency']] = (float) $row['rate'];
            }
        }

        $item->set($rates)
            ->expiresAfter(3600);
        $this->cache->save($item);

        return $rates;
    }

    private function makeCacheKey(string $base, array $targets): string
    {
        sort($targets, SORT_STRING | SORT_FLAG_CASE);
        return 'exchange_rates_' . strtolower($base) . '_' . strtolower(implode('_', $targets));
    }

    /**
     * @param string $base
     * @param array $targets
     * @return string
     */
    public function getCacheKey(string $base, array $targets): string
    {
        return $this->makeCacheKey($base, $targets);
    }
}
