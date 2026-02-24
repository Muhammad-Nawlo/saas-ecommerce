<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Persistence;

use App\Modules\Payments\Domain\Entities\Payment;
use App\Modules\Payments\Domain\Repositories\PaymentRepository;
use App\Modules\Payments\Domain\ValueObjects\OrderId;
use App\Modules\Payments\Domain\ValueObjects\PaymentId;
use App\Modules\Payments\Domain\ValueObjects\PaymentProvider;
use App\Modules\Payments\Domain\ValueObjects\PaymentStatus;
use App\Modules\Shared\Domain\ValueObjects\Money;
use App\Modules\Shared\Domain\ValueObjects\TenantId;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use App\Modules\Shared\Infrastructure\Persistence\TransactionManager;
use Illuminate\Database\Eloquent\Model;

final class EloquentPaymentRepository implements PaymentRepository
{
    private const MODEL_CLASS = PaymentModel::class;

    public function __construct(
        private TransactionManager $transactionManager,
        private ?EventBus $eventBus = null
    ) {
    }

    public function save(Payment $payment): void
    {
        $this->transactionManager->run(function () use ($payment): void {
            $tenantId = $this->currentTenantId();
            $modelClass = self::MODEL_CLASS;
            $existing = $modelClass::forTenant($tenantId)->find($payment->id()->value());
            $model = $existing ?? new PaymentModel();
            $model->id = $payment->id()->value();
            $model->tenant_id = $tenantId;
            $model->order_id = $payment->orderId()->value();
            $model->amount = $payment->amount()->amountInMinorUnits();
            $model->currency = $payment->amount()->currency();
            $model->status = $payment->status()->value();
            $model->provider = $payment->provider()->value();
            $model->provider_payment_id = $payment->providerPaymentId();
            $model->created_at = $payment->createdAt();
            $model->updated_at = $payment->updatedAt();
            $model->save();
            foreach ($payment->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    public function findById(PaymentId $id): ?Payment
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $model = $modelClass::forTenant($tenantId)->find($id->value());
        return $model !== null ? $this->toDomain($model) : null;
    }

    public function findByOrderId(OrderId $orderId): array
    {
        $modelClass = self::MODEL_CLASS;
        $tenantId = $this->currentTenantId();
        $models = $modelClass::forTenant($tenantId)->where('order_id', $orderId->value())->orderBy('created_at', 'desc')->get();
        $payments = [];
        foreach ($models as $model) {
            $payments[] = $this->toDomain($model);
        }
        return $payments;
    }

    public function delete(Payment $payment): void
    {
        $this->transactionManager->run(function () use ($payment): void {
            $modelClass = self::MODEL_CLASS;
            $tenantId = $this->currentTenantId();
            $model = $modelClass::forTenant($tenantId)->find($payment->id()->value());
            if ($model !== null) {
                $model->delete();
            }
            foreach ($payment->pullDomainEvents() as $event) {
                if ($this->eventBus !== null) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    private function toDomain(Model $model): Payment
    {
        assert($model instanceof PaymentModel);
        $createdAt = $model->created_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->created_at)
            : new \DateTimeImmutable($model->created_at);
        $updatedAt = $model->updated_at instanceof \DateTimeInterface
            ? \DateTimeImmutable::createFromInterface($model->updated_at)
            : new \DateTimeImmutable($model->updated_at);
        return Payment::reconstitute(
            PaymentId::fromString($model->id),
            TenantId::fromString($model->tenant_id),
            OrderId::fromString($model->order_id),
            Money::fromMinorUnits($model->amount, $model->currency),
            PaymentStatus::fromString($model->status),
            PaymentProvider::fromString($model->provider),
            $model->provider_payment_id,
            $createdAt,
            $updatedAt
        );
    }

    private function currentTenantId(): string
    {
        $tenant = tenant();
        if ($tenant === null) {
            throw new \RuntimeException('Tenant context is required to access payments');
        }
        return (string) $tenant->getTenantKey();
    }
}
