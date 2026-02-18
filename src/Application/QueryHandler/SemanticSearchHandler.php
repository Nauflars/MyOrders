<?php

declare(strict_types=1);

namespace App\Application\QueryHandler;

use App\Application\Query\SemanticSearchQuery;
use App\Infrastructure\ExternalApi\OpenAiEmbeddingClient;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Infrastructure\Persistence\MongoDB\Document\MaterialView;
use Psr\Log\LoggerInterface;

/**
 * SemanticSearchHandler - Execute semantic search via embeddings
 * 
 * Generates embedding for search text, then uses MongoDB Atlas Vector Search
 * or manual cosine similarity to find matching materials.
 */
final readonly class SemanticSearchHandler
{
    public function __construct(
        private OpenAiEmbeddingClient $embeddingClient,
        private DocumentManager $documentManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(SemanticSearchQuery $query): array
    {
        $this->logger->info('Executing semantic search', [
            'customer_id' => $query->customerId,
            'search_text' => $query->searchText,
            'limit' => $query->limit,
        ]);

        try {
            // Generate embedding for search text
            $searchEmbedding = $this->embeddingClient->generateEmbedding($query->searchText);

            // Get all materials for customer (with embeddings)
            $repository = $this->documentManager->getRepository(MaterialView::class);
            $materials = $repository->findBy([
                'customerId' => $query->customerId,
            ]);

            // Calculate similarity scores
            $results = [];
            foreach ($materials as $material) {
                $materialEmbedding = $material->getEmbedding();
                
                if (!$materialEmbedding) {
                    continue; // Skip materials without embeddings
                }

                $similarity = OpenAiEmbeddingClient::cosineSimilarity(
                    $searchEmbedding,
                    $materialEmbedding
                );

                if ($similarity >= $query->minSimilarity) {
                    $results[] = [
                        'material' => $material,
                        'similarity' => $similarity,
                    ];
                }
            }

            // Sort by similarity (highest first)
            usort($results, fn($a, $b) => $b['similarity'] <=> $a['similarity']);

            // Limit results
            $results = array_slice($results, 0, $query->limit);

            $this->logger->info('Semantic search completed', [
                'customer_id' => $query->customerId,
                'results_count' => count($results),
                'avg_similarity' => count($results) > 0 
                    ? array_sum(array_column($results, 'similarity')) / count($results)
                    : 0,
            ]);

            return $results;

        } catch (\Exception $e) {
            $this->logger->error('Semantic search failed', [
                'customer_id' => $query->customerId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }
}
