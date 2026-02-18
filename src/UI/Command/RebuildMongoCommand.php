<?php

declare(strict_types=1);

namespace App\UI\Command;

use Doctrine\ODM\MongoDB\DocumentManager;
use Doctrine\ORM\EntityManagerInterface;
use App\Domain\Entity\CustomerMaterial;
use App\Infrastructure\Persistence\MongoDB\Document\MaterialView;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * RebuildMongoCommand - Rebuild MongoDB MaterialView from MySQL
 * 
 * Syncs all CustomerMaterial entities from MySQL to MongoDB MaterialView documents.
 * Run after schema changes or to repair inconsistencies.
 */
#[AsCommand(
    name: 'app:mongo:rebuild',
    description: 'Rebuild MongoDB MaterialView collection from MySQL'
)]
final class RebuildMongoCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly DocumentManager $documentManager,
        private readonly LoggerInterface $logger
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption(
            'customer',
            'c',
            InputOption::VALUE_OPTIONAL,
            'Rebuild only for specific customer ID'
        );
        $this->addOption(
            'clear',
            null,
            InputOption::VALUE_NONE,
            'Clear existing MaterialView documents before rebuild'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $customerId = $input->getOption('customer');
        $clear = $input->getOption('clear');

        $io->title('Rebuild MongoDB MaterialView Collection');

        // Clear existing documents if requested
        if ($clear) {
            $io->section('Clearing existing MaterialView documents...');
            $repository = $this->documentManager->getRepository(MaterialView::class);
            
            if ($customerId) {
                $count = $repository->createQueryBuilder()
                    ->remove()
                    ->field('customerId')->equals($customerId)
                    ->getQuery()
                    ->execute();
            } else {
                $count = $repository->createQueryBuilder()
                    ->remove()
                    ->getQuery()
                    ->execute();
            }
            
            $this->documentManager->flush();
            $io->success(sprintf('Cleared %d documents', $count));
        }

        // Build query for CustomerMaterial
        $qb = $this->entityManager->getRepository(CustomerMaterial::class)
            ->createQueryBuilder('cm');
        
        if ($customerId) {
            $qb->where('cm.customer_id = :customer_id')
               ->setParameter('customer_id', $customerId);
        }

        $materials = $qb->getQuery()->getResult();
        $total = count($materials);

        if ($total === 0) {
            $io->warning('No materials found to sync');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Syncing %d materials to MongoDB...', $total));
        $io->progressStart($total);

        $synced = 0;
        $errors = 0;

        foreach ($materials as $material) {
            try {
                // Check if already exists
                $repository = $this->documentManager->getRepository(MaterialView::class);
                $materialView = $repository->findOneBy([
                    'materialId' => $material->getId(),
                ]);

                if (!$materialView) {
                    // Create new
                    $materialView = new MaterialView(
                        $material->getId(),
                        $material->getMaterialNumber(),
                        $material->getDescription() ?? '',
                        $material->getCustomerId(),
                        $material->getSalesOrg() ?? ''
                    );
                    $this->documentManager->persist($materialView);
                }

                // Update price if available
                if ($material->getPrice() !== null) {
                    $materialView->updatePrice(
                        $material->getPrice(),
                        $material->getCurrency() ?? 'EUR',
                        $material->getPosnr()
                    );
                }

                $synced++;
                $io->progressAdvance();

                // Flush in batches
                if ($synced % 100 === 0) {
                    $this->documentManager->flush();
                    $this->documentManager->clear();
                }

            } catch (\Exception $e) {
                $errors++;
                $this->logger->error('Failed to sync material to MongoDB', [
                    'material_id' => $material->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Final flush
        $this->documentManager->flush();
        $io->progressFinish();

        $io->success(sprintf(
            'Rebuild complete: %d synced, %d errors',
            $synced,
            $errors
        ));

        if ($errors > 0) {
            $io->warning('Some materials failed to sync. Check logs for details.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
