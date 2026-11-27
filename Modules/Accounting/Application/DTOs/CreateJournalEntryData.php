<?php

namespace Modules\Accounting\Application\DTOs;

use Carbon\CarbonImmutable;

final readonly class CreateJournalEntryData
{
    /**
     * @param  list<JournalLineData>  $lines
     */
    public function __construct(
        public CarbonImmutable $entryDate,
        public string $description,
        public array $lines,
        public ?string $idempotencyKey = null,
        public ?string $sourceType = null,
        public ?int $sourceId = null,
    ) {}
}
