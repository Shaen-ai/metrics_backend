<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ModuleConnectionPoint extends Model
{
    protected $fillable = [
        'module_id',
        'position',
        'type',
    ];

    public function module(): BelongsTo
    {
        return $this->belongsTo(Module::class);
    }
}
