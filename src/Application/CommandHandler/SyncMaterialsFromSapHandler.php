<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\SyncMaterialPriceCommand;
use App\Application\Command\SyncMaterialsFromSapCommand;
use App\Domain\Entity\Material;
use App\Domain\Repository\MaterialRepositoryInterface;
use App\Infrastructure\ExternalApi\SapApiClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler]
final readonly class SyncMaterialsFromSapHandler
{
    public function __construct(
        private SapApiClientInterface $sapApiClient,
        private MaterialRepositoryInterface $materialRepository,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SyncMaterialsFromSapCommand $command): void
    {
        $this->logger->info('Starting SAP materials sync', [
            'customer_id' => $command->customerId,
        ]);

        try {
            // Build request payload for materials
            $requestData = [
                'I_WA_TVKO' => $command->tvkoData,
                'I_WA_TVAK' => $command->tvakData,
                'I_WA_AG' => $command->customerData,
                'I_WA_WE' => $command->weData,
                'I_WA_RG' => $command->rgData,
            ];

            // Get materials from SAP
            $sapData = $this->sapApiClient->loadMaterials($requestData);

            if (!isset($sapData['X_MAT_FOUND']) || !is_array($sapData['X_MAT_FOUND'])) {
                $this->logger->warning('No materials returned from SAP', [
                    'customer_id' => $command->customerId,
                ]);
                return;
            }

            $materialsCount = count($sapData['X_MAT_FOUND']);
            $this->logger->info("Processing {$materialsCount} materials from SAP");

            foreach ($sapData['X_MAT_FOUND'] as $sapMaterial) {
                $materialNumber = $sapMaterial['MATNR'] ?? null;
                
                if (!$materialNumber) {
                    $this->logger->warning('Material without MATNR, skipping', [
                        'material_data' => $sapMaterial,
                    ]);
                    continue;
                }

                // Check if material exists
                $material = $this->materialRepository->findBySapMaterialNumber($materialNumber);

                if ($material === null) {
                    // Create new material
                    $material = new Material(
                        id: \Symfony\Component\Uid\Uuid::v4()->toRfc4122(),
                        sapMaterialNumber: $materialNumber,
                        description: $sapMaterial['MAKTG'] ?? 'Unknown Material'
                    );
                    
                    $this->logger->debug('Creating new material', [
                        'material_number' => $materialNumber,
                    ]);
                } else {
                    $this->logger->debug('Updating existing material', [
                        'material_number' => $materialNumber,
                    ]);
                }

                // Update material data
                $material->updateFromSapData($sapMaterial);

                // Save material
                $this->materialRepository->save($material);

                // Dispatch price sync for this material
                $this->messageBus->dispatch(new SyncMaterialPriceCommand(
                    customerId: $command->customerId,
                    materialNumber: $materialNumber,
                    tvkoData: $command->tvkoData,
                    tvakData: $command->tvakData,
                    customerData: $command->customerData,
                    weData: $command->weData,
                    rgData: $command->rgData
                ));
            }

            $this->logger->info('Materials sync completed', [
                'customer_id' => $command->customerId,
                'materials_count' => $materialsCount,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Materials sync failed', [
                'customer_id' => $command->customerId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }
}
