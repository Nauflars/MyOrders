<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\ValueObject\Posnr;
use Doctrine\ORM\Mapping as ORM;

/**
 * CustomerMaterial - Many-to-Many relationship with price info
 * 
 * Represents the relationship between a customer and a material
 * with customer-specific pricing and availability information.
 * 
 * Includes POSNR (SAP position number) required for accurate price retrieval.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customer_materials')]
#[ORM\UniqueConstraint(name: 'customer_material_salesorg_unique', columns: ['customer_id', 'material_id', 'sales_org'])]
#[ORM\Index(columns: ['customer_id'], name: 'idx_customer')]
#[ORM\Index(columns: ['material_id'], name: 'idx_material')]
#[ORM\Index(columns: ['posnr'], name: 'idx_posnr')]
#[ORM\Index(columns: ['customer_id', 'sales_org'], name: 'idx_customer_salesorg')]
class CustomerMaterial
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\ManyToOne(targetEntity: Customer::class, inversedBy: 'materials')]
    #[ORM\JoinColumn(name: 'customer_id', nullable: false, onDelete: 'CASCADE')]
    private Customer $customer;

    #[ORM\ManyToOne(targetEntity: Material::class, inversedBy: 'customerMaterials')]
    #[ORM\JoinColumn(name: 'material_id', nullable: false, onDelete: 'CASCADE')]
    private Material $material;

    #[ORM\Column(type: 'string', length: 10, name: 'sales_org', nullable: true)]
    private ?string $salesOrg;

    #[ORM\Column(type: 'string', length: 6, nullable: true)]
    private ?string $posnr; // SAP position number for price retrieval

    #[ORM\Column(type: 'decimal', precision: 13, scale: 2, nullable: true)]
    private ?string $price;

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $currency;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'price_unit')]
    private ?string $priceUnit; // Unit of measure for price

    #[ORM\Column(type: 'decimal', precision: 13, scale: 3, nullable: true)]
    private ?string $weight;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'weight_unit')]
    private ?string $weightUnit;

    #[ORM\Column(type: 'decimal', precision: 13, scale: 3, nullable: true)]
    private ?string $volume;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'volume_unit')]
    private ?string $volumeUnit;

    #[ORM\Column(type: 'boolean', name: 'is_available')]
    private bool $isAvailable = true;

    #[ORM\Column(type: 'integer', nullable: true, name: 'minimum_order_quantity')]
    private ?int $minimumOrderQuantity;

    #[ORM\Column(type: 'integer', nullable: true, name: 'availability_days')]
    private ?int $availabilityDays; // Lead time in days

    #[ORM\Column(type: 'json', nullable: true, name: 'sap_price_data')]
    private ?array $sapPriceData = []; // Full SAP pricing response

    #[ORM\Column(type: 'datetime_immutable', name: 'price_updated_at', nullable: true)]
    private ?\DateTimeImmutable $priceUpdatedAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    public function __construct(
        string $id,
        Customer $customer,
        Material $material,
        ?string $salesOrg = null
    ) {
        $this->id = $id;
        $this->customer = $customer;
        $this->material = $material;
        $this->salesOrg = $salesOrg;
        $this->posnr = null;
        $this->price = null;
        $this->currency = null;
        $this->priceUnit = null;
        $this->weight = null;
        $this->weightUnit = null;
        $this->volume = null;
        $this->volumeUnit = null;
        $this->isAvailable = true;
        $this->minimumOrderQuantity = null;
        $this->availabilityDays = null;
        $this->sapPriceData = [];
        $this->priceUpdatedAt = null;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getCustomer(): Customer
    {
        return $this->customer;
    }

    public function getMaterial(): Material
    {
        return $this->material;
    }

    public function updatePrice(
        string $price,
        string $currency,
        array $sapPriceData
    ): void {
        $this->price = $price;
        $this->currency = $currency;
        $this->priceUnit = $sapPriceData['VRKME'] ?? null;
        $this->weight = isset($sapPriceData['BRGEW']) ? (string)$sapPriceData['BRGEW'] : null;
        $this->weightUnit = $sapPriceData['GEWEI'] ?? null;
        $this->volume = isset($sapPriceData['VOLUM']) ? (string)$sapPriceData['VOLUM'] : null;
        $this->volumeUnit = $sapPriceData['VOLEH'] ?? null;
        $this->minimumOrderQuantity = isset($sapPriceData['MINMENGE']) ? (int)$sapPriceData['MINMENGE'] : null;
        $this->availabilityDays = isset($sapPriceData['LPRIO']) ? (int)$sapPriceData['LPRIO'] : null;
        $this->sapPriceData = $sapPriceData;
        $this->priceUpdatedAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function markUnavailable(): void
    {
        $this->isAvailable = false;
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getPrice(): ?string
    {
        return $this->price;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function getSalesOrg(): ?string
    {
        return $this->salesOrg;
    }

    public function setSalesOrg(string $salesOrg): void
    {
        $this->salesOrg = $salesOrg;
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Get POSNR value object (null if not set)
     */
    public function getPosnr(): ?Posnr
    {
        return $this->posnr !== null ? Posnr::fromString($this->posnr) : null;
    }

    /**
     * Get raw POSNR string for database operations
     */
    public function getPosnrValue(): ?string
    {
        return $this->posnr;
    }

    /**
     * Set POSNR from value object
     */
    public function setPosnr(Posnr $posnr): void
    {
        $this->posnr = $posnr->value();
        $this->updatedAt = new \DateTimeImmutable();
    }

    /**
     * Set POSNR from string (validates via value object)
     */
    public function setPosnrFromString(string $posnr): void
    {
        $this->setPosnr(Posnr::fromString($posnr));
    }

    /**
     * Check if POSNR is set
     */
    public function hasPosnr(): bool
    {
        return $this->posnr !== null;
    }
}
