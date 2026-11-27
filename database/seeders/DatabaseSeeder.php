<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Modules\Identity\Application\RegisterTenant;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds a local demo tenant (owner + one company, no transactions).
     */
    public function run(): void
    {
        $this->call(PlatformAdminSeeder::class);

        if (! app()->environment('local')) {
            return;
        }

        app(RegisterTenant::class)->handle(
            'Demo Owner',
            'owner@example.com',
            Hash::make('password'),
            'Demo Company',
            'EG',
            'en',
        );
    }
}
