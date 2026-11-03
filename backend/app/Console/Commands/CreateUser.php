<?php
namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class CreateUser extends Command
{
    protected $signature = 'user:create {username} {display_name} {password}';
    protected $description = 'Create a new user';

    public function handle(): int
    {
        $username = $this->argument('username');
        $displayName = $this->argument('display_name');
        $password = $this->argument('password');

        if (User::where('username', $username)->exists()) {
            $this->error("Username '{$username}' already exists.");
            return self::FAILURE;
        }

        User::create([
            'username' => $username,
            'display_name' => $displayName,
            'password' => Hash::make($password),
        ]);

        $this->info("User '{$username}' created successfully.");
        return self::SUCCESS;
    }
}
