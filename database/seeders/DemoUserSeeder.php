<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

class DemoUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     * 
     * Creates demo admin and client users for testing.
     */
    public function run(): void
    {
        // Create Admin User
        $admin = User::firstOrCreate(
            ['email' => 'admin@talksasa.com'],
            [
                'name' => 'Admin User',
                'password' => Hash::make('password'),
                'code' => 'ADMIN001',
                'role' => 'admin',
                'is_active' => true,
                'rate_limit_per_minute' => 1000,
                'rate_limit_per_hour' => 100000,
            ]
        );

        $this->command->info('Admin user created/updated:');
        $this->command->info('  Email: admin@talksasa.com');
        $this->command->info('  Password: password');

        // Create Client User
        $client = User::firstOrCreate(
            ['email' => 'client@example.com'],
            [
                'name' => 'Demo Client',
                'password' => Hash::make('password'),
                'code' => 'CLIENT001',
                'role' => 'client',
                'is_active' => true,
                'rate_limit_per_minute' => 60,
                'rate_limit_per_hour' => 1000,
            ]
        );

        $this->command->info('Client user created/updated:');
        $this->command->info('  Email: client@example.com');
        $this->command->info('  Password: password');
    }
}
