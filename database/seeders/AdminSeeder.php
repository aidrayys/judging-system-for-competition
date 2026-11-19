<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates the DEMO administrator account.
 *
 * Credentials come from APP_ADMIN_USERNAME / APP_ADMIN_PASSWORD (see
 * .env.example). If no password is configured, the documented demo-only
 * default below is used.
 */
class AdminSeeder extends Seeder
{
    /** Documented, demo-only placeholder password (see README). */
    public const DEFAULT_PASSWORD = 'Admin@Demo2026';

    public function run(): void
    {
        $username = env('APP_ADMIN_USERNAME', 'admin');
        $name = env('APP_ADMIN_NAME', 'Demo Administrator');

        if (User::where('username', $username)->exists()) {
            $this->command?->info('Admin account already exists: '.$username.' (no changes made).');

            return;
        }

        $password = env('APP_ADMIN_PASSWORD') ?: self::DEFAULT_PASSWORD;
        $generated = false;

        User::create([
            'name' => $name,
            'username' => $username,
            'password' => Hash::make($password),
            'role' => User::ROLE_ADMIN,
            'is_active' => true,
        ]);

        $this->command?->info('Demo admin account created.');
        $this->command?->line('  Username: '.$username);
        $this->command?->line('  Password: '.($generated ? $password.'  (generated - store it now)' : '(from APP_ADMIN_PASSWORD)'));
    }
}
