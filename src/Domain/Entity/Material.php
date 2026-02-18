<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

/**
 * Material Entity (Clean SAP Material Master Data)
 * 
 * Represents a material/product from SAP with master data.
 * This is the "clean" material table without customer-specific data.
 */
#[ORM\Entity]
#[ORM\Table(name: 'materials')]
#[ORM\Index(columns: ['sap_material_number'], name: 'idx_material_sap')]
class Material
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 18, unique: true, name: 'sap_material_number')]
    private string $sapMaterialNumber; // MATNR

    #[ORM\Column(type: 'string', length: 255)]
    private string $description;

    #[ORM\Column(type: 'string', length: 40, nullable: true, name: 'description_short')]
    private ?string $descriptionShort;

    #[ORM\Column(type: 'string', length: 10, nullable: true, name: 'material_type')]
    private ?string $materialType; // MTART

    #[ORM\Column(type: 'string', length: 10, nullable: true, name: 'material_group')]
    private ?string $materialGroup; // MATKL

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'base_unit')]
    private ?string $baseUnit; // MEINS

    #[ORM\Column(type: 'decimal', precision: 13, scale: 3, nullable: true)]
    private ?string $weight; // Gross weight

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'weight_unit')]
    private ?string $weightUnit;

    #[ORM\Column(type: 'decimal', precision: 13, scale: 3, nullable: true)]
    private ?string $volume;

    #[ORM\Column(type: 'string', length: 3, nullable: true, name: 'volume_unit')]
    private ?string $volumeUnit;

    #[ORM\Column(type: 'boolean', name: 'is_active')]
    private bool $isActive = true;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sapData = []; // Full SAP master data

    #[ORM\Column(type: 'datetime_immutable', name: 'last_sync_at')]
    private \DateTimeImmutable $lastSyncAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'created_at')]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', name: 'updated_at')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'material', targetEntity: CustomerMaterial::class, cascade: ['persist', 'remove'])]
    private Collection $customerMaterials;

    public function __construct(
        string $id,
        string $sapMaterialNumber,
        string $description
    ) {
        $this->id = $id;
        $this->sapMaterialNumber = $sapMaterialNumber;
        $this->description = $description;
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->customerMaterials = new ArrayCollection();
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function getSapMaterialNumber(): string
    {
        return $this->sapMaterialNumber;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function updateFromSapData(array $sapData): void
    {
        // Update description if MAKTG is present
        if (isset($sapData['MAKTG'])) {
            $this->description = $sapData['MAKTG'];
        }
        $this->descriptionShort = $sapData['MAKTX'] ?? null;
        $this->materialType = $sapData['MTART'] ?? null;
        $this->materialGroup = $sapData['MATKL'] ?? null;
        $this->baseUnit = $sapData['MEINS'] ?? null;
        $this->weight = isset($sapData['BRGEW']) ? (string)$sapData['BRGEW'] : null;
        $this->weightUnit = $sapData['GEWEI'] ?? null;
        $this->volume = isset($sapData['VOLUM']) ? (string)$sapData['VOLUM'] : null;
        $this->volumeUnit = $sapData['VOLEH'] ?? null;
        $this->sapData = $sapData;
        $this->lastSyncAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function deactivate(): void
    {
        $this->isActive = false;
        $this->updatedAt = new \DateTimeImmutable();
    }
}
