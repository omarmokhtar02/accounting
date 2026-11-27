<?php

namespace Modules\Localization\Presentation\Console;

use Illuminate\Console\Command;
use Modules\Localization\Application\SyncTaxDefaults;

class SyncTaxDefaultsCommand extends Command
{
    protected $signature = 'tax:sync-defaults';

    protected $description = 'Insert the default tax rates of every country pack that are missing (never overwrites admin edits)';

    public function handle(SyncTaxDefaults $sync): int
    {
        $this->info('Inserted '.$sync->handle().' default tax rate(s).');

        return self::SUCCESS;
    }
}
