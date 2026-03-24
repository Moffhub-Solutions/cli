<?php
declare(strict_types=1);
namespace Moffhub\Cli\Commands;

use Moffhub\MpsSpec\Contracts\ConnectorInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ValidateManifestCommand extends Command
{
    protected static $defaultName = 'validate';
    protected static $defaultDescription = 'Validate a connector manifest against the MPS spec';

    protected function configure(): void
    {
        $this->addArgument('connector-class', InputArgument::REQUIRED, 'Fully qualified connector class name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connectorClass = $input->getArgument('connector-class');

        if (!class_exists($connectorClass)) {
            $io->error("Class {$connectorClass} not found.");
            return Command::FAILURE;
        }

        $connector = new $connectorClass();

        if (!$connector instanceof ConnectorInterface) {
            $io->error("{$connectorClass} does not implement ConnectorInterface.");
            return Command::FAILURE;
        }

        $manifest = $connector->manifest();
        $errors = [];

        if (empty($manifest->connectorId)) $errors[] = 'connectorId is empty';
        if (empty($manifest->displayName)) $errors[] = 'displayName is empty';
        if (empty($manifest->version)) $errors[] = 'version is empty';
        if (empty($manifest->specVersion)) $errors[] = 'specVersion is empty';
        if (empty($manifest->vendorName)) $errors[] = 'vendorName is empty';
        if (empty($manifest->vendorWebsite)) $errors[] = 'vendorWebsite is empty';
        if (empty($manifest->vendorSupportEmail)) $errors[] = 'vendorSupportEmail is empty';
        if (empty($manifest->supportedChannels)) $errors[] = 'supportedChannels is empty';
        if (empty($manifest->supportedCurrencies)) $errors[] = 'supportedCurrencies is empty';
        if (empty($manifest->capabilities)) $errors[] = 'capabilities is empty';

        $io->title('Manifest Validation');
        $io->table(
            ['Field', 'Value'],
            [
                ['Connector ID', $manifest->connectorId],
                ['Display Name', $manifest->displayName],
                ['Version', $manifest->version],
                ['Spec Version', $manifest->specVersion],
                ['Vendor', $manifest->vendorName],
                ['Channels', implode(', ', array_map(fn ($c) => $c->value, $manifest->supportedChannels))],
                ['Currencies', implode(', ', $manifest->supportedCurrencies)],
                ['Capabilities', implode(', ', array_map(fn ($c) => $c->value, $manifest->capabilities))],
                ['Settlement', $manifest->settlementModel->value],
                ['Config Fields', (string) count($manifest->requiredConfig)],
            ],
        );

        if (empty($errors)) {
            $io->success('Manifest is valid.');
            return Command::SUCCESS;
        }

        foreach ($errors as $error) {
            $io->error($error);
        }

        return Command::FAILURE;
    }
}
