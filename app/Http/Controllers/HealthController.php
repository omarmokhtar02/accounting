<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Readiness probe: /up only proves PHP boots, /health also proves the database and cache answer.
 */
class HealthController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $checks = [
            'database' => $this->check(fn () => DB::select('select 1')),
            'cache' => $this->check(function () {
                Cache::put('health-check', '1', 5);

                return Cache::get('health-check') === '1';
            }),
        ];

        $healthy = ! in_array(false, $checks, true);

        return response()->json(['status' => $healthy ? 'ok' : 'degraded', 'checks' => $checks], $healthy ? 200 : 503);
    }

    private function check(callable $probe): bool
    {
        try {
            return $probe() !== false;
        } catch (Throwable $e) {
            report($e);

            return false;
        }
    }
}
