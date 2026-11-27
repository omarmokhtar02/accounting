<?php

namespace Modules\Accounting\Domain\Exceptions;

use Modules\Accounting\Domain\Enums\JournalStatus;

final class InvalidJournalState extends DomainException
{
    public static function transition(JournalStatus $from, JournalStatus $to): self
    {
        return new self('accounting.errors.invalid_transition', [
            'from' => $from->label(),
            'to' => $to->label(),
        ]);
    }

    public static function invalidAccount(): self
    {
        return new self('accounting.errors.invalid_account');
    }

    public static function unpostedDrafts(): self
    {
        return new self('accounting.errors.unposted_drafts');
    }

    public static function selfPosting(): self
    {
        return new self('accounting.errors.segregation_of_duties');
    }
}
