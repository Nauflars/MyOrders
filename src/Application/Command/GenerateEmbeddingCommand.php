<?php

declare(strict_types=1);

namespace App\Application\Command;

/**
 * GenerateEmbeddingCommand - Generate semantic embedding for material
 * 
 * Triggered after price updates or when regenerating all embeddings.
 * Generates 1536-dimensional embedding using OpenAI API and stores in MongoDB.
 */
final readonly class GenerateEmbeddingCommand
{
    public function __construct(
        public string $materialId,
        public string $description
    ) {
    }
}
