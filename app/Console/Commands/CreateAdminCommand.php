<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateAdminCommand extends Command
{
    protected $signature = 'judging:create-admin
                            {--name=Demo Administrator : Display name of the administrator}
                            {--username=admin : Login username}
                            {--password= : Password (generated securely when omitted)}';

    protected $description = 'Create or update the Judging System for Competition administrator account.';

    public function handle(): int
    {
        $username = (string) $this->option('username');
        $name = (string) $this->option('name');
        $password = (string) ($this->option('password') ?: 'Admin'.bin2hex(random_bytes(6)).'!');

        if (strlen($password) < 10) {
            $this->error('The password must be at least 10 characters long.');

            return self::FAILURE;
        }

        $admin = User::where('username', $username)->first();

        if (! $admin) {
            $admin = new User(['username' => $username]);
        }

        $admin->name = $name;
        $admin->role = User::ROLE_ADMIN;
        $admin->is_active = true;
        $admin->password = Hash::make($password);
        $admin->save();

        $this->info('Administrator account ready.');
        $this->line('  Username: '.$username);
        $this->line('  Password: '.$password);
        $this->line('  Store the password securely - it is not shown again.');

        return self::SUCCESS;
    }
}
