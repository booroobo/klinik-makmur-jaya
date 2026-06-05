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
        'quantity',
    ];

    protected $casts = [
        'quantity' => 'integer',
    ];

    protected $appends = [
        'line_total',
    ];

    public function cart(): BelongsTo
    {
        return $this->belongsTo(Cart::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function getLineTotalAttribute(): float
    {
        return (float) ($this->medicine?->price ?? 0) * $this->quantity;
    }
}
