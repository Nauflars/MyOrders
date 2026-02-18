<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * SyncStatus - Enumeration of sync operation states
 * 
 * Represents the current status of a material synchronization operation.
 * Used to track progress and identify failed syncs that may need retry.
 * 
 * States:
 * - in_progress: Sync is currently running
 * - completed: Sync finished successfully
 * - failed: Sync encountered an error and stopped
 */
enum SyncStatus: string
{
    case IN_PROGRESS = 'in_progress';
    case COMPLETED = 'completed';
    case FAILED = 'failed';

    /**
     * Check if the sync is currently running
     */
    public function isInProgress(): bool
    {
        return $this === self::IN_PROGRESS;
    }

    /**
     * Check if the sync completed successfully
     */
    public function isCompleted(): bool
    {
        return $this === self::COMPLETED;
    }

    /**
     * Check if the sync failed
     */
    public function isFailed(): bool
    {
        return $this === self::FAILED;
    }

    /**
     * Check if the sync is in a terminal state (completed or failed)
     */
    public function isTerminal(): bool
    {
        return $this->isCompleted() || $this->isFailed();
    }

    /**
     * Get a human-readable label for the status
     */
    public function label(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'In Progress',
            self::COMPLETED => 'Completed',
            self::FAILED => 'Failed',
        };
    }

    /**
     * Get a CSS class for UI styling
     */
    public function cssClass(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'status-progress',
            self::COMPLETED => 'status-success',
            self::FAILED => 'status-error',
        };
    }

    /**
     * Get an icon name for UI display
     */
    public function icon(): string
    {
        return match ($this) {
            self::IN_PROGRESS => 'spinner',
            self::COMPLETED => 'check-circle',
            self::FAILED => 'x-circle',
        };
    }
}
