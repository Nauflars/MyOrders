<?php

declare(strict_types=1);

namespace App\Application\Event;

/**
 * MaterialSyncedEvent - Domain event emitted when material is synced
 * 
 * Triggered after successfully syncing a material from SAP.
 * Used to update MongoDB read model and trigger other async operations.
 */
final readonly class MaterialSyncedEvent
{
    public function __construct(
        public string $materialId,
        public string $customerId,
        public string $salesOrg,
        public string $materialNumber,
        public string $description,
        public \DateTimeImmutable $syncedAt
    ) {
    }

    public function getMaterialId(): string
    {
        return $this->materialId;
    }

    public function getCustomerId(): string
    {
        return $this->customerId;
    }

    public function getSalesOrg(): string
    {
        return $this->salesOrg;
    }

    public function getMaterialNumber(): string
    {
        return $this->materialNumber;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getSyncedAt(): \DateTimeImmutable
    {
        return $this->syncedAt;
    }

    public function toArray(): array
    {
        return [
            'material_id' => $this->materialId,
            'customer_id' => $this->customerId,
            'sales_org' => $this->salesOrg,
            'material_number' => $this->materialNumber,
            'description' => $this->description,
            'synced_at' => $this->syncedAt->format('Y-m-d H:i:s'),
        ];
    }
}
