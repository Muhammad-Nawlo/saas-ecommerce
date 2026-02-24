<?php

declare(strict_types=1);

namespace App\Modules\Cart\Infrastructure\Persistence;

use App\Modules\Cart\Domain\Entities\Cart;
use App\Modules\Cart\Domain\Entities\CartItem;
use App\Modules\Cart\Domain\Repositories\CartRepository;
use App\Modules\Cart\Domain\ValueObjects\CartId;
use App\Modules\Cart\Domain\ValueObjects\CartItemId;
use App\Modules\Cart\Domain\ValueObjects\CartStatus;
use App\Modules\Cart\Domain\ValueObjects\CustomerEmail;
use App\Modules\Cart\Domain\ValueObjects\ProductId;
use App\Modules\Cart\Domain\ValueObjects\Quantity;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Illuminate\Database\Eloquent\Model;

final class EloquentCartRepository implements CartRepository
{
    private const CART_MODEL = CartModel::class;

    public function __construct(
        private TransactionManager $transactionManager,
        private ?EventBus $eventBus = null
    ) {
    }

    public function save(Cart $cart): void
    {
        $this->transactionManager->run(function () use ($cart): void {
            $tenantId = $this->currentTenantId();
            $modelClass = self::CART_MODEL;
            $existingCart = $modelClass::forTenant($tenantId)->find($cart->id()->value());
            $cartModel = $existingCart ?? new CartModel();
            $cartModel->id = $cart->id()->value();
            $cartModel->tenant_id = $tenantId;
            $cartModel->customer_email = $cart->customerEmail()?->value();
            $cartModel->session_id = $cart->sessionId();
            $cartModel->status = $cart->status()->value();
            $cartModel->total_amount = $cart->totalAmount()->amountInMinorUnits();
            $cartModel->currency = $cart->totalAmount()->currency();
            $cartModel->created_at = $cart->createdAt();
            $cartModel->updated_at = $cart->updatedAt();
            $cartModel->save();

            $existingItemIds = $cartModel->items()->pluck('id')->all();
            $currentItemIds = array_map(fn (CartItem $i) => $i->id()->value(), $cart->items());
            foreach ($existingItemIds as $eid) {
                if (!in_array($eid, $currentItemIds, true)) {
                    CartItemModel::where('cart_id', $cartModel->id)->where('id', $eid)->delete();
                }
            }
            foreach ($cart->items() as $item) {
                $itemModel = CartItemModel::where('cart_id', $cartModel->id)->find($item->id()->value());
                if ($itemModel === null) {
                    $itemModel = new CartItemModel();
                }
                $itemModel->id = $item->id()->value();
                $itemModel->cart_id = $cartModel->id;
                $itemModel->product_id = $item->productId()->value();
                $itemModel->quantity = $item->quantity();
                $itemModel->unit_price_amount = $item->unitPrice()->amountInMinorUnits();
                $itemModel->unit_price_currency = $item->unitPrice()->currency();
                $itemModel->total_price_amount = $item->totalPrice()->amountInMinorUnits();
                $itemModel->total_price_currency = $item->totalPrice()->currency();
                $itemModel->save();
            }

            foreach ($cart->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    public function findById(CartId $id): ?Cart
    {
        $modelClass = self::CART_MODEL;
        $tenantId = $this->currentTenantId();
        $cartModel = $modelClass::forTenant($tenantId)->with('items')->find($id->value());
        return $cartModel !== null ? $this->toDomain($cartModel) : null;
    }

    public function findActiveByCustomer(string $email): ?Cart
    {
        $modelClass = self::CART_MODEL;
        $tenantId = $this->currentTenantId();
        $cartModel = $modelClass::forTenant($tenantId)
            ->where('customer_email', strtolower(trim($email)))
            ->where('status', CartStatus::ACTIVE)
            ->with('items')
            ->first();
        return $cartModel !== null ? $this->toDomain($cartModel) : null;
    }

    public function findActiveBySession(string $sessionId): ?Cart
    {
        $modelClass = self::CART_MODEL;
        $tenantId = $this->currentTenantId();
        $cartModel = $modelClass::forTenant($tenantId)
            ->where('session_id', $sessionId)
            ->where('status', CartStatus::ACTIVE)
            ->with('items')
            ->first();
        return $cartModel !== null ? $this->toDomain($cartModel) : null;
    }

    public function delete(Cart $cart): void
    {
        $this->transactionManager->run(function () use ($cart): void {
            $modelClass = self::CART_MODEL;
            $tenantId = $this->currentTenantId();
            $cartModel = $modelClass::forTenant($tenantId)->find($cart->id()->value());
            if ($cartModel !== null) {
                $cartModel->delete();
            }
            foreach ($cart->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    private function toDomain(Model $model): Cart
    {
        assert($model instanceof CartModel);
        $createdAt = $model->created_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->created_at)
            : new \DateTimeImmutable($model->created_at);
        $updatedAt = $model->updated_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->updated_at)
            : new \DateTimeImmutable($model->updated_at);
        $customerEmail = $model->customer_email !== null && $model->customer_email !== ''
            ? CustomerEmail::fromString($model->customer_email)
            : null;
        $items = [];
        foreach ($model->items as $itemModel) {
            assert($itemModel instanceof CartItemModel);
            $items[] = CartItem::create(
                CartItemId::fromString($itemModel->id),
                ProductId::fromString($itemModel->product_id),
                Quantity::fromInt($itemModel->quantity),
                Money::fromMinorUnits($itemModel->unit_price_amount, $itemModel->unit_price_currency)
            );
        }
        return Cart::reconstitute(
            CartId::fromString($model->id),
            TenantId::fromString($model->tenant_id),
            $customerEmail,
            $model->session_id,
            CartStatus::fromString($model->status),
            Money::fromMinorUnits($model->total_amount, $model->currency),
            $createdAt,
            $updatedAt,
            $items
        );
    }

    private function currentTenantId(): string
    {
        $tenant = tenant();
        if ($tenant === null) {
            throw new \RuntimeException('Tenant context is required to access carts');
        }
        return (string) $tenant->getTenantKey();
    }
}
