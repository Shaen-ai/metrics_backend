<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ScrapeUrl extends Model
{
    protected $fillable = [
        'source_marketplace',
        'url',
        'url_hash',
        'external_id',
        'discovery_source',
        'status',
        'fail_count',
        'last_error',
        'scraped_at',
        'last_seen_in_listing_at',
        'last_verified_at',
    ];

    protected static function booted(): void
    {
        static::saving(function (self $model) {
            if ($model->isDirty('url') || empty($model->url_hash)) {
                $model->url_hash = hash('sha256', $model->url);
            }
        });
    }

    protected function casts(): array
    {
        return [
            'scraped_at' => 'datetime',
            'last_seen_in_listing_at' => 'datetime',
            'last_verified_at' => 'datetime',
        ];
    }

    public function scopePending($query)
    {
        return $query->where('status', 'pending');
    }

    public function scopeFailed($query)
    {
        return $query->where('status', 'failed');
    }

    public function scopeMarketplace($query, string $marketplace)
    {
        return $query->where('source_marketplace', $marketplace);
    }

    public function markDone(): void
    {
        $this->update([
            'status' => 'done',
            'scraped_at' => now(),
        ]);
    }

    public function markFailed(string $error): void
    {
        $this->update([
            'status' => $this->fail_count >= 2 ? 'failed' : 'pending',
            'fail_count' => $this->fail_count + 1,
            'last_error' => $error,
        ]);
    }

    /**
     * Product confirmed not to exist (404/410 or redirected to homepage).
     * Permanently mark as failed so we never retry.
     */
    public function markNotFound(): void
    {
        $this->update([
            'status' => 'failed',
            'fail_count' => 99,
            'last_error' => 'Product does not exist (404/gone/redirect)',
        ]);
    }
}
