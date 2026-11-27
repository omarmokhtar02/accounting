<?php

namespace Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * Base class for accounting business-rule failures. The message is a translation
 * key resolved by the presentation layer, so users see it in their own locale.
 */
abstract class DomainException extends RuntimeException
{
    /**
     * @param  array<string, scalar>  $replace
     */
    public function __construct(public readonly string $translationKey, public readonly array $replace = [])
    {
        parent::__construct($translationKey);
    }

    public function userMessage(): string
    {
        return __($this->translationKey, $this->replace);
    }
}
