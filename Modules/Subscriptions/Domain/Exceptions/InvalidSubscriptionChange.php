<?php

namespace Modules\Subscriptions\Domain\Exceptions;

use Modules\Subscriptions\Domain\Enums\SubscriptionStatus;
use RuntimeException;

/**
 * A subscription operation that is not allowed. The message is a translation key so the
 * admin dashboard shows it in the admin's language.
 */
final class InvalidSubscriptionChange extends RuntimeException
{
    /** @param array<string, scalar> $replace */
    public function __construct(public readonly string $translationKey, public readonly array $replace = [])
    {
        parent::__construct($translationKey);
    }

    public function userMessage(): string
    {
        return __($this->translationKey, $this->replace);
    }

    public static function transition(SubscriptionStatus $from, SubscriptionStatus $to): self
    {
        return new self('subscriptions.errors.transition', ['from' => $from->label(), 'to' => $to->label()]);
    }

    public static function alreadyLive(): self
    {
        return new self('subscriptions.errors.already_live');
    }

    public static function notTrialing(): self
    {
        return new self('subscriptions.errors.not_trialing');
    }

    public static function planInactive(): self
    {
        return new self('subscriptions.errors.plan_inactive');
    }

    public static function invalidDays(): self
    {
        return new self('subscriptions.errors.invalid_days');
    }

    public static function noSubscription(): self
    {
        return new self('subscriptions.errors.no_subscription');
    }
}
