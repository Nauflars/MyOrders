<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Application\Command\SyncCustomerFromSapCommand;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/sap', name: 'sap_')]
class SapSyncController extends AbstractController
{
    public function __construct(
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Trigger SAP synchronization for a customer
     * 
     * POST /api/sap/sync
     * Body: {"salesOrg": "101", "customerId": "0000185851"}
     */
    #[Route('/sync', name: 'sync', methods: ['POST'])]
    public function triggerSync(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (!isset($data['salesOrg']) || !isset($data['customerId'])) {
            return $this->json([
                'error' => 'Missing required parameters: salesOrg and customerId',
            ], 400);
        }

        $salesOrg = (string) $data['salesOrg'];
        $customerId = (string) $data['customerId'];

        $this->logger->info('Triggering SAP sync via API', [
            'sales_org' => $salesOrg,
            'customer_id' => $customerId,
        ]);

        try {
            // Dispatch the sync command asynchronously
            $this->messageBus->dispatch(new SyncCustomerFromSapCommand(
                salesOrg: $salesOrg,
                customerId: $customerId
            ));

            return $this->json([
                'status' => 'sync_started',
                'message' => 'SAP synchronization has been queued and will be processed asynchronously',
                'salesOrg' => $salesOrg,
                'customerId' => $customerId,
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Failed to dispatch sync command', [
                'error' => $e->getMessage(),
                'sales_org' => $salesOrg,
                'customer_id' => $customerId,
            ]);

            return $this->json([
                'error' => 'Failed to trigger synchronization',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Get sync status (placeholder - would need to implement tracking)
     */
    #[Route('/sync/status/{customerId}', name: 'sync_status', methods: ['GET'])]
    public function getSyncStatus(string $customerId): JsonResponse
    {
        // TODO: Implement sync status tracking
        // This would require storing sync job metadata in database
        
        return $this->json([
            'message' => 'Status tracking not yet implemented',
            'customerId' => $customerId,
        ]);
    }
}
