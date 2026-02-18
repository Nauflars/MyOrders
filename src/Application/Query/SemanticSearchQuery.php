<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * SemanticSearchQuery - Search materials by semantic similarity
 * 
 * Uses OpenAI embeddings and cosine similarity to find materials
 * matching the user's natural language query.
 */
final readonly class SemanticSearchQuery
{
    public function __construct(
        public string $customerId,
        public string $searchText,
        public int $limit = 20,
        public float $minSimilarity = 0.7
    ) {
    }
}
