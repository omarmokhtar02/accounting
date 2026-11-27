<?php

namespace Modules\Subscriptions\Domain\Exceptions;

use RuntimeException;

/**
 * The tenant's plan does not allow more users or companies. Surfaced to the user with an upgrade hint.
 */
final class PlanLimitReached extends RuntimeException
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

    public static function users(int $max): self
    {
        return new self('subscriptions.errors.user_limit', ['max' => $max]);
    }

    public static function companies(int $max): self
    {
        return new self('subscriptions.errors.company_limit', ['max' => $max]);
    }

    public static function noAccess(): self
    {
        return new self('subscriptions.errors.no_access');
    }
}
