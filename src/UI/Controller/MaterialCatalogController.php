<?php

declare(strict_types=1);

namespace App\UI\Controller;

use App\Domain\Repository\CustomerRepositoryInterface;
use App\Domain\Repository\MaterialRepositoryInterface;
use App\Domain\Repository\CustomerMaterialRepositoryInterface;
use Doctrine\DBAL\Connection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\Receiver\MessageCountAwareInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MaterialCatalogController extends AbstractController
{
    public function __construct(
        private CustomerRepositoryInterface $customerRepository,
        private MaterialRepositoryInterface $materialRepository,
        private CustomerMaterialRepositoryInterface $customerMaterialRepository,
        private Connection $connection
    ) {
    }

    #[Route('/catalog/{salesOrg}/{customerId}', name: 'material_catalog', methods: ['GET'])]
    public function catalog(string $salesOrg, string $customerId): Response
    {
        // Find customer
        $customer = $this->customerRepository->findBySapId($customerId, $salesOrg);
        
        if (!$customer) {
            return $this->render('catalog/not_synced.html.twig', [
                'sales_org' => $salesOrg,
                'customer_id' => $customerId,
            ]);
        }

        // Get all materials for this customer with prices
        $qb = $this->connection->createQueryBuilder();
        $materials = $qb
            ->select(
                'm.id',
                'm.sap_material_number',
                'm.description',
                'm.description_short',
                'm.base_unit',
                'cm.price',
                'cm.currency',
                'cm.weight',
                'cm.weight_unit',
                'cm.volume',
                'cm.volume_unit',
                'cm.is_available',
                'cm.price_updated_at'
            )
            ->from('materials', 'm')
            ->innerJoin('m', 'customer_materials', 'cm', 'm.id = cm.material_id')
            ->innerJoin('cm', 'customers', 'c', 'cm.customer_id = c.id')
            ->where('c.sap_customer_id = :customer_id')
            ->andWhere('c.sales_org = :sales_org')
            ->andWhere('cm.is_available = 1')
            ->setParameter('customer_id', $customerId)
            ->setParameter('sales_org', $salesOrg)
            ->orderBy('m.description', 'ASC')
            ->executeQuery()
            ->fetchAllAssociative();

        // Get sync statistics
        $stats = $this->getSyncStatistics();
        
        // Get total materials count
        $totalMaterials = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('materials')
            ->executeQuery()
            ->fetchOne();

        return $this->render('catalog/index.html.twig', [
            'customer' => [
                'sap_id' => $customerId,
                'sales_org' => $salesOrg,
                'name' => $customer->getName(),
            ],
            'materials' => $materials,
            'total_materials' => $totalMaterials,
            'synced_materials' => count($materials),
            'sync_stats' => $stats,
        ]);
    }

    #[Route('/api/catalog/{salesOrg}/{customerId}/sync-status', name: 'catalog_sync_status', methods: ['GET'])]
    public function syncStatus(string $salesOrg, string $customerId): JsonResponse
    {
        $customer = $this->customerRepository->findBySapId($customerId, $salesOrg);
        
        if (!$customer) {
            return new JsonResponse([
                'synced' => false,
                'customer_found' => false,
                'message' => 'Customer not synced yet',
            ]);
        }

        // Get counts
        $totalMaterials = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('materials')
            ->executeQuery()
            ->fetchOne();

        $syncedPrices = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('customer_materials', 'cm')
            ->innerJoin('cm', 'customers', 'c', 'cm.customer_id = c.id')
            ->where('c.sap_customer_id = :customer_id')
            ->andWhere('c.sales_org = :sales_org')
            ->setParameter('customer_id', $customerId)
            ->setParameter('sales_org', $salesOrg)
            ->executeQuery()
            ->fetchOne();

        // Get queue stats
        $stats = $this->getSyncStatistics();

        $isSyncing = $stats['async_count'] > 0;
        $progress = $totalMaterials > 0 ? ($syncedPrices / $totalMaterials) * 100 : 0;

        return new JsonResponse([
            'synced' => !$isSyncing && $syncedPrices > 0,
            'customer_found' => true,
            'is_syncing' => $isSyncing,
            'progress' => round($progress, 2),
            'total_materials' => (int)$totalMaterials,
            'synced_prices' => (int)$syncedPrices,
            'pending_messages' => $stats['async_count'],
            'failed_messages' => $stats['failed_count'],
        ]);
    }

    private function getSyncStatistics(): array
    {
        // Get messenger queue stats
        $asyncCount = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('messenger_messages')
            ->where('queue_name = :queue')
            ->setParameter('queue', 'async')
            ->executeQuery()
            ->fetchOne();

        $failedCount = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from('messenger_messages')
            ->where('queue_name = :queue')
            ->setParameter('queue', 'failed')
            ->executeQuery()
            ->fetchOne();

        return [
            'async_count' => (int)$asyncCount,
            'failed_count' => (int)$failedCount,
        ];
    }
}
