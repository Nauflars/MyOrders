<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

/**
 * EmbeddingVector - 1536-dimensional embedding vector for semantic search
 * 
 * Represents an OpenAI text-embedding-3-small vector used for semantic
 * similarity calculations. Vectors are normalized and have fixed dimensions.
 */
final readonly class EmbeddingVector
{
    private const DIMENSIONS = 1536;

    /** @param array<int, float> $values */
    private function __construct(
        private array $values
    ) {
        $this->validate();
    }

    /**
     * Create from array of floats
     * 
     * @param array<int, float> $values
     * @throws InvalidArgumentException
     */
    public static function fromArray(array $values): self
    {
        return new self($values);
    }

    /**
     * Validate vector dimensions and values
     */
    private function validate(): void
    {
        if (count($this->values) !== self::DIMENSIONS) {
            throw new InvalidArgumentException(
                sprintf(
                    'Embedding vector must have exactly %d dimensions, got %d',
                    self::DIMENSIONS,
                    count($this->values)
                )
            );
        }

        foreach ($this->values as $value) {
            if (!is_float($value) && !is_int($value)) {
                throw new InvalidArgumentException('All embedding values must be numeric');
            }
        }
    }

    /**
     * Get the vector values
     * 
     * @return array<int, float>
     */
    public function values(): array
    {
        return $this->values;
    }

    /**
     * Calculate cosine similarity with another vector
     * 
     * @return float Similarity score between -1 and 1 (higher = more similar)
     */
    public function cosineSimilarity(self $other): float
    {
        $dotProduct = 0.0;
        $magnitude1 = 0.0;
        $magnitude2 = 0.0;

        for ($i = 0; $i < self::DIMENSIONS; $i++) {
            $dotProduct += $this->values[$i] * $other->values[$i];
            $magnitude1 += $this->values[$i] ** 2;
            $magnitude2 += $other->values[$i] ** 2;
        }

        $magnitude1 = sqrt($magnitude1);
        $magnitude2 = sqrt($magnitude2);

        if ($magnitude1 == 0.0 || $magnitude2 == 0.0) {
            return 0.0;
        }

        return $dotProduct / ($magnitude1 * $magnitude2);
    }

    /**
     * Get vector dimensions
     */
    public static function dimensions(): int
    {
        return self::DIMENSIONS;
    }

    /**
     * Convert to JSON-serializable array
     */
    public function toArray(): array
    {
        return $this->values;
    }
}
