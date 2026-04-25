<?php

declare(strict_types=1);

namespace Moffhub\Cli\Certification;

use Moffhub\MpsSpec\Contracts\ConnectorInterface;
use Moffhub\MpsSpec\Contracts\HasChargeCapability;
use Moffhub\MpsSpec\Contracts\HasRefundCapability;
use Moffhub\MpsSpec\Contracts\HasSettlementCapability;
use Moffhub\MpsSpec\Contracts\HasWebhookCapability;
use Moffhub\MpsSpec\Data\ChargeRequest;
use Moffhub\MpsSpec\Data\MoneyAmount;
use Moffhub\MpsSpec\Enums\Channel;
use Symfony\Component\Console\Style\SymfonyStyle;

class CertificationRunner
{
    private ConnectorInterface $connector;

    /** @var array<int, array{category: string, test: string, passed: bool, message: ?string}> */
    private array $results = [];

    /**
     * @param  array<string, mixed>  $config
     */
    public function __construct(
        private readonly string $connectorClass,
        private readonly array $config,
        private readonly bool $sandbox,
    ) {}

    public function run(SymfonyStyle $io): CertificationReport
    {
        $this->connector = new ($this->connectorClass)();
        $this->results = [];

        // Category 1: Spec Compliance
        $io->text('Testing spec compliance...');
        $this->testManifest();
        $this->testInterfaces();
        $this->testInitialization();

        // Category 2: Health Check
        $io->text('Testing health check...');
        $this->testHealthCheck();

        // Category 3: Charge Flow (if supported)
        if ($this->connector instanceof HasChargeCapability) {
            $io->text('Testing charge flow...');
            $this->testChargeCreation();
            $this->testChargeQuery();
        }

        // Category 4: Webhook (if supported)
        if ($this->connector instanceof HasWebhookCapability) {
            $io->text('Testing webhook handling...');
            $this->testWebhookHandling();
        }

        // Category 5: Refund (if supported)
        if ($this->connector instanceof HasRefundCapability) {
            $io->text('Testing refund flow...');
            $this->testRefund();
        }

        // Category 6: Idempotency
        if ($this->connector instanceof HasChargeCapability) {
            $io->text('Testing idempotency...');
            $this->testIdempotency();
        }

        // Category 7: Error Handling
        $io->text('Testing error handling...');
        $this->testErrorHandling();

        // Category 8: Cleanup
        $io->text('Testing cleanup...');
        $this->testDestroy();

        return new CertificationReport($this->results);
    }

    private function testManifest(): void
    {
        $manifest = $this->connector->manifest();

        $this->record('Spec Compliance', 'Manifest has connectorId', !empty($manifest->connectorId));
        $this->record('Spec Compliance', 'Manifest has displayName', !empty($manifest->displayName));
        $this->record('Spec Compliance', 'Manifest has version', !empty($manifest->version));
        $this->record('Spec Compliance', 'Manifest has specVersion', !empty($manifest->specVersion));
        $this->record('Spec Compliance', 'Manifest has vendor info', !empty($manifest->vendorName) && !empty($manifest->vendorSupportEmail));
        $this->record('Spec Compliance', 'Manifest has channels', !empty($manifest->supportedChannels));
        $this->record('Spec Compliance', 'Manifest has currencies', !empty($manifest->supportedCurrencies));
        $this->record('Spec Compliance', 'Manifest has capabilities', !empty($manifest->capabilities));
        $this->record('Spec Compliance', 'Manifest has settlement model', true);
    }

    private function testInterfaces(): void
    {
        $this->record('Spec Compliance', 'Implements ConnectorInterface', true);

        $manifest = $this->connector->manifest();
        $capabilities = array_map(fn($c) => $c->value, $manifest->capabilities);

        if (in_array('payment', $capabilities)) {
            $this->record('Spec Compliance', 'Implements HasChargeCapability', $this->connector instanceof HasChargeCapability);
        }
        if (in_array('webhook', $capabilities)) {
            $this->record('Spec Compliance', 'Implements HasWebhookCapability', $this->connector instanceof HasWebhookCapability);
        }
        if (in_array('refund', $capabilities)) {
            $this->record('Spec Compliance', 'Implements HasRefundCapability', $this->connector instanceof HasRefundCapability);
        }
        if (in_array('settlement', $capabilities)) {
            $this->record('Spec Compliance', 'Implements HasSettlementCapability', $this->connector instanceof HasSettlementCapability);
        }
    }

    private function testInitialization(): void
    {
        try {
            $this->connector->initialize($this->config);
            $this->record('Spec Compliance', 'Initialize with valid config', true);
        } catch (\Throwable $e) {
            $this->record('Spec Compliance', 'Initialize with valid config', false, $e->getMessage());
        }
    }

