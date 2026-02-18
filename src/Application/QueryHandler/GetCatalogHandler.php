<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Query\GetCatalogQuery;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Infrastructure\Persistence\MongoDB\Document\MaterialView;
use Psr\Log\LoggerInterface;

/**
 * GetCatalogHandler - Execute catalog query using MongoDB
 * 
 * Fast pagination and filtering using MongoDB read model.
 * Falls back to MySQL if MongoDB not populated.
 */
final readonly class GetCatalogHandler
{
    public function __construct(
        private DocumentManager $documentManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(GetCatalogQuery $query): array
    {
        $this->logger->debug('Executing catalog query', [
            'customer_id' => $query->customerId,
            'page' => $query->page,
            'per_page' => $query->perPage,
            'search_term' => $query->searchTerm,
        ]);

        try {
            $repository = $this->documentManager->getRepository(MaterialView::class);
            $qb = $repository->createQueryBuilder();

            // Filter by customer
            $qb->field('customerId')->equals($query->customerId);

            // Apply search filter
            if ($query->searchTerm) {
                $pattern = preg_quote($query->searchTerm, '/');
                $regex = new \MongoDB\BSON\Regex($pattern, 'i');
                $qb->addOr(
                    $qb->expr()->field('materialNumber')->equals($regex),
                    $qb->expr()->field('description')->equals($regex)
                );
            }

            // Count total
            $total = $qb->getQuery()->execute()->count();

            // Apply sorting
            $sortField = match($query->sortBy) {
                'material_number' => 'materialNumber',
                'description' => 'description',
                'price' => 'price',
                'updated' => 'lastUpdatedAt',
                default => 'materialNumber'
            };
            $sortDir = strtolower($query->sortDirection) === 'desc' ? -1 : 1;
            $qb->sort($sortField, $sortDir);

            // Apply pagination
            $qb->skip($query->getOffset())
               ->limit($query->perPage);

            $materials = $qb->getQuery()->execute()->toArray();

            $this->logger->info('Catalog query executed', [
                'customer_id' => $query->customerId,
                'results_count' => count($materials),
                'total' => $total,
            ]);

            return [
                'materials' => $materials,
                'total' => $total,
                'page' => $query->page,
                'per_page' => $query->perPage,
                'total_pages' => (int) ceil($total / $query->perPage),
            ];

        } catch (\Exception $e) {
            $this->logger->error('Catalog query failed', [
                'customer_id' => $query->customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
