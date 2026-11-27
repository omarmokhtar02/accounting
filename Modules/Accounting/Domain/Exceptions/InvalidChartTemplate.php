<?php

namespace Modules\Accounting\Domain\Exceptions;

use RuntimeException;

/**
 * A chart-of-accounts template is structurally wrong. This is a developer/data error (a broken
 * country pack), so it carries the full list of problems rather than a translated user message.
 */
final class InvalidChartTemplate extends RuntimeException
{
    /**
     * @param  list<string>  $problems
     */
    public function __construct(public readonly array $problems)
    {
        parent::__construct('Invalid chart of accounts template: '.implode('; ', $problems));
    }
}
