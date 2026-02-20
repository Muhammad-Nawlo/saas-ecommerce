<?php

namespace App\Modules\Catalog\Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ProductModel extends Model
{
    protected $table = 'products';

    protected $fillable = [
        'id',
        'name',
        'slug',
        'active',
    ];

    public $incrementing = false;
    protected $keyType = 'string';
}
