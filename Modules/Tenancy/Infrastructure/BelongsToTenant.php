<?php

namespace Modules\Tenancy\Infrastructure;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Tenancy\Domain\Models\Tenant;
use Modules\Tenancy\Infrastructure\Exceptions\InvalidTenantContext;

/**
 * Scopes every query to the current tenant and stamps tenant_id on create.
 * Fails closed: with no tenant context, queries return nothing and creates throw.
 */
trait BelongsToTenant
{
    public static function bootBelongsToTenant(): void
    {
        static::addGlobalScope('tenant', function (Builder $builder): void {
            $tenantId = app(TenantContext::class)->tenantId();

            if ($tenantId === null) {
                $builder->whereRaw('1 = 0');

                return;
            }

            $builder->where($builder->getModel()->getTable().'.tenant_id', $tenantId);
        });

        static::creating(function ($model): void {
            if ($model->tenant_id !== null) {
                return;
            }

            $tenantId = app(TenantContext::class)->tenantId();

            if ($tenantId === null) {
                throw InvalidTenantContext::missing();
            }

            $model->tenant_id = $tenantId;
        });
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }
}
