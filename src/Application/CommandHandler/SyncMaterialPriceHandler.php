<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SyncMaterialPriceCommand;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Repository\CustomerMaterialRepositoryInterface;
use App\Domain\Repository\CustomerRepositoryInterface;
use App\Domain\Repository\MaterialRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClient;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncMaterialPriceHandler
{
    public function __construct(
        private SapApiClient $sapApiClient,
        private CustomerRepositoryInterface $customerRepository,
        private MaterialRepositoryInterface $materialRepository,
        private CustomerMaterialRepositoryInterface $customerMaterialRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SyncMaterialPriceCommand $command): void
    {
        $this->logger->debug('Starting SAP material price sync', [
            'customer_id' => $command->customerId,
            'material_number' => $command->materialNumber,
        ]);

        try {
            // Get material price from SAP
            $sapData = $this->sapApiClient->getMaterialPrice(
                $command->customerId,
                $command->materialNumber,
                $command->tvkoData,
                $command->tvakData,
                $command->customerData,
                $command->weData,
                $command->rgData
            );

            // Find customer and material
            $salesOrg = $command->tvkoData['VKORG'] ?? null;
            if (!$salesOrg) {
                $this->logger->error('Sales org not found in TVKO data');
                return;
            }

            $customer = $this->customerRepository->findBySapId($command->customerId, $salesOrg);
            if (!$customer) {
                $this->logger->error('Customer not found', [
                    'customer_id' => $command->customerId,
                ]);
                return;
            }

            $material = $this->materialRepository->findBySapMaterialNumber($command->materialNumber);
            if (!$material) {
                $this->logger->error('Material not found', [
                    'material_number' => $command->materialNumber,
                ]);
                return;
            }

            // Find or create CustomerMaterial relationship
            $customerMaterial = $this->customerMaterialRepository->findByCustomerAndMaterial(
                $customer,
                $material
            );

            if ($customerMaterial === null) {
                $customerMaterial = new CustomerMaterial(
                    id: \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
                    customer: $customer,
                    material: $material
                );
                
                $this->logger->debug('Creating new customer-material relationship', [
                    'customer_id' => $command->customerId,
                    'material_number' => $command->materialNumber,
                ]);
            }

            // Update price from SAP data
            $materialData = $sapData['OUT_WA_MATNR'] ?? [];
            
            if (empty($materialData)) {
                $this->logger->warning('No material price data in SAP response', [
                    'customer_id' => $command->customerId,
                    'material_number' => $command->materialNumber,
                ]);
                return;
            }
            
            $price = $materialData['NETPR'] ?? '0.00';
            $currency = $materialData['WAERK'] ?? 'USD';
            
            $customerMaterial->updatePrice($price, $currency, $materialData);

            // Save
            $this->customerMaterialRepository->save($customerMaterial);

            $this->logger->debug('Material price sync completed', [
                'customer_id' => $command->customerId,
                'material_number' => $command->materialNumber,
                'price' => $customerMaterial->getPrice(),
                'currency' => $customerMaterial->getCurrency(),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Material price sync failed', [
                'customer_id' => $command->customerId,
                'material_number' => $command->materialNumber,
                'error' => $e->getMessage(),
            ]);

            // Don't throw - we don't want to fail the entire sync if one price fails
            // Just log the error and continue
        }
    }
}
