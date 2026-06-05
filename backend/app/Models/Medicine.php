<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Medicine extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'category_id',
        'supplier_id',
        'name',
        'description',
        'composition',
        'dosage',
        'side_effects',
        'price',
        'minimum_stock',
        'requires_prescription',
        'image',
        'is_active',
    ];

    protected $casts = [
        'price' => 'decimal:2',
        'minimum_stock' => 'integer',
        'requires_prescription' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected $appends = [
        'image_url',
        'total_stock',
    ];

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function getImageUrlAttribute(): ?string
    {
        return $this->image ? asset('storage/'.$this->image) : null;
    }

    public function getTotalStockAttribute(): int
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
