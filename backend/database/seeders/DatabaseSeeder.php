<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use App\Models\ShiftType;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // The administrator account is created only when credentials are
        // explicitly provided through the environment. No default password is
        // baked into the repository.
        $username = env('ADMIN_USERNAME');
        $password = env('ADMIN_PASSWORD');

        if ($username && $password) {
            User::firstOrCreate(
                ['username' => $username],
                [
                    'password' => Hash::make($password),
                    'display_name' => env('ADMIN_DISPLAY_NAME', '管理者'),
                ]
            );
        } else {
            $this->command?->warn(
                'Skipping admin user seeding: set ADMIN_USERNAME and ADMIN_PASSWORD to create one.'
            );
        }

        $types = [
            ['name' => '日勤', 'sort_order' => 1, 'start_time' => '08:30:00', 'end_time' => '17:30:00', 'color' => '#1976d2'],
            ['name' => '早番', 'sort_order' => 2, 'start_time' => '07:00:00', 'end_time' => '16:00:00', 'color' => '#e67e22'],
            ['name' => '遅番', 'sort_order' => 3, 'start_time' => '10:00:00', 'end_time' => '20:00:00', 'color' => '#8e44ad'],
            ['name' => '夜勤', 'sort_order' => 4, 'start_time' => '17:00:00', 'end_time' => '09:00:00', 'color' => '#2c3e50'],
            ['name' => '明け', 'sort_order' => 5, 'start_time' => null, 'end_time' => null, 'color' => '#16a085'],
        ];
        foreach ($types as $t) {
            ShiftType::firstOrCreate(['name' => $t['name']], $t);
        }
    }
}
