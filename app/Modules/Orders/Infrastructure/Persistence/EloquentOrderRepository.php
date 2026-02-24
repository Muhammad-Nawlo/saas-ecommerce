<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Persistence;

use App\Modules\Orders\Domain\Entities\Order;
use App\Modules\Orders\Domain\Entities\OrderItem;
use App\Modules\Orders\Domain\Repositories\OrderRepository;
use App\Modules\Orders\Domain\ValueObjects\CustomerEmail;
use App\Modules\Orders\Domain\ValueObjects\OrderId;
use App\Modules\Orders\Domain\ValueObjects\OrderItemId;
use App\Modules\Orders\Domain\ValueObjects\OrderStatus;
use App\Modules\Orders\Domain\ValueObjects\ProductId;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Illuminate\Database\Eloquent\Model;

final class EloquentOrderRepository implements OrderRepository
{
    private const ORDER_MODEL = OrderModel::class;

    public function __construct(
        private TransactionManager $transactionManager,
        private ?EventBus $eventBus = null
    ) {
    }

    public function save(Order $order): void
    {
        $this->transactionManager->run(function () use ($order): void {
            $tenantId = $this->currentTenantId();
            $orderModelClass = self::ORDER_MODEL;
            $existingOrder = $orderModelClass::forTenant($tenantId)->find($order->id()->value());
            $orderModel = $existingOrder ?? new OrderModel();
            $orderModel->id = $order->id()->value();
            $orderModel->tenant_id = $tenantId;
            $orderModel->customer_email = $order->customerEmail()->value();
            $orderModel->status = $order->status()->value();
            $orderModel->total_amount = $order->totalAmount()->amountInMinorUnits();
            $orderModel->currency = $order->totalAmount()->currency();
            $orderModel->created_at = $order->createdAt();
            $orderModel->updated_at = $order->updatedAt();
            $orderModel->save();

            $existingItemIds = $orderModel->items()->pluck('id')->all();
            $currentItemIds = array_map(fn (OrderItem $i) => $i->id()->value(), $order->items());
            foreach ($existingItemIds as $eid) {
                if (!in_array($eid, $currentItemIds, true)) {
                    OrderItemModel::where('order_id', $orderModel->id)->where('id', $eid)->delete();
                }
            }
            foreach ($order->items() as $item) {
                $itemModel = OrderItemModel::where('order_id', $orderModel->id)->find($item->id()->value());
                if ($itemModel === null) {
                    $itemModel = new OrderItemModel();
                }
                $itemModel->id = $item->id()->value();
                $itemModel->order_id = $orderModel->id;
                $itemModel->product_id = $item->productId()->value();
                $itemModel->quantity = $item->quantity();
                $itemModel->unit_price_amount = $item->unitPrice()->amountInMinorUnits();
                $itemModel->unit_price_currency = $item->unitPrice()->currency();
                $itemModel->total_price_amount = $item->totalPrice()->amountInMinorUnits();
                $itemModel->total_price_currency = $item->totalPrice()->currency();
                $itemModel->save();
            }

            foreach ($order->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    public function findById(OrderId $id): ?Order
    {
        $orderModelClass = self::ORDER_MODEL;
        $tenantId = $this->currentTenantId();
        $orderModel = $orderModelClass::forTenant($tenantId)->with('items')->find($id->value());
        return $orderModel !== null ? $this->toDomain($orderModel) : null;
    }

    public function delete(Order $order): void
    {
        $this->transactionManager->run(function () use ($order): void {
            $orderModelClass = self::ORDER_MODEL;
            $tenantId = $this->currentTenantId();
            $orderModel = $orderModelClass::forTenant($tenantId)->find($order->id()->value());
            if ($orderModel !== null) {
                $orderModel->delete();
            }
            foreach ($order->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    private function toDomain(Model $model): Order
    {
        assert($model instanceof OrderModel);
        $createdAt = $model->created_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->created_at)
            : new \DateTimeImmutable($model->created_at);
        $updatedAt = $model->updated_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->updated_at)
            : new \DateTimeImmutable($model->updated_at);
        $items = [];
        foreach ($model->items as $itemModel) {
            assert($itemModel instanceof OrderItemModel);
            $items[] = OrderItem::create(
                OrderItemId::fromString($itemModel->id),
                ProductId::fromString($itemModel->product_id),
                \App\Modules\Orders\Domain\ValueObjects\Quantity::fromInt($itemModel->quantity),
                Money::fromMinorUnits($itemModel->unit_price_amount, $itemModel->unit_price_currency)
            );
        }
        return Order::reconstitute(
            OrderId::fromString($model->id),
            TenantId::fromString($model->tenant_id),
            CustomerEmail::fromString($model->customer_email),
            OrderStatus::fromString($model->status),
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
            throw new \RuntimeException('Tenant context is required to access orders');
        }
        return (string) $tenant->getTenantKey();
    }
}
