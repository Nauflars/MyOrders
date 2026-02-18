<?php

declare(strict_types=1);

namespace App\UI\Command;

use App\Application\Command\SyncCustomerFromSapCommand;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsCommand(
    name: 'app:sap:sync',
    description: 'Synchronize customer data from SAP ERP',
)]
class SapSyncCommand extends Command
{
    public function __construct(
        private readonly MessageBusInterface $messageBus
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('salesOrg', InputArgument::REQUIRED, 'Sales Organization (e.g., 101)')
            ->addArgument('customerId', InputArgument::REQUIRED, 'Customer ID (e.g., 0000185851)')
            ->setHelp(<<<'HELP'
The <info>%command.name%</info> command triggers asynchronous synchronization 
of customer data from SAP ERP.

Usage:
  <info>php %command.full_name% 101 0000185851</info>

This will:
1. Fetch customer data from SAP
2. Sync materials for the customer
3. Sync prices for each material

The process runs asynchronously via Symfony Messenger.
Make sure RabbitMQ is running and workers are consuming messages:
  <info>php bin/console messenger:consume async -vv</info>
HELP
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        
        $salesOrg = (string) $input->getArgument('salesOrg');
        $customerId = (string) $input->getArgument('customerId');

        $io->title('SAP Synchronization');
        $io->section('Configuration');
        $io->table(
            ['Parameter', 'Value'],
            [
                ['Sales Organization', $salesOrg],
                ['Customer ID', $customerId],
            ]
        );

        $io->section('Dispatching Sync Command');

        try {
            // Dispatch the command
            $this->messageBus->dispatch(new SyncCustomerFromSapCommand(
                salesOrg: $salesOrg,
                customerId: $customerId
            ));

            $io->success([
                'SAP synchronization command has been dispatched!',
                'The sync will be processed asynchronously.',
            ]);

            $io->note([
                'Make sure messenger workers are running:',
                '  php bin/console messenger:consume async -vv',
            ]);

            $io->info([
                'The sync process will:',
                '1. Fetch customer data from SAP',
                '2. Create/update customer in database',
                '3. Fetch all materials for the customer',
                '4. Create/update materials in database',
                '5. Fetch and sync prices for each material',
            ]);

            return Command::SUCCESS;

        } catch (\Exception $e) {
            $io->error([
                'Failed to dispatch sync command',
                $e->getMessage(),
            ]);

            if ($output->isVerbose()) {
                $io->section('Stack Trace');
                $io->text($e->getTraceAsString());
            }

            return Command::FAILURE;
        }
    }
}
