<?php

namespace Modules\Shared\Domain\Exceptions;

use RuntimeException;

final class InvalidImportFile extends RuntimeException
{
    /**
     * @param  array<string, scalar>  $replace
     */
    private function __construct(public readonly string $translationKey, public readonly array $replace = [])
    {
        parent::__construct($translationKey);
    }

    public static function unreadable(): self
    {
        return new self('app.import.file_unreadable');
    }

    public static function empty(): self
    {
        return new self('app.import.file_empty');
    }

    public static function tooManyRows(int $max): self
    {
        return new self('app.import.too_many_rows', ['max' => $max]);
    }

    /**
     * @param  list<string>  $missing
     */
    public static function missingColumns(array $missing): self
    {
        return new self('app.import.missing_columns', ['columns' => implode(', ', $missing)]);
    }

    public function userMessage(): string
    {
        return __($this->translationKey, $this->replace);
    }
}
