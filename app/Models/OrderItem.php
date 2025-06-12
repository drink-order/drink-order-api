<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OrderItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'order_id',
        'product_size_id',
        'quantity',
        'unit_price',
        'sugar_level',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
    ];

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function productSize(): BelongsTo
    {
        return $this->belongsTo(ProductSize::class);
    }

    public function toppings(): HasMany
    {
        return $this->hasMany(OrderTopping::class);
    }

        public function getFormattedSugarLevelAttribute()
    {
        $levels = [
            '0%' => 'No Sugar',
            '25%' => 'Light Sweet',
            '50%' => 'Half Sweet',
            '75%' => 'Less Sweet',
            '100%' => 'Regular'
        ];

        return $levels[$this->sugar_level] ?? 'Regular';
    }
}