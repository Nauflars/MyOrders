<?php

declare(strict_types=1);

namespace App\Application\Event;

/**
 * PriceFetchedEvent - Domain event emitted when material price is fetched from SAP
 * 
 * Triggered after successful price retrieval from SAP system. This event
 * can be used to:
 * - Update MongoDB read model with price
 * - Trigger embedding generation for semantic search
 * - Send notifications about price updates
 * - Log price history for analytics
 */
final readonly class PriceFetchedEvent
{
    public function __construct(
        private string $materialId,
        private string $customerId,
        private string $salesOrg,
        private string $posnr,
        private float $price,
        private string $currency,
        private array $sapPriceData,
        private \DateTimeImmutable $fetchedAt
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

    public function getPosnr(): string
    {
        return $this->posnr;
    }

    public function getPrice(): float
    {
        return $this->price;
    }

    public function getCurrency(): string
    {
        return $this->currency;
    }

    public function getSapPriceData(): array
    {
        return $this->sapPriceData;
    }

    public function getFetchedAt(): \DateTimeImmutable
    {
        return $this->fetchedAt;
    }

    public function toArray(): array
    {
        return [
            'materialId' => $this->materialId,
            'customerId' => $this->customerId,
            'salesOrg' => $this->salesOrg,
            'posnr' => $this->posnr,
            'price' => $this->price,
            'currency' => $this->currency,
            'sapPriceData' => $this->sapPriceData,
            'fetchedAt' => $this->fetchedAt->format(\DateTimeInterface::ATOM),
        ];
    }
}
