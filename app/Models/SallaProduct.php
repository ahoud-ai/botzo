<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A copy of a store's product, so the assistant can search it without calling Salla on every message.
 */
class SallaProduct extends Model
{
    protected $guarded = [];

    protected $casts = [
        'salla_product_id' => 'integer',
        'price' => 'float',
        'regular_price' => 'float',
        'is_available' => 'boolean',
        'options' => 'array',
    ];
}