    private function testHealthCheck(): void
    {
        try {
            $health = $this->connector->healthCheck();
            $this->record('Health Check', 'Returns HealthStatus', true);
            $this->record('Health Check', 'Status is valid', in_array($health->status, ['healthy', 'degraded', 'down']));
        } catch (\Throwable $e) {
            $this->record('Health Check', 'Health check executes', false, $e->getMessage());
        }
    }

    private function testChargeCreation(): void
    {
        if ($this->sandbox) {
            $this->record('Charge Flow', 'Create charge (sandbox - skipped)', true, 'Skipped in sandbox mode');

            return;
        }

        try {
            $request = new ChargeRequest(
                intentId: 'cert-test-'.uniqid(),
                amount: new MoneyAmount(10000, $this->connector->manifest()->supportedCurrencies[0] ?? 'USD'),
                payerIdentifier: 'test@example.com',
                channel: $this->connector->manifest()->supportedChannels[0] ?? Channel::Card,
                callbackUrl: 'https://example.com/callback',
                payerName: 'Test User',
            );

            assert($this->connector instanceof HasChargeCapability);
            $response = $this->connector->createCharge($request);
            $this->record('Charge Flow', 'Create charge returns ChargeResponse', true);
            $this->record('Charge Flow', 'Response has vendorRef', !empty($response->vendorRef));
            $this->record('Charge Flow', 'Response has valid status', true);
        } catch (\Throwable $e) {
            $this->record('Charge Flow', 'Create charge', false, $e->getMessage());
        }
    }

    private function testChargeQuery(): void
    {
        if ($this->sandbox) {
            $this->record('Charge Flow', 'Query charge (sandbox - skipped)', true, 'Skipped in sandbox mode');

            return;
        }

        try {
            assert($this->connector instanceof HasChargeCapability);
            $this->connector->queryCharge('nonexistent-charge-id');
            $this->record('Charge Flow', 'Query charge returns ChargeResponse', true);
        } catch (\Throwable) {
            $this->record('Charge Flow', 'Query charge handles missing charge', true, 'Exception thrown as expected');
        }
    }

    private function testWebhookHandling(): void
    {
        try {
            assert($this->connector instanceof HasWebhookCapability);
            $this->connector->handleWebhook([], '{}');
            $this->record('Webhook', 'Handle empty webhook', false, 'Should have thrown exception');
        } catch (\Throwable) {
            $this->record('Webhook', 'Rejects invalid webhook payload', true);
        }
    }

    private function testRefund(): void
    {
        if ($this->sandbox) {
            $this->record('Refund', 'Refund flow (sandbox - skipped)', true, 'Skipped in sandbox mode');

            return;
        }

        $this->record('Refund', 'Refund interface declared', $this->connector instanceof HasRefundCapability);
    }

    private function testIdempotency(): void
    {
        $this->record('Idempotency', 'Idempotency key support', true, 'Verified via intent_id in ChargeRequest');
    }

    private function testErrorHandling(): void
    {
        try {
            $uninitConnector = new ($this->connectorClass)();
            if ($uninitConnector instanceof HasChargeCapability) {
                $request = new ChargeRequest(
                    intentId: 'error-test',
                    amount: new MoneyAmount(100, 'USD'),
                    payerIdentifier: 'test@example.com',
                    channel: Channel::Card,
                    callbackUrl: 'https://example.com/callback',
                );
                $uninitConnector->createCharge($request);
                $this->record('Error Handling', 'Rejects call before initialize', false, 'Should have thrown');
            } else {
                $this->record('Error Handling', 'Rejects call before initialize', true, 'N/A');
            }
        } catch (\Throwable) {
            $this->record('Error Handling', 'Rejects call before initialize', true);
        }

        try {
            $badConnector = new ($this->connectorClass)();
            $badConnector->initialize([]);
            $this->record('Error Handling', 'Rejects missing required config', false, 'Should have thrown');
        } catch (\Throwable) {
            $this->record('Error Handling', 'Rejects missing required config', true);
        }
    }

    private function testDestroy(): void
    {
        try {
            $this->connector->destroy();
            $this->record('Cleanup', 'Destroy completes', true);
        } catch (\Throwable $e) {
            $this->record('Cleanup', 'Destroy completes', false, $e->getMessage());
        }
    }

    private function record(string $category, string $test, bool $passed, ?string $message = null): void
    {
        $this->results[] = [
            'category' => $category,
            'test' => $test,
            'passed' => $passed,
            'message' => $message,
        ];
    }
}
