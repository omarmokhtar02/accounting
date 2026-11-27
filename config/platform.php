<?php

return [

    /*
    | First platform super admin, created by `php artisan db:seed` (PlatformAdminSeeder). Nothing is created
    | unless both an email and a password are set, and there is deliberately no default password.
    */
    'admin' => [
        'name' => env('PLATFORM_ADMIN_NAME', 'Super Admin'),
        'email' => env('PLATFORM_ADMIN_EMAIL'),
        'password' => env('PLATFORM_ADMIN_PASSWORD'),
    ],

];
