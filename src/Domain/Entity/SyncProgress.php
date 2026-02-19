<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * SyncProgress - Tracks synchronization progress for each customer
 * 
 * Stores real-time progress of material synchronization from SAP,
 * allowing per-customer progress tracking in the UI.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sync_progress')]
#[ORM\Index(name: 'idx_customer_sales_org', columns: ['customer_id', 'sales_org'])]
#[ORM\Index(name: 'idx_status', columns: ['status'])]
#[ORM\Index(name: 'idx_started_at', columns: ['started_at'])]
class SyncProgress
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 100)]
    private string $id;

    #[ORM\Column(type: 'string', length: 50)]
    private string $customerId;

    #[ORM\Column(type: 'string', length: 50)]
    private string $salesOrg;

    #[ORM\Column(type: 'integer')]
    private int $totalMaterials;

    #[ORM\Column(type: 'integer')]
    private int $processedMaterials = 0;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status; // in_progress, completed, failed

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $startedAt;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $completedAt = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    private function __construct()
    {
    }

    /**
     * Create a new sync progress tracker
     */
    public static function start(string $customerId, string $salesOrg, int $totalMaterials): self
    {
        $sync = new self();
        $sync->id = uniqid('sync_', true);
        $sync->customerId = $customerId;
        $sync->salesOrg = $salesOrg;
        $sync->totalMaterials = $totalMaterials;
        $sync->processedMaterials = 0;
        $sync->status = 'in_progress';
        $sync->startedAt = new \DateTimeImmutable();
        
        return $sync;
    }

    /**
     * Increment processed materials count
     */
    public function incrementProcessed(): void
    {
        $this->processedMaterials++;
    }

    /**
     * Increment processed materials by a specific amount
     */
    public function incrementProcessedBy(int $count): void
    {
        $this->processedMaterials += $count;
    }

    /**
     * Mark sync as completed
     */
    public function complete(): void
    {
        $this->status = 'completed';
        $this->completedAt = new \DateTimeImmutable();
        $this->processedMaterials = $this->totalMaterials;
    }

    /**
     * Mark sync as failed
     */
    public function fail(string $errorMessage): void
    {
        $this->status = 'failed';
        $this->errorMessage = $errorMessage;
        $this->completedAt = new \DateTimeImmutable();
    }

    /**
     * Calculate percentage complete
     */
    public function getPercentageComplete(): float
    {
        if ($this->totalMaterials === 0) {
            return 100.0;
        }
        
        return round(($this->processedMaterials / $this->totalMaterials) * 100, 2);
    }

    /**
     * Get elapsed time in seconds
     */
    public function getElapsedSeconds(): int
    {
        $endTime = $this->completedAt ?? new \DateTimeImmutable();
        return $endTime->getTimestamp() - $this->startedAt->getTimestamp();
    }

    /**
     * Estimate remaining time in seconds based on current progress
     */
    public function getEstimatedTimeRemaining(): ?int
    {
        if ($this->processedMaterials === 0 || $this->status !== 'in_progress') {
            return null;
        }
        
        $elapsedSeconds = $this->getElapsedSeconds();
        $remainingMaterials = $this->totalMaterials - $this->processedMaterials;
        $secondsPerMaterial = $elapsedSeconds / $this->processedMaterials;
        
        return (int) round($remainingMaterials * $secondsPerMaterial);
    }

    // Getters
    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getSalesOrg(): string
    {
        return $this->salesOrg;
    }

    public function getTotalMaterials(): int
    {
        return $this->totalMaterials;
    }

    public function getProcessedMaterials(): int
    {
        return $this->processedMaterials;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getStartedAt(): \DateTimeInterface
    {
        return $this->startedAt;
    }

    public function getCompletedAt(): ?\DateTimeInterface
    {
        return $this->completedAt;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }
}
