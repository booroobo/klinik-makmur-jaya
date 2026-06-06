<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineVariant extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'medicine_id',
        'name',
        'price',
        'sku',
        'is_active',
        'sort_order',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'is_active' => 'boolean',
        'sort_order' => 'integer',
    ];

    protected $appends = [
        'stock',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function cartItems(): HasMany
    {
        return $this->hasMany(CartItem::class);
    }

    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function getStockAttribute(): int
    {
        if ($this->relationLoaded('batches')) {
            return (int) $this->batches
                ->filter(fn (MedicineBatch $batch): bool => ! $batch->trashed()
                    && $batch->expired_date->gte(now()->startOfDay())
                    && $batch->quantity > 0)
                ->sum('quantity');
        }

        return (int) $this->batches()
            ->whereDate('expired_date', '>=', now()->toDateString())
            ->where('quantity', '>', 0)
            ->sum('quantity');
    }
}
