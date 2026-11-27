<?php

namespace Modules\Tenancy\Infrastructure\Exceptions;

use RuntimeException;

final class InvalidTenantContext extends RuntimeException
{
    public static function missing(): self
    {
        return new self('No tenant context is initialised.');
    }
}
