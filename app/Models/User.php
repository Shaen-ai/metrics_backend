<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'id',
        'email',
        'password',
        'name',
        'company_name',
        'slug',
        'selected_mode_id',
        'selected_sub_mode_ids',
        'logo',
        'language',
        'currency',
        'paypal_email',
        'planner_material_ids',
        'use_custom_planner_catalog',
        'public_site_layout',
        'public_site_texts',
        'public_site_theme',
        'custom_design_key',
        'plan_tier',
        'trial_ends_at',
        'usage_month_start',
        'image3d_generations_this_month',
        'ai_chat_messages_this_month',
        'email_verification_token',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'selected_sub_mode_ids' => 'array',
            'planner_material_ids' => 'array',
            'use_custom_planner_catalog' => 'boolean',
            'public_site_texts' => 'array',
            'public_site_theme' => 'array',
            'trial_ends_at' => 'datetime',
            'usage_month_start' => 'date',
        ];
    }

    public function selectedMode(): BelongsTo
    {
        return $this->belongsTo(Mode::class, 'selected_mode_id');
    }

    public function selectedSubModes()
    {
        return SubMode::whereIn('id', $this->selected_sub_mode_ids ?? [])->get();
    }

    public function catalogItems(): HasMany
    {
        return $this->hasMany(CatalogItem::class, 'admin_id');
    }

    public function materials(): HasMany
    {
        return $this->hasMany(Material::class, 'admin_id');
    }

    public function modules(): HasMany
    {
        return $this->hasMany(Module::class, 'admin_id');
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class, 'admin_id');
    }

    public function plannerRequests(): HasMany
    {
        return $this->hasMany(PlannerRequest::class, 'admin_id');
    }
}
