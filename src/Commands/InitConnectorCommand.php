<?php
declare(strict_types=1);
namespace Moffhub\Cli\Commands;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'init-connector', description: 'Scaffold a new MPS connector package')]
class InitConnectorCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addArgument('name', InputArgument::REQUIRED, 'Connector name (e.g. my-gateway)')
            ->addArgument('namespace', InputArgument::OPTIONAL, 'PHP namespace', 'Vendor\\Connector\\MyGateway');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $name = $input->getArgument('name');
        $namespace = $input->getArgument('namespace');

        $dir = getcwd() . '/' . $name;

        if (is_dir($dir)) {
            $io->error("Directory {$name} already exists.");
            return Command::FAILURE;
        }

        mkdir($dir . '/src/Support', 0755, true);
        mkdir($dir . '/tests', 0755, true);

        $slug = str_replace([' ', '_'], '-', strtolower($name));
        $className = str_replace(['-', '_', ' '], '', ucwords($name, '-_ ')) . 'Connector';
        $escapedNs = str_replace('\\', '\\\\', $namespace);

        file_put_contents($dir . '/composer.json', json_encode([
            'name' => "vendor/connector-{$slug}",
            'description' => "{$name} MPS connector",
            'type' => 'library',
            'license' => 'MIT',
            'require' => [
                'php' => '^8.3',
                'moffhub/mps-spec' => '^0.1',
                'moffhub/connector-sdk' => '^0.1',
                'guzzlehttp/guzzle' => '^7.0',
            ],
            'autoload' => ['psr-4' => ["{$namespace}\\" => 'src/']],
            'minimum-stability' => 'stable',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $connectorTemplate = <<<PHP
        <?php
        declare(strict_types=1);
        namespace {$namespace};

        use Moffhub\ConnectorSdk\BaseConnector;
        use Moffhub\MpsSpec\Contracts\HasChargeCapability;
        use Moffhub\MpsSpec\Contracts\HasWebhookCapability;
        use Moffhub\MpsSpec\Data\ChargeRequest;
        use Moffhub\MpsSpec\Data\ChargeResponse;
        use Moffhub\MpsSpec\Data\ConfigField;
        use Moffhub\MpsSpec\Data\ConnectorManifest;
        use Moffhub\MpsSpec\Data\HealthStatus;
        use Moffhub\MpsSpec\Data\WebhookResult;
        use Moffhub\MpsSpec\Enums\Capability;
        use Moffhub\MpsSpec\Enums\Channel;
        use Moffhub\MpsSpec\Enums\ChargeStatus;
        use Moffhub\MpsSpec\Enums\SettlementModel;

        class {$className} extends BaseConnector implements HasChargeCapability, HasWebhookCapability
        {
            public function manifest(): ConnectorManifest
            {
                return new ConnectorManifest(
                    connectorId: '{$slug}',
                    displayName: '{$name}',
                    version: '0.1.0',
                    specVersion: '0.1.0',
                    vendorName: 'Your Company',
                    vendorWebsite: 'https://example.com',
                    vendorSupportEmail: 'support@example.com',
                    supportedChannels: [Channel::Card],
                    supportedCurrencies: ['USD'],
                    capabilities: [Capability::Payment, Capability::Webhook],
                    settlementModel: SettlementModel::VendorLed,
                    requiredConfig: [
                        new ConfigField(key: 'api_key', label: 'API Key', type: 'string', secret: true),
                    ],
                );
            }

            public function createCharge(ChargeRequest \$request): ChargeResponse
            {
                \$this->ensureInitialized();
                // TODO: Implement charge creation
                throw new \\RuntimeException('Not implemented');
            }

            public function queryCharge(string \$chargeId): ChargeResponse
            {
                \$this->ensureInitialized();
                // TODO: Implement charge query
                throw new \\RuntimeException('Not implemented');
            }

            public function handleWebhook(array \$headers, mixed \$body): WebhookResult
            {
                \$this->ensureInitialized();
                // TODO: Implement webhook handling
                throw new \\RuntimeException('Not implemented');
            }
        }
        PHP;

        file_put_contents($dir . "/src/{$className}.php", $connectorTemplate);

        $io->success([
            "Connector scaffolded at: {$dir}",
            "Class: {$namespace}\\{$className}",
            '',
            'Next steps:',
            "1. cd {$name} && composer install",
            "2. Implement createCharge(), queryCharge(), handleWebhook()",
            "3. Run: moffhub certify '{$namespace}\\{$className}' --sandbox",
        ]);

        return Command::SUCCESS;
    }
}
