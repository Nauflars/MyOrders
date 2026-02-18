<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Customer Entity (SAP AG - Sold-to Party)
 * 
 * Represents a customer from SAP with all relevant data
 * for order management and material pricing.
 */
#[ORM\Entity]
#[ORM\Table(name: 'customers')]
#[ORM\Index(columns: ['sap_customer_id', 'sales_org'], name: 'idx_customer_sap')]
class Customer
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 10, name: 'sap_customer_id')]
    private string $sapCustomerId; // KUNNR

    #[ORM\Column(type: 'string', length: 4, name: 'sales_org')]
    private string $salesOrg; // VKORG

    #[ORM\Column(type: 'string', length: 255)]
    private string $name1;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $name2;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $street; // STRAS

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    private ?string $city; // ORT01

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $postalCode; // PSTLZ

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $region; // REGIO

    #[ORM\Column(type: 'string', length: 2)]
    private string $country; // LAND1

    #[ORM\Column(type: 'string', length: 3, nullable: true)]
    private ?string $currency; // WAERK

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $incoterms; // INCO1

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $shippingCondition; // VSBED

    #[ORM\Column(type: 'string', length: 10, nullable: true)]
    private ?string $paymentTerms; // ZTERM

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $taxClass; // TAXK1

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    private ?string $vatNumber; // STCEG

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sapData = []; // Full SAP response data

    #[ORM\Column(type: 'datetime_immutable', name: 'last_sync_at')]
    private \DateTimeImmutable $lastSyncAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'customer', targetEntity: CustomerMaterial::class, cascade: ['persist', 'remove'])]
    private Collection $materials;

    public function __construct(
        string $id,
        string $sapCustomerId,
        string $salesOrg,
        string $name1,
        string $country
    ) {
        $this->id = $id;
        $this->sapCustomerId = $sapCustomerId;
        $this->salesOrg = $salesOrg;
        $this->name1 = $name1;
        $this->country = $country;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->materials = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSapCustomerId(): string
    {
        return $this->sapCustomerId;
    }

    public function getSalesOrg(): string
    {
        return $this->salesOrg;
    }

    public function getName(): string
    {
        return $this->name1 . ($this->name2 ? ' ' . $this->name2 : '');
    }

    public function updateFromSapData(array $sapData): void
    {
        $this->name2 = $sapData['NAME2'] ?? null;
        $this->street = $sapData['STRAS'] ?? null;
        $this->city = $sapData['ORT01'] ?? null;
        $this->postalCode = $sapData['PSTLZ'] ?? null;
        $this->region = $sapData['REGIO'] ?? null;
        $this->currency = $sapData['WAERK'] ?? null;
        $this->incoterms = $sapData['INCO1'] ?? null;
        $this->shippingCondition = $sapData['VSBED'] ?? null;
        $this->paymentTerms = $sapData['ZTERM'] ?? null;
        $this->taxClass = $sapData['TAXK1'] ?? null;
        $this->vatNumber = $sapData['STCEG'] ?? null;
        $this->sapData = $sapData;
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getMaterials(): Collection
    {
        return $this->materials;
    }
}
