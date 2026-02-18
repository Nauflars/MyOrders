<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SyncCustomerFromSapCommand;
use App\Application\Command\SyncMaterialsFromSapCommand;
use App\Domain\Entity\Customer;
use App\Domain\Repository\CustomerRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class SyncCustomerFromSapHandler
{
    public function __construct(
        private SapApiClient $sapApiClient,
        private CustomerRepositoryInterface $customerRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SyncCustomerFromSapCommand $command): void
    {
        $this->logger->info('Starting SAP customer sync', [
            'sales_org' => $command->salesOrg,
            'customer_id' => $command->customerId,
        ]);

        try {
            // Get customer data from SAP
            $sapData = $this->sapApiClient->getCustomerData(
                $command->salesOrg,
                $command->customerId
            );

            // Check if customer already exists
            $customer = $this->customerRepository->findBySapId(
                $command->customerId,
                $command->salesOrg
            );

            if ($customer === null) {
                // Create new customer
                $customer = new Customer(
                    id: \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
                    sapCustomerId: $command->customerId,
                    salesOrg: $command->salesOrg,
                    name1: $sapData['NAME1'] ?? 'Unknown',
                    country: $sapData['LAND1'] ?? 'ES'
                );
                
                $this->logger->info('Creating new customer', [
                    'customer_id' => $command->customerId,
                ]);
            } else {
                $this->logger->info('Updating existing customer', [
                    'customer_id' => $command->customerId,
                ]);
            }

            // Update customer data from SAP response
            $customer->updateFromSapData($sapData);

            // Save customer
            $this->customerRepository->save($customer);

            $this->logger->info('Customer sync completed', [
                'customer_id' => $command->customerId,
                'customer_name' => $customer->getName(),
            ]);

            // Now trigger materials sync
            if (isset($sapData['WA_TVKO']) && isset($sapData['WA_TVAK']) && isset($sapData['WA_AG'])) {
                $this->logger->info('Dispatching materials sync command');
                
                $this->messageBus->dispatch(new SyncMaterialsFromSapCommand(
                    customerId: $command->customerId,
                    salesOrg: $command->salesOrg,
                    tvkoData: $sapData['WA_TVKO'],
                    tvakData: $sapData['WA_TVAK'],
                    customerData: $sapData['WA_AG'],
                    weData: $sapData['WA_WE'] ?? [],
                    rgData: $sapData['WA_RG'] ?? []
                ));
            }

        } catch (\Exception $e) {
            $this->logger->error('Customer sync failed', [
                'customer_id' => $command->customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
