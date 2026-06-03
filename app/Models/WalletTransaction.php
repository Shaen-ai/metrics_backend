<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WalletTransaction extends Model
{
    use HasUuids;

    protected $fillable = [
        'user_id',
        'type',
        'amount_usd',
        'balance_after_usd',
        'description',
        'stripe_payment_intent_id',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'amount_usd' => 'decimal:2',
            'balance_after_usd' => 'decimal:2',
            'metadata' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
