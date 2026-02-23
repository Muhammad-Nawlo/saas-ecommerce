<?php

namespace App\Modules\Shared\Domain\Contracts;

interface RepositoryInterface
{
    public function find(string $id);

    public function save(object $entity): void;

    public function delete(string $id): void;
}
