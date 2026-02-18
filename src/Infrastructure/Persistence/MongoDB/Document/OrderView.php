<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * Example Order Read Model (MongoDB - Optimized for Queries)
 * 
 * This represents the READ side of CQRS pattern.
 * Denormalized data structure optimized for fast queries.
 */
#[MongoDB\Document(collection: 'order_views')]
class OrderView
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: 'string')]
    private string $orderId;

    #[MongoDB\Field(type: 'string')]
    private string $customerName;

    #[MongoDB\Field(type: 'string')]
    private string $status;

    #[MongoDB\Field(type: 'float')]
    private float $totalAmount;

    #[MongoDB\Field(type: 'date')]
    private \DateTime $createdAt;

    #[MongoDB\Field(type: 'date')]
    private \DateTime $updatedAt;

    /**
     * Denormalized fields for fast queries
     */
    #[MongoDB\Field(type: 'hash')]
    private array $metadata;

    public function __construct(
        string $orderId,
        string $customerName,
        string $status,
        float $totalAmount,
        \DateTime $createdAt
    ) {
        $this->orderId = $orderId;
        $this->customerName = $customerName;
        $this->status = $status;
        $this->totalAmount = $totalAmount;
        $this->createdAt = $createdAt;
        $this->updatedAt = new \DateTime();
        $this->metadata = [];
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function updateStatus(string $status): void
    {
        $this->status = $status;
        $this->updatedAt = new \DateTime();
    }

    public function getTotalAmount(): float
    {
        return $this->totalAmount;
    }

    public function getCreatedAt(): \DateTime
    {
        return $this->createdAt;
    }

    public function addMetadata(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
        $this->updatedAt = new \DateTime();
    }

    public function getMetadata(): array
    {
        return $this->metadata;
    }
}
