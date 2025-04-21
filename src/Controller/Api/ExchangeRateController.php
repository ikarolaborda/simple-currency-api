<?php

namespace App\Controller\Api;

use App\Service\ExchangeRateService;
use DateTimeInterface;
use Psr\Cache\InvalidArgumentException;
use OpenApi\Attributes as OA;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[Route('exchange-rates', name: 'api_exchange_rates', methods: ['GET'])]
#[OA\Tag(name: 'Exchange Rates', description: 'Operations related to currency exchange rates')]
final class ExchangeRateController extends AbstractController
{
    public function __construct(
        private readonly ExchangeRateService $svc
    ) {}

    /**
     * @throws InvalidArgumentException
     */
    #[OA\Get(
        path: '/api/exchange-rates',
        summary: 'Get exchange rates',
        parameters: [
            new OA\Parameter(
                name: 'base_currency',
                description: '3-letter ISO code for the base currency (default: EUR)',
                in: 'query',
                required: false,
                schema: new OA\Schema(type: 'string', example: 'EUR')
            ),
            new OA\Parameter(
                name: 'target_currencies',
                description: 'Comma-separated list of target currency codes',
                in: 'query',
                required: true,
                schema: new OA\Schema(type: 'string', example: 'USD,GBP')
            )
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Successful retrieval of rates',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(property: 'base', type: 'string', example: 'EUR'),
                        new OA\Property(
                            property: 'rates',
                            type: 'object',
                            example: ['USD' => 1.08, 'GBP' => 0.85],
                            additionalProperties: new OA\AdditionalProperties(type: 'number')
                        ),
                        new OA\Property(property: 'fetched', type: 'string', format: 'date-time')
                    ],
                    type: 'object'
                )
            ),
            new OA\Response(
                response: 400,
                description: 'Invalid request',
                content: new OA\JsonContent(
                    properties: [
                        new OA\Property(
                            property: 'error',
                            type: 'string',
                            example: 'You must provide one or more target_currencies as a comma-separated list.'
                        )
                    ],
                    type: 'object'
                )
            )
        ]
    )]
    public function __invoke(Request $request): JsonResponse
    {
        $base    = strtoupper($request->query->get('base_currency', 'EUR'));
        $raw     = (string) $request->query->get('target_currencies', '');
        $targets = array_filter(array_map('strtoupper', explode(',', $raw)));

        if (empty($targets)) {
            return $this->json([
                'error' => 'You must provide one or more target_currencies as a comma-separated list.'
            ], 400);
        }

        $rates = $this->svc->getRates($base, $targets);

        return $this->json([
            'base'    => $base,
            'rates'   => $rates,
            'fetched' => (new \DateTimeImmutable())->format(DateTimeInterface::ATOM),
        ]);
    }
}
