<?php

namespace Modules\Subscriptions\Domain\Enums;

/**
 * What a tenant may do right now: everything, only read and export (grace after expiry), or nothing
 * except the billing page.
 */
enum AccessLevel: string
{
    case Full = 'full';
    case ReadOnly = 'read_only';
    case Blocked = 'blocked';

    public function allowsWrites(): bool
    {
        return $this === self::Full;
    }

    public function allowsReads(): bool
    {
        return $this !== self::Blocked;
    }
}
