<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\Doctrine\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Example Order Write Model (MySQL - Source of Truth)
 * 
 * This represents the WRITE side of CQRS pattern.
 * All commands that modify state go through this entity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'orders')]
class Order
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 255)]
    private string $customerName;

    #[ORM\Column(type: 'string', length: 20)]
    private string $status;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    private string $totalAmount;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        string $customerName,
        string $totalAmount
    ) {
        $this->id = $id;
        $this->customerName = $customerName;
        $this->status = 'pending';
        $this->totalAmount = $totalAmount;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomerName(): string
    {
        return $this->customerName;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function getTotalAmount(): string
    {
        return $this->totalAmount;
    }

    public function confirm(): void
    {
        $this->status = 'confirmed';
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function cancel(): void
    {
        $this->status = 'cancelled';
        $this->updatedAt = new \DateTimeImmutable();
    }
}
