<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductTopping extends Model
{
    use HasFactory;

    protected $fillable = [
        'product_id',
        'topping_id',
        'price'
    ];

    protected $casts = [
        'price' => 'decimal:2',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function topping(): BelongsTo
    {
        return $this->belongsTo(Topping::class);
    }
}