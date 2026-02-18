<?php

declare(strict_types=1);

namespace App\UI\Command;

use App\Application\Command\GenerateEmbeddingCommand;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Infrastructure\Persistence\MongoDB\Document\MaterialView;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * RegenerateEmbeddingsCommand - Regenerate embeddings for all materials
 * 
 * Dispatches GenerateEmbeddingCommand for each MaterialView document.
 * Run after changing embedding model or to repair missing embeddings.
 */
#[AsCommand(
    name: 'app:embeddings:regenerate',
    description: 'Regenerate embeddings for all materials'
)]
final class RegenerateEmbeddingsCommand extends Command
{
    public function __construct(
        private readonly DocumentManager $documentManager,
        private readonly MessageBusInterface $messageBus,
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
            'Regenerate only for specific customer ID'
        );
        $this->addOption(
            'missing-only',
            'm',
            InputOption::VALUE_NONE,
            'Only generate embeddings for materials that don\'t have one'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $customerId = $input->getOption('customer');
        $missingOnly = $input->getOption('missing-only');

        $io->title('Regenerate Material Embeddings');

        // Build query
        $qb = $this->documentManager->getRepository(MaterialView::class)
            ->createQueryBuilder();

        if ($customerId) {
            $qb->field('customerId')->equals($customerId);
        }

        if ($missingOnly) {
            $qb->addOr($qb->expr()->field('embedding')->exists(false))
               ->addOr($qb->expr()->field('embedding')->equals(null));
        }

        $materials = $qb->getQuery()->execute();
        $total = count($materials);

        if ($total === 0) {
            $io->warning('No materials found to regenerate embeddings');
            return Command::SUCCESS;
        }

        $io->section(sprintf('Dispatching embedding generation for %d materials...', $total));
        $io->progressStart($total);

        $dispatched = 0;
        $errors = 0;

        foreach ($materials as $material) {
            try {
                $this->messageBus->dispatch(
                    new GenerateEmbeddingCommand(
                        $material->getMaterialId(),
                        $material->getDescription()
                    )
                );

                $dispatched++;
                $io->progressAdvance();

            } catch (\Exception $e) {
                $errors++;
                $this->logger->error('Failed to dispatch embedding generation', [
                    'material_id' => $material->getMaterialId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        $io->progressFinish();

        $io->success(sprintf(
            'Dispatched %d embedding generation commands (%d errors)',
            $dispatched,
            $errors
        ));

        $io->note('Embeddings will be generated asynchronously by workers. Check logs for progress.');

        if ($errors > 0) {
            $io->warning('Some commands failed to dispatch. Check logs for details.');
            return Command::FAILURE;
        }

        return Command::SUCCESS;
    }
}
