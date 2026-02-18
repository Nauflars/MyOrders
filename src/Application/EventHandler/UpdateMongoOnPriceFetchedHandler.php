<?php

declare(strict_types=1);

namespace App\Application\EventHandler;

use App\Application\Command\GenerateEmbeddingCommand;
use App\Application\Event\PriceFetchedEvent;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Infrastructure\Persistence\MongoDB\Document\MaterialView;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * UpdateMongoOnPriceFetchedHandler - Update MongoDB when price fetched
 * 
 * Listens to PriceFetchedEvent and updates MaterialView document with price data.
 * Then dispatches GenerateEmbeddingCommand to update semantic search index.
 */
#[AsMessageHandler]
final readonly class UpdateMongoOnPriceFetchedHandler
{
    public function __construct(
        private DocumentManager $documentManager,
        private MessageBusInterface $messageBus,
        private LoggerInterface $logger
    ) {
    }

    public function __invoke(PriceFetchedEvent $event): void
    {
        $this->logger->info('Updating MongoDB after price fetch', [
            'material_id' => $event->getMaterialId(),
            'customer_id' => $event->getCustomerId(),
            'price' => $event->getPrice(),
        ]);

        try {
            $repository = $this->documentManager->getRepository(MaterialView::class);
            
            // Find or create MaterialView
            $materialView = $repository->findOneBy([
                'materialId' => $event->getMaterialId(),
                'customerId' => $event->getCustomerId(),
            ]);

            if (!$materialView) {
                // Material not yet in MongoDB, will be synced later
                $this->logger->debug('MaterialView not found, skipping price update', [
                    'material_id' => $event->getMaterialId(),
                ]);
                return;
            }

            // Update price data
            $materialView->updatePrice(
                $event->getPrice(),
                $event->getCurrency(),
                $event->getPosnr()
            );

            $this->documentManager->flush();

            $this->logger->info('MongoDB updated with price', [
                'material_id' => $event->getMaterialId(),
                'price' => $event->getPrice(),
                'posnr' => $event->getPosnr(),
            ]);

            // Dispatch command to regenerate embedding
            $this->messageBus->dispatch(
                new GenerateEmbeddingCommand(
                    $event->getMaterialId(),
                    $materialView->getDescription()
                )
            );

        } catch (\Exception $e) {
            $this->logger->error('Failed to update MongoDB after price fetch', [
                'material_id' => $event->getMaterialId(),
                'error' => $e->getMessage(),
            ]);
            // Don't rethrow - this is eventual consistency, can retry later
        }
    }
}
