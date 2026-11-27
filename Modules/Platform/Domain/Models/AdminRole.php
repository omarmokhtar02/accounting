<?php

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AdminRole extends Model
{
    public const SUPER_ADMIN = 'super_admin';

    protected $fillable = ['key', 'name_en', 'name_ar', 'is_system'];

    protected function casts(): array
    {
        return ['is_system' => 'boolean'];
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(AdminPermission::class, 'admin_role_permission');
    }

    public function users(): HasMany
    {
        return $this->hasMany(AdminUser::class);
    }

    public function isSuperAdmin(): bool
    {
        return $this->key === self::SUPER_ADMIN;
    }

    public function localizedName(): string
    {
        return app()->getLocale() === 'ar' ? $this->name_ar : $this->name_en;
    }
}
