<?php

namespace App\Service;

use App\Contracts\ExchangeRateClientInterface;
use App\Entity\CurrencyRate;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\InvalidArgumentException;

readonly class ExchangeRateService
{
    public function __construct(
        private EntityManagerInterface      $em,
        private ExchangeRateClientInterface $client,
        private CacheItemPoolInterface      $cache
    ) {}

    /**
     * Fetch from the 3rd‑party API, persist into MySQL, and prime Redis cache.
     *
     * @param string $base 3-letter base currency code
     * @param string[] $targets Array of 3-letter target currency codes
     * @throws InvalidArgumentException
     */
    public function updateRates(string $base, array $targets): void
    {
        $rates = $this->client->fetchRates($base, $targets);
        $now   = new \DateTimeImmutable();

        foreach ($rates as $currency => $rate) {
            $entity = new CurrencyRate();
            $entity
                ->setBaseCurrency($base)
                ->setTargetCurrency($currency)
                ->setRate($rate)
                ->setFetchedAt($now);

            $this->em->persist($entity);
        }

        $this->em->flush();

        $key  = $this->makeCacheKey($base, $targets);
        $item = $this->cache->getItem($key);
        $item->set($rates)
            ->expiresAfter(3600);
        $this->cache->save($item);
    }

    /**
     * Get rates, checking cache first, then DB if cache miss. Primes cache on miss.
     *
     * @param string $base 3-letter base currency code
     * @param string[] $targets Array of 3-letter target currency codes
     * @return array<string, float>
     * @throws InvalidArgumentException
     */
    public function getRates(string $base, array $targets): array
    {
        $key  = $this->makeCacheKey($base, $targets);
        $item = $this->cache->getItem($key);

        if ($item->isHit()) {
            return $item->get();
        }

        // Cache miss: load latest rates from DB
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
        sort($targets);
        return 'exchange_rates_' . strtolower($base) . '_' . strtolower(implode('_', $targets));
    }
}
