<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

use InvalidArgumentException;

/**
 * Posnr - SAP Position Number Value Object
 * 
 * Represents a 6-character SAP position number (POSNR) that uniquely identifies
 * a material position within a customer/sales org context. This value is critical
 * for accurate price retrieval from SAP systems.
 * 
 * Business Rules:
 * - Must be exactly 6 characters
 * - Typically zero-padded (e.g., "000010", "000020")
 * - Immutable once set
 * - Required for accurate SAP price calculations
 */
final readonly class Posnr
{
    private function __construct(
        private string $value
    ) {
        $this->validate();
    }

    /**
     * Create a new Posnr from a string value
     * 
     * @throws InvalidArgumentException If validation fails
     */
    public static function fromString(string $value): self
    {
        return new self($value);
    }

    /**
     * Create a Posnr from an integer (automatically zero-padded)
     */
    public static function fromInt(int $value): self
    {
        if ($value < 0 || $value > 999999) {
            throw new InvalidArgumentException(
                'POSNR integer value must be between 0 and 999999'
            );
        }

        return new self(str_pad((string) $value, 6, '0', STR_PAD_LEFT));
    }

    /**
     * Validate POSNR format
     * 
     * @throws InvalidArgumentException If validation fails
     */
    private function validate(): void
    {
        $trimmed = trim($this->value);
        
        if (strlen($trimmed) === 0) {
            throw new InvalidArgumentException('POSNR cannot be empty');
        }

        if (strlen($trimmed) !== 6) {
            throw new InvalidArgumentException(
                sprintf(
                    'POSNR must be exactly 6 characters, got %d characters: "%s"',
                    strlen($trimmed),
                    $trimmed
                )
            );
        }

        if (!ctype_digit($trimmed)) {
            throw new InvalidArgumentException(
                sprintf(
                    'POSNR must contain only digits, got: "%s"',
                    $trimmed
                )
            );
        }
    }

    /**
     * Get the string value
     */
    public function value(): string
    {
        return $this->value;
    }

    /**
     * Convert to string  (e.g., for database persistence)
     */
    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * Check equality with another Posnr
     */
    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    /**
     * Convert to integer (removes leading zeros)
     */
    public function toInt(): int
    {
        return (int) $this->value;
    }
}
