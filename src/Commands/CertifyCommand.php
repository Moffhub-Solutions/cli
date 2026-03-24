<?php
declare(strict_types=1);
namespace Moffhub\Cli\Commands;

use Moffhub\Cli\Certification\CertificationRunner;
use Moffhub\Cli\Certification\CertificationReport;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'certify', description: 'Run certification tests against a connector')]
class CertifyCommand extends Command
{

    protected function configure(): void
    {
        $this
            ->addArgument('connector-class', InputArgument::REQUIRED, 'Fully qualified connector class name')
            ->addOption('config', 'c', InputOption::VALUE_OPTIONAL, 'Path to JSON config file for connector credentials')
            ->addOption('sandbox', null, InputOption::VALUE_NONE, 'Run in sandbox mode (skip real API calls)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $connectorClass = $input->getArgument('connector-class');
        $configPath = $input->getOption('config');
        $sandbox = $input->getOption('sandbox');

        $io->title('Moffhub Connector Certification');
        $io->text("Connector: {$connectorClass}");

        if (!class_exists($connectorClass)) {
            $io->error("Class {$connectorClass} not found. Ensure autoloading is configured.");
            return Command::FAILURE;
        }

        $config = [];
        if ($configPath && file_exists($configPath)) {
            $config = json_decode(file_get_contents($configPath), true) ?? [];
            $io->text("Config loaded from: {$configPath}");
        }

        $runner = new CertificationRunner($connectorClass, $config, $sandbox);

        $io->section('Running certification tests...');
        $report = $runner->run($io);

        $io->newLine();
        $io->section('Results');

        $this->displayReport($io, $report);

        return $report->passed ? Command::SUCCESS : Command::FAILURE;
    }

    private function displayReport(SymfonyStyle $io, CertificationReport $report): void
    {
        $io->table(
            ['Category', 'Test', 'Status', 'Message'],
            array_map(fn ($result) => [
                $result['category'],
                $result['test'],
                $result['passed'] ? '<fg=green>PASS</>' : '<fg=red>FAIL</>',
                $result['message'] ?? '',
            ], $report->results),
        );

        $io->newLine();
        $io->text("Total: {$report->total} | Passed: {$report->passedCount} | Failed: {$report->failedCount}");

        if ($report->passed) {
            $io->success('Certification PASSED - connector meets MPS requirements.');
        } else {
            $io->error("Certification FAILED - {$report->failedCount} test(s) did not pass.");
        }
    }
}
