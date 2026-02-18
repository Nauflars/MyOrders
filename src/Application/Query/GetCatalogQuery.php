<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * GetCatalogQuery - Get paginated material catalog with filters
 * 
 * Returns customer materials with optional search filter and sorting.
 */
final readonly class GetCatalogQuery
{
    public function __construct(
        public string $customerId,
        public ?string $searchTerm = null,
        public int $page = 1,
        public int $perPage = 50,
        public string $sortBy = 'material_number',
        public string $sortDirection = 'ASC'
    ) {
    }

    public function getOffset(): int
    {
        return ($this->page - 1) * $this->perPage;
    }
}
