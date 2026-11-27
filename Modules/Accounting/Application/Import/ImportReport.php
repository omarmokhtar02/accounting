<?php

namespace Modules\Accounting\Application\Import;

/**
 * Outcome of validating or applying an import file. Nothing is written when there are errors.
 */
final readonly class ImportReport
{
    /**
     * @param  list<array{row: int, key: string, replace: array<string, scalar>}>  $errors
     */
    public function __construct(
        public int $total,
        public int $created,
        public int $skipped,
        public array $errors,
        public bool $committed,
    ) {}

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }
}
