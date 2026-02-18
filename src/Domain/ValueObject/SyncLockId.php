<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

/**
 * SyncLockId - Composite identifier for sync operations
 * 
 * Represents a unique identifier for distributed locking of sync operations.
 * Composed of salesOrg and customerId to ensure only one sync operation
 * can run for a given customer/sales org combination at a time.
 * 
 * Business Rules:
 * - Combination of salesOrg + customerId must be unique
 * - Used as Redis lock key: "sync_lock_{salesOrg}_{customerId}"
 * - Immutable once created
 */
final readonly class SyncLockId
{
    private function __construct(
        private string $salesOrg,
        private string $customerId
    ) {
        $this->validate();
    }

    /**
     * Create a new SyncLockId
     * 
     * @throws InvalidArgumentException If validation fails
     */
    public static function create(string $salesOrg, string $customerId): self
    {
        return new self($salesOrg, $customerId);
    }

    /**
     * Create from a lock key string (e.g., "sync_lock_0000210839_185")
     */
    public static function fromLockKey(string $lockKey): self
    {
        $pattern = '/^sync_lock_([^_]+)_(.+)$/';
        if (!preg_match($pattern, $lockKey, $matches)) {
            throw new InvalidArgumentException(
                sprintf('Invalid lock key format: "%s"', $lockKey)
            );
        }

        return new self($matches[1], $matches[2]);
    }

    /**
     * Validate the sync lock components
     * 
     * @throws InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        if (empty(trim($this->salesOrg))) {
            throw new InvalidArgumentException('Sales organization cannot be empty');
        }

        if (empty(trim($this->customerId))) {
            throw new InvalidArgumentException('Customer ID cannot be empty');
        }

        // Sales org is typically 10 digits (e.g., "0000210839")
        if (strlen($this->salesOrg) > 50) {
            throw new InvalidArgumentException(
                sprintf('Sales organization too long: "%s"', $this->salesOrg)
            );
        }

        if (strlen($this->customerId) > 50) {
            throw new InvalidArgumentException(
                sprintf('Customer ID too long: "%s"', $this->customerId)
            );
        }
    }

    /**
     * Get the sales organization code
     */
    public function salesOrg(): string
    {
        return $this->salesOrg;
    }

    /**
     * Get the customer ID
     */
    public function customerId(): string
    {
        return $this->customerId;
    }

    /**
     * Convert to Redis lock key
     */
    public function toLockKey(): string
    {
        return sprintf('sync_lock_%s_%s', $this->salesOrg, $this->customerId);
    }

    /**
     * Convert to string representation (same as lock key)
     */
    public function __toString(): string
    {
        return $this->toLockKey();
    }

    /**
     * Check equality with another SyncLockId
     */
    public function equals(self $other): bool
    {
        return $this->salesOrg === $other->salesOrg 
            && $this->customerId === $other->customerId;
    }

    /**
     * Convert to array for serialization
     */
    public function toArray(): array
    {
        return [
            'salesOrg' => $this->salesOrg,
            'customerId' => $this->customerId,
        ];
    }
}
