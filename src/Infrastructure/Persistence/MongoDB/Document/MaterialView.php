<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\MongoDB\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;

/**
 * MaterialView - MongoDB document for fast search and semantic search
 * 
 * Read-only model optimized for search operations. Updated via events
 * when materials are synced to MySQL (source of truth).
 */
#[MongoDB\Document(collection: 'material_view')]
#[MongoDB\Index(keys: ['customerId' => 1, 'materialNumber' => 1], options: ['unique' => true])]
#[MongoDB\Index(keys: ['customerId' => 1, 'salesOrg' => 1])]
#[MongoDB\Index(keys: ['materialNumber' => 1])]
#[MongoDB\Index(keys: ['customerId' => 1])]
#[MongoDB\Index(keys: ['lastUpdatedAt' => -1])]
class MaterialView
{
    #[MongoDB\Id]
    private string $id;

    #[MongoDB\Field(type: 'string')]
    private string $materialId;

    #[MongoDB\Field(type: 'string')]
    private string $materialNumber;

    #[MongoDB\Field(type: 'string')]
    private string $description;

    #[MongoDB\Field(type: 'string')]
    private string $customerId;

    #[MongoDB\Field(type: 'string')]
    private string $salesOrg;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $posnr = null;

    #[MongoDB\Field(type: 'float', nullable: true)]
    private ?float $price = null;

    #[MongoDB\Field(type: 'string', nullable: true)]
    private ?string $currency = null;

    #[MongoDB\Field(type: 'collection', nullable: true)]
    private ?array $embedding = null; // 1536-dimensional vector

    #[MongoDB\Field(type: 'date_immutable')]
    private \DateTimeImmutable $lastUpdatedAt;

    public function __construct(
        string $materialId,
        string $materialNumber,
        string $description,
        string $customerId,
        string $salesOrg
    ) {
        $this->materialId = $materialId;
        $this->materialNumber = $materialNumber;
        $this->description = $description;
        $this->customerId = $customerId;
        $this->salesOrg = $salesOrg;
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    public function updatePrice(float $price, string $currency, ?string $posnr): void
    {
        $this->price = $price;
        $this->currency = $currency;
        $this->posnr = $posnr;
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    public function setEmbedding(array $embedding): void
    {
        if (count($embedding) !== 1536) {
            throw new \InvalidArgumentException('Embedding must be 1536-dimensional vector');
        }
        $this->embedding = $embedding;
        $this->lastUpdatedAt = new \DateTimeImmutable();
    }

    // Getters
    public function getId(): string { return $this->id; }
    public function getMaterialId(): string { return $this->materialId; }
    public function getMaterialNumber(): string { return $this->materialNumber; }
    public function getDescription(): string { return $this->description; }
    public function getCustomerId(): string { return $this->customerId; }
    public function getSalesOrg(): string { return $this->salesOrg; }
    public function getPosnr(): ?string { return $this->posnr; }
    public function getPrice(): ?float { return $this->price; }
    public function getCurrency(): ?string { return $this->currency; }
    public function getEmbedding(): ?array { return $this->embedding; }
    public function getLastUpdatedAt(): \DateTimeImmutable { return $this->lastUpdatedAt; }
}
