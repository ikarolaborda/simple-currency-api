<?php

namespace App\Command;

use App\Service\ExchangeRateService;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:currency:rates',
    description: 'Fetches currency rates for a base and saves them.'
)]
class FetchCurrencyRatesCommand extends Command
{
    public function __construct(
        private readonly ExchangeRateService $svc
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'base_currency',
                InputArgument::OPTIONAL,
                'Base currency (3‑letter ISO code)',
                'EUR'
            )
            ->addArgument(
                'target_currencies',
                InputArgument::IS_ARRAY,
                'One or more target currencies (3‑letter ISO codes)'
            );
    }

    /**
     * @throws InvalidArgumentException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $base    = strtoupper($input->getArgument('base_currency'));
        $targets = array_map('strtoupper', $input->getArgument('target_currencies'));

        if (empty($targets)) {
            $output->writeln('<error>Please specify at least one target currency.</>');
            return Command::INVALID;
        }

        $output->writeln("Fetching rates for {$base} → " . implode(', ', $targets));
        $this->svc->updateRates($base, $targets);
        $output->writeln('<info>Done.</>');

        return Command::SUCCESS;
    }
}
