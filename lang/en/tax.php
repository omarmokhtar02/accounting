<?php

return [
    'codes_title' => 'Taxes',
    'managed_by_platform' => 'Tax rates are set per country by the platform and apply automatically from their effective date.',
    'code' => 'Code',
    'current_rate' => 'Current rate',
    'next_change' => 'Next change',
    'not_configured' => 'Not configured yet',
    'no_taxes' => 'No taxes are defined for this country.',
    'kinds' => [
        'vat' => 'VAT',
        'withholding' => 'Withholding',
        'table_tax' => 'Table tax',
        'stamp_duty' => 'Stamp duty',
        'other' => 'Other',
    ],
    'platform_title' => 'Tax rates by country',
    'platform_help' => 'A rate applies from its effective date until the next one. Schedule a law change in advance or enter it today; rates already in force are history and cannot be edited, so posted documents are never re-rated.',
    'new_rate_percent' => 'New rate (%)',
    'effective_from' => 'Effective from',
    'note' => 'Note (law / decree reference)',
    'schedule_rate' => 'Set rate',
    'rate_scheduled' => 'Tax rate saved.',
    'rate_removed' => 'Scheduled rate removed.',
    'errors' => [
        'rate_not_configured' => 'No rate is configured for :code in :country on :date. Ask the platform admin to set it.',
        'in_the_past' => 'A rate cannot take effect in the past. Use today or a later date.',
        'duplicate_date' => 'A rate for this tax code already starts on :date.',
        'rate_out_of_range' => 'The rate must be a percentage between 0 and 100 with at most two decimals.',
        'already_in_force' => 'This rate is already in force (or in the past) and is kept as history.',
        'unknown_code' => 'The tax code :code does not exist for :country.',
    ],
];
