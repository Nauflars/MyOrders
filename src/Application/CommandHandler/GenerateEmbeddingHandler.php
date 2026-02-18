<?php

declare(strict_types=1);

namespace App\Application\CommandHandler;

use App\Application\Command\GenerateEmbeddingCommand;
use App\Infrastructure\ExternalApi\OpenAiEmbeddingClient;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Infrastructure\Persistence\MongoDB\Document\MaterialView;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * GenerateEmbeddingHandler - Generate and store material embedding
 * 
 * Calls OpenAI API to generate embedding, then updates MaterialView document.
 * Retries on failure via messenger retry strategy.
 */
#[AsMessageHandler]
final readonly class GenerateEmbeddingHandler
{
    public function __construct(
        private OpenAiEmbeddingClient $embeddingClient,
        private DocumentManager $documentManager,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(GenerateEmbeddingCommand $command): void
    {
        $this->logger->info('Generating embedding', [
            'material_id' => $command->materialId,
            'description_length' => strlen($command->description),
        ]);

        try {
            // Generate embedding via OpenAI
            $embedding = $this->embeddingClient->generateEmbedding($command->description);

            // Find MaterialView document
            $repository = $this->documentManager->getRepository(MaterialView::class);
            $materialView = $repository->findOneBy(['materialId' => $command->materialId]);

            if (!$materialView) {
                $this->logger->warning('MaterialView not found for embedding', [
                    'material_id' => $command->materialId,
                ]);
                return;
            }

            // Update with embedding
            $materialView->setEmbedding($embedding);
            $this->documentManager->flush();

            $this->logger->info('Embedding generated and stored', [
                'material_id' => $command->materialId,
                'dimensions' => count($embedding),
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Embedding generation failed', [
                'material_id' => $command->materialId,
                'error' => $e->getMessage(),
            ]);
            throw $e; // Retry via messenger
        }
    }
}
