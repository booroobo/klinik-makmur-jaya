<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicineBatch extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'medicine_id',
        'medicine_variant_id',
        'batch_number',
        'expired_date',
        'quantity',
        'purchase_price',
    ];

    protected $casts = [
        'expired_date' => 'date',
        'quantity' => 'integer',
        'purchase_price' => 'decimal:2',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(MedicineVariant::class, 'medicine_variant_id');
    }

    public function orderItemUsages(): HasMany
    {
        return $this->hasMany(OrderItemBatch::class);
    }
}
