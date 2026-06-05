<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Order extends Model
{
    use HasFactory;

    public const STATUS_PENDING_PAYMENT = 'pending_payment';
    public const STATUS_PAID = 'paid';
    public const STATUS_WAITING_PRESCRIPTION_REVIEW = 'waiting_prescription_review';
    public const STATUS_LEGACY_WAITING_PRESCRIPTION = 'waiting_prescription';
    public const STATUS_CONFIRMED = 'confirmed';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_READY_FOR_PICKUP = 'ready_for_pickup';
    public const STATUS_OUT_FOR_DELIVERY = 'out_for_delivery';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_REJECTED = 'rejected';

    public const STATUSES = [
        self::STATUS_PENDING_PAYMENT,
        self::STATUS_PAID,
        self::STATUS_WAITING_PRESCRIPTION_REVIEW,
        self::STATUS_CONFIRMED,
        self::STATUS_PROCESSING,
        self::STATUS_READY_FOR_PICKUP,
        self::STATUS_OUT_FOR_DELIVERY,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
        self::STATUS_REJECTED,
    ];

    public const PAYMENT_STATUS_UNPAID = 'unpaid';
    public const PAYMENT_STATUS_PAID = 'paid';
    public const PAYMENT_STATUS_FAILED = 'failed';
    public const PAYMENT_STATUS_REFUNDED = 'refunded';

    public const PAYMENT_STATUSES = [
        self::PAYMENT_STATUS_UNPAID,
        self::PAYMENT_STATUS_PAID,
        self::PAYMENT_STATUS_FAILED,
        self::PAYMENT_STATUS_REFUNDED,
    ];

    public const FULFILLMENT_PICKUP = 'pickup';
    public const FULFILLMENT_DELIVERY = 'delivery';

    public const FULFILLMENT_METHODS = [
        self::FULFILLMENT_PICKUP,
        self::FULFILLMENT_DELIVERY,
    ];

    protected $fillable = [
        'user_id',
        'order_number',
        'status',
        'fulfillment_method',
        'payment_method',
        'payment_status',
        'subtotal',
        'service_fee',
        'delivery_fee',
        'total',
        'customer_name',
        'customer_phone',
        'customer_address',
        'notes',
    ];

    protected $casts = [
        'subtotal' => 'decimal:2',
        'service_fee' => 'decimal:2',
        'delivery_fee' => 'decimal:2',
        'total' => 'decimal:2',
    ];

    protected $appends = [
        'normalized_status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function normalizedStatus(): string
    {
        return $this->status === self::STATUS_LEGACY_WAITING_PRESCRIPTION
            ? self::STATUS_WAITING_PRESCRIPTION_REVIEW
            : $this->status;
    }

    public function getNormalizedStatusAttribute(): string
    {
        return $this->normalizedStatus();
    }
}
