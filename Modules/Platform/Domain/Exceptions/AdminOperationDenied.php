<?php

namespace Modules\Platform\Domain\Exceptions;

use RuntimeException;

/**
 * A platform-admin operation that must not be allowed (for example removing the last super admin).
 * Carries a translation key so the admin sees it in their language.
 */
final class AdminOperationDenied extends RuntimeException
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

    public static function lastSuperAdmin(): self
    {
        return new self('platform.errors.last_super_admin');
    }

    public static function systemRole(): self
    {
        return new self('platform.errors.system_role');
    }

    public static function roleInUse(int $users): self
    {
        return new self('platform.errors.role_in_use', ['users' => $users]);
    }

    public static function cannotDeactivateSelf(): self
    {
        return new self('platform.errors.cannot_deactivate_self');
    }

    public static function weakPassword(): self
    {
        return new self('platform.errors.weak_password');
    }
}
