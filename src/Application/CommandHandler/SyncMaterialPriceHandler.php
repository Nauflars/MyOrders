<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SyncMaterialPriceCommand;
use App\Domain\Entity\CustomerMaterial;
use App\Domain\Repository\CustomerMaterialRepositoryInterface;
use App\Domain\Repository\CustomerRepositoryInterface;
use App\Domain\Repository\MaterialRepositoryInterface;
use App\Domain\Repository\SyncProgressRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class SyncMaterialPriceHandler
{
    public function __construct(
        private SapApiClientInterface $sapApiClient,
        private CustomerRepositoryInterface $customerRepository,
        private MaterialRepositoryInterface $materialRepository,
        private CustomerMaterialRepositoryInterface $customerMaterialRepository,
        private SyncProgressRepositoryInterface $syncProgressRepository,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SyncMaterialPriceCommand $command): void
    {
        $this->logger->debug('Starting SAP material price sync', [
            'customer_id' => $command->customerId,
            'material_number' => $command->materialNumber,
            'sales_org' => $command->salesOrg,
            'posnr' => $command->posnr ?? 'none',
        ]);

        try {
            // Get material price from SAP with POSNR for accurate pricing
            $sapData = $this->sapApiClient->getMaterialPrice(
                $command->customerId,
                $command->materialNumber,
                $command->tvkoData ?? [],
                $command->tvakData ?? [],
                $command->customerData ?? [],
                $command->weData ?? [],
                $command->rgData ?? [],
                $command->posnr // Include POSNR for accurate price retrieval
            );

            // Find customer and material
            $customer = $this->customerRepository->findBySapId($command->customerId, $command->salesOrg);
            if (!$customer) {
                $this->logger->error('Customer not found', [
                    'customer_id' => $command->customerId,
                    'sales_org' => $command->salesOrg,
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
                    material: $material,
                    salesOrg: $command->salesOrg
                );
                
                $this->logger->debug('Creating new customer-material relationship', [
                    'customer_id' => $command->customerId,
                    'material_number' => $command->materialNumber,
                    'sales_org' => $command->salesOrg,
                ]);
            }

            // Update price from SAP data
            $materialData = $sapData['OUT_WA_MATNR'] ?? [];
            
            if (empty($materialData)) {
                $this->logger->warning('No material price data in SAP response', [
                    'customer_id' => $command->customerId,
                    'material_number' => $command->materialNumber,
                    'posnr' => $command->posnr,
                ]);
                return;
            }
            
            $price = $materialData['NETPR'] ?? '0.00';
            $currency = $materialData['WAERK'] ?? 'USD';
            
            // Store POSNR if provided
            if ($command->posnr !== null) {
                $customerMaterial->setPosnrFromString($command->posnr);
            }
            
            $customerMaterial->updatePrice($price, $currency, $materialData);

            // Save
            $this->customerMaterialRepository->save($customerMaterial);

            $this->logger->debug('Material price sync completed', [
                'customer_id' => $command->customerId,
                'material_number' => $command->materialNumber,
                'price' => $customerMaterial->getPrice(),
                'currency' => $customerMaterial->getCurrency(),
            ]);

            // Update sync progress
            if ($command->syncId) {
                $syncProgress = $this->syncProgressRepository->findById($command->syncId);
                if ($syncProgress) {
                    $syncProgress->incrementProcessed();
                    $this->syncProgressRepository->save($syncProgress);
                    
                    $this->logger->debug('Sync progress updated', [
                        'sync_id' => $command->syncId,
                        'processed' => $syncProgress->getProcessedMaterials(),
                        'total' => $syncProgress->getTotalMaterials(),
                        'percentage' => $syncProgress->getPercentageComplete(),
                    ]);
                }
            }

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Material price sync failed: %s in %s:%d - %s',
                get_class($e),
                $e->getFile(),
                $e->getLine(),
                $e->getMessage()
            ), [
                'customer_id' => $command->customerId,
                'material_number' => $command->materialNumber,
                'trace' => $e->getTraceAsString(),
            ]);

            // Don't throw - we don't want to fail the entire sync if one price fails
            // Just log the error and continue
        }
    }
}
