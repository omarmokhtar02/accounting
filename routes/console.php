<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Trials and paid periods that have ended become "expired" (idempotent, safe to run often).
Schedule::command('subscriptions:expire')->hourly()->withoutOverlapping();
