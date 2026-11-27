<?php

namespace Modules\Platform\Presentation\Console;

use Illuminate\Console\Command;
use Modules\Platform\Application\SaveAdminUser;
use Modules\Platform\Domain\Exceptions\AdminOperationDenied;
use Modules\Platform\Domain\Models\AdminRole;
use Modules\Platform\Domain\Models\AdminUser;

class CreateAdminCommand extends Command
{
    protected $signature = 'platform:create-admin {email} {--name=Administrator} {--password= : Min 12 chars; prompted when omitted} {--role=super_admin}';

    protected $description = 'Create a platform administrator (the first super admin is created this way)';

    public function handle(SaveAdminUser $save): int
    {
        $email = strtolower((string) $this->argument('email'));

        if (AdminUser::where('email', $email)->exists()) {
            $this->error("An admin with {$email} already exists.");

            return self::FAILURE;
        }

        $role = AdminRole::where('key', $this->option('role'))->first();

        if ($role === null) {
            $this->error('Unknown role: '.$this->option('role'));

            return self::FAILURE;
        }

        $password = $this->option('password') ?: $this->secret('Password (min 12 characters)');

        try {
            $save->create((string) $this->option('name'), $email, (string) $password, $role);
        } catch (AdminOperationDenied $e) {
            $this->error($e->userMessage());

            return self::FAILURE;
        }

        $this->info("Admin {$email} created with role {$role->key}.");

        return self::SUCCESS;
    }
}
