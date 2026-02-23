<?php

declare(strict_types=1);

namespace App\Modules\Shared\Infrastructure\Persistence;

use App\Modules\Shared\Domain\Contracts\AggregateRoot;
use App\Modules\Shared\Domain\Contracts\Repository;
use App\Modules\Shared\Domain\ValueObjects\Uuid;
use App\Modules\Shared\Infrastructure\Messaging\EventBus;
use Illuminate\Database\Eloquent\Model;

abstract class BaseEloquentRepository implements Repository
{
    public function __construct(
        protected TransactionManager $transactionManager,
        protected ?EventBus $eventBus = null
    ) {
    }

    abstract protected function modelClass(): string;

    abstract protected function toDomain(Model $model): object;

    abstract protected function toModel(object $aggregate): Model;

    public function find(Uuid $id): ?object
    {
        $modelClass = $this->modelClass();
        $model = $modelClass::find($id->value());
        return $model !== null ? $this->toDomain($model) : null;
    }

    public function save(object $aggregate): void
    {
        $this->transactionManager->run(function () use ($aggregate): void {
            $model = $this->toModel($aggregate);
            $model->save();
            if ($aggregate instanceof AggregateRoot && $this->eventBus !== null) {
                foreach ($aggregate->pullDomainEvents() as $event) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }

    public function remove(object $aggregate): void
    {
        $this->transactionManager->run(function () use ($aggregate): void {
            $model = $this->toModel($aggregate);
            $model->delete();
            if ($aggregate instanceof AggregateRoot && $this->eventBus !== null) {
                foreach ($aggregate->pullDomainEvents() as $event) {
                    $this->eventBus->publish($event);
                }
            }
        });
    }
}
