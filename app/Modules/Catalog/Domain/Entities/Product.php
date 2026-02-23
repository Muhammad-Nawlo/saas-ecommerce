<?php

namespace App\Modules\Catalog\Domain\Entities;

class Product
{
    public function __construct(
        public string $id,
        public string $name,
        public string $slug,
        public bool   $active = true
    )
    {
    }
}
