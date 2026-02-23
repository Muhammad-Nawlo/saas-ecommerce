<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Domain\Entities;

use App\Modules\Catalog\Domain\Events\ProductActivated;
use App\Modules\Catalog\Domain\Events\ProductCreated;
use App\Modules\Catalog\Domain\Events\ProductDeactivated;
use App\Modules\Catalog\Domain\Events\ProductPriceChanged;
use App\Modules\Catalog\Domain\Repositories\ProductRepository;
use App\Modules\Catalog\Domain\ValueObjects\ProductDescription;
use App\Modules\Catalog\Domain\ValueObjects\ProductId;
use App\Modules\Catalog\Domain\ValueObjects\ProductName;
use App\Modules\Shared\Domain\Contracts\AggregateRoot;
use App\Modules\Shared\Domain\Exceptions\BusinessRuleViolation;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\Slug;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Domain\ValueObjects\Uuid;

final class Product implements AggregateRoot
{
    /** @var list<object> */
    private array $domainEvents = [];

    private function __construct(
        private ProductId $id,
        private TenantId $tenantId,
        private ProductName $name,
        private Slug $slug,
        private ProductDescription $description,
        private Money $price,
        private bool $isActive,
        private \DateTimeImmutable $createdAt
    ) {
    }

    public static function create(
        ProductId $id,
        TenantId $tenantId,
        ProductName $name,
        Slug $slug,
        ProductDescription $description,
        Money $price
    ): self {
        $product = new self(
            $id,
            $tenantId,
            $name,
            $slug,
            $description,
            $price,
            true,
            new \DateTimeImmutable()
        );
        $product->recordEvent(new ProductCreated(
            $id,
            $tenantId,
            $name->value(),
            $slug->value(),
            new \DateTimeImmutable()
        ));
        return $product;
    }

    public static function reconstitute(
        ProductId $id,
        TenantId $tenantId,
        ProductName $name,
        Slug $slug,
        ProductDescription $description,
        Money $price,
        bool $isActive,
        \DateTimeImmutable $createdAt
    ): self {
        return new self($id, $tenantId, $name, $slug, $description, $price, $isActive, $createdAt);
    }

    public function rename(ProductName $name): void
    {
        $this->name = $name;
    }

    public function changePrice(Money $newPrice): void
    {
        if ($this->price->equals($newPrice)) {
            return;
        }
        $oldPrice = $this->price;
        $this->price = $newPrice;
        $this->recordEvent(new ProductPriceChanged(
            $this->id,
            $oldPrice,
            $newPrice,
            new \DateTimeImmutable()
        ));
    }

    public function activate(): void
    {
        if ($this->isActive) {
            return;
        }
        $this->isActive = true;
        $this->recordEvent(new ProductActivated($this->id, new \DateTimeImmutable()));
    }

    public function deactivate(): void
    {
        if (!$this->isActive) {
            return;
        }
        $this->isActive = false;
        $this->recordEvent(new ProductDeactivated($this->id, new \DateTimeImmutable()));
    }

    public function ensureSlugUnique(ProductRepository $repository): void
    {
        $existing = $repository->findBySlug($this->slug);
        if ($existing !== null && !$existing->id()->equals($this->id)) {
            throw BusinessRuleViolation::because(
                sprintf('Product with slug "%s" already exists for this tenant', $this->slug->value())
            );
        }
    }

    public function getId(): Uuid
    {
        return $this->id->toUuid();
    }

    /**
     * @return list<object>
     */
    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    public function id(): ProductId
    {
        return $this->id;
    }

    public function tenantId(): TenantId
    {
        return $this->tenantId;
    }

    public function name(): ProductName
    {
        return $this->name;
    }

    public function slug(): Slug
    {
        return $this->slug;
    }

    public function description(): ProductDescription
    {
        return $this->description;
    }

    public function price(): Money
    {
        return $this->price;
    }

    public function isActive(): bool
    {
        return $this->isActive;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @param object $event
     */
    private function recordEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
