<?php

namespace App\Tests\Command;

use App\Command\FetchCurrencyRatesCommand;
use App\Service\ExchangeRateService;
use PHPUnit\Framework\MockObject\Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Console\Command\Command;

class FetchCurrencyRatesCommandTest extends TestCase
{
    /**
     * @throws Exception
     */
    public function testExecuteNoTargets(): void
    {
        $svc = $this->createMock(ExchangeRateService::class);
        $command = new FetchCurrencyRatesCommand($svc);
        $tester = new CommandTester($command);
        $code = $tester->execute([
            'base_currency' => 'EUR',
        ]);

        $this->assertSame(Command::INVALID, $code);
        $this->assertStringContainsString(
            'Please specify at least one target currency.',
            $tester->getDisplay()
        );
    }

    /**
     * @throws Exception
     */
    public function testExecuteSuccess(): void
    {
        $svc = $this->createMock(ExchangeRateService::class);
        $svc->expects($this->once())
            ->method('updateRates')
            ->with('USD', ['GBP', 'JPY']);

        $command = new FetchCurrencyRatesCommand($svc);
        $tester = new CommandTester($command);
        $code = $tester->execute([
            'base_currency'     => 'usd',
            'target_currencies' => ['gbp', 'jpy'],
        ]);

        $this->assertSame(Command::SUCCESS, $code);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Fetching rates for USD → GBP, JPY', $display);
        $this->assertStringContainsString('Done.', $display);
    }
}
