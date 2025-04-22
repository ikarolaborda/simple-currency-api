<?php

namespace App\Tests\Controller;

use App\Entity\CurrencyRate;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class ExchangeRateControllerFunctionalTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        $this->client = static::createClient();

        $container = $this->client->getContainer();
        $this->em = $container->get('doctrine')->getManager();
        $container->get('cache.app')->clear();

        $now = new \DateTimeImmutable();
        foreach (['USD' => 1.123456, 'GBP' => 0.876543] as $currency => $rate) {
            $entity = (new CurrencyRate())
                ->setBaseCurrency('EUR')
                ->setTargetCurrency($currency)
                ->setRate($rate)
                ->setFetchedAt($now);

            $this->em->persist($entity);
        }
        $this->em->flush();

        $container->get('cache.app')->clear();
    }

    public function testSuccessfulRetrieval(): void
    {
        $this->client->request(
            'GET',
            '/api/exchange-rates?base_currency=EUR&target_currencies=USD,GBP'
        );

        $this->assertResponseIsSuccessful();

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('rates', $data);
        $this->assertIsNumeric($data['rates']['USD']);
        $this->assertIsNumeric($data['rates']['GBP']);
    }

    public function testMissingTargets(): void
    {
        $this->client->request('GET', '/api/exchange-rates');

        $this->assertResponseStatusCodeSame(400);

        $response = $this->client->getResponse();
        $data = json_decode($response->getContent(), true);

        $this->assertArrayHasKey('error', $data);
    }
}
