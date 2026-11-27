<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Platform\Application\SaveAdminUser;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;

/**
 * Creates the first platform super admin from PLATFORM_ADMIN_EMAIL / PLATFORM_ADMIN_PASSWORD.
 * Safe to run on every deploy: it skips when the variables are empty or the admin already exists,
 * and it never changes an existing admin's password.
 */
class PlatformAdminSeeder extends Seeder
{
    public function run(SaveAdminUser $save): void
    {
        $email = strtolower((string) config('platform.admin.email'));
        $password = (string) config('platform.admin.password');

        if ($email === '' || $password === '') {
            $this->command?->warn('PlatformAdminSeeder skipped: set PLATFORM_ADMIN_EMAIL and PLATFORM_ADMIN_PASSWORD (12+ characters).');

            return;
        }

        if (AdminUser::where('email', $email)->exists()) {
            $this->command?->info("Platform admin {$email} already exists.");

            return;
        }

        $save->create((string) config('platform.admin.name'), $email, $password, AdminRole::where('key', 'super_admin')->firstOrFail());

        $this->command?->info("Platform super admin {$email} created. Sign in at /platform/login.");
    }
}
