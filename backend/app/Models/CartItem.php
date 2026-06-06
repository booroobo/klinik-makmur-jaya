<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CartItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'cart_id',
        'medicine_id',
        'medicine_variant_id',
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $appends = [
        'line_total',
        'unit_price',
        'available_stock',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MedicineVariant::class, 'medicine_variant_id');
    }

    public function getUnitPriceAttribute(): float
    {
        return (float) ($this->variant?->price ?? $this->medicine?->price ?? 0);
    }

    public function getAvailableStockAttribute(): int
    {
        return $this->variant
            ? $this->variant->stock
            : (int) ($this->medicine?->total_stock ?? 0);
    }

    public function getLineTotalAttribute(): float
    {
        return $this->unit_price * $this->quantity;
    }
}
