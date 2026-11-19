<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class ResetPasswordCommand extends Command
{
    protected $signature = 'judging:reset-password
                            {username : Username of the account}
                            {--password= : New password (generated securely when omitted)}';

    protected $description = 'Reset the password of any Judging System for Competition account (admin or judge).';

    public function handle(): int
    {
        $username = (string) $this->argument('username');
        $password = (string) ($this->option('password') ?: 'Idea'.bin2hex(random_bytes(6)).'!');

        $user = User::where('username', $username)->first();

        if (! $user) {
            $this->error('No account found for username: '.$username);

            return self::FAILURE;
        }

        $user->password = Hash::make($password);
        $user->save();

        $this->info('Password reset for '.$user->name.' ('.$username.').');
        $this->line('  New password: '.$password);
        $this->line('  Store it securely - it is not shown again.');

        return self::SUCCESS;
    }
}
