<?php

declare(strict_types=1);

namespace App\Modules\Shared\Domain\ValueObjects;

use App\Modules\Shared\Domain\Exceptions\InvalidValueObject;

final readonly class Slug
{
    private const SLUG_REGEX = '/^[a-z0-9]+(?:-[a-z0-9]+)*$/';

    private function __construct(
        private string $value
    ) {
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));
        if ($normalized === '') {
            throw InvalidValueObject::forValue(self::class, $value, 'Slug cannot be empty');
        }
        if (!preg_match(self::SLUG_REGEX, $normalized)) {
            throw InvalidValueObject::forValue(
                self::class,
                $value,
                'Slug must contain only lowercase letters, numbers and hyphens'
            );
        }
        return new self($normalized);
    }

    public static function fromTitle(string $title): self
    {
        $slug = strtolower(trim($title));
        $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
        $slug = preg_replace('/[\s-]+/', '-', $slug);
        $slug = trim($slug, '-');
        if ($slug === '') {
            throw InvalidValueObject::forValue(self::class, $title, 'Could not generate slug from title');
        }
        return new self($slug);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(Slug $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
