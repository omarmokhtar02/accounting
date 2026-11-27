<?php

namespace Modules\Platform\Domain\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * A platform administrator (the people who run the SaaS). Distinct from tenant users: separate login,
 * separate guard ("admin"), separate roles and permissions.
 */
class AdminUser extends Authenticatable
{
    protected $fillable = ['name', 'email', 'password', 'admin_role_id', 'locale', 'is_active', 'last_login_at'];

    protected $hidden = ['password', 'remember_token'];

    /** @var list<string>|null */
    private ?array $permissionKeys = null;

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class, 'admin_role_id');
    }

    public function isSuperAdmin(): bool
    {
        return $this->role->isSuperAdmin();
    }

    /** Super admins can do everything, whatever the permission rows say. */
    public function hasPermission(string $key): bool
    {
        if ($this->isSuperAdmin()) {
            return true;
        }

        $this->permissionKeys ??= $this->role->permissions()->pluck('key')->all();

        return in_array($key, $this->permissionKeys, true);
    }
}
