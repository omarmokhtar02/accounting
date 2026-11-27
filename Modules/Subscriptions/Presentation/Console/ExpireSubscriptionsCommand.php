<?php

namespace Modules\Subscriptions\Presentation\Console;

use Illuminate\Console\Command;
use Modules\Subscriptions\Application\ExpireDueSubscriptions;

class ExpireSubscriptionsCommand extends Command
{
    protected $signature = 'subscriptions:expire';

    protected $description = 'Expire trials and subscriptions whose period has ended (idempotent, safe to run hourly)';

    public function handle(ExpireDueSubscriptions $expire): int
    {
        $count = $expire->handle();

        $this->info("Expired {$count} subscription(s).");

        return self::SUCCESS;
    }
}
