<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CreateUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'user:create 
                            {--name= : User full name}
                            {--email= : User email address}
                            {--password= : User password (will be prompted if not provided)}
                            {--role=client : User role (admin or client)}
                            {--code= : User code/identifier (auto-generated if not provided)}
                            {--active : Make user active immediately}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Create a new admin or client user account';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        // Get or prompt for name
        $name = $this->option('name');
        if (!$name) {
            $name = $this->ask('Enter user full name');
        }

        // Get or prompt for email
        $email = $this->option('email');
        if (!$email) {
            $email = $this->ask('Enter user email address');
        }

        // Validate email
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid email address!');
            return Command::FAILURE;
        }

        // Check if email already exists
        if (User::where('email', $email)->exists()) {
            $this->error("User with email '{$email}' already exists!");
            return Command::FAILURE;
        }

        // Get or prompt for password
        $password = $this->option('password');
        if (!$password) {
            $password = $this->secret('Enter password (min 8 characters)');
            $confirmPassword = $this->secret('Confirm password');
            
            if ($password !== $confirmPassword) {
                $this->error('Passwords do not match!');
                return Command::FAILURE;
            }
            
            if (strlen($password) < 8) {
                $this->error('Password must be at least 8 characters!');
                return Command::FAILURE;
            }
        }

        // Get role
        $role = $this->option('role');
        if (!in_array($role, ['admin', 'client'])) {
            $this->error("Invalid role! Must be 'admin' or 'client'");
            return Command::FAILURE;
        }

        // Get or generate code
        $code = $this->option('code');
        if (!$code) {
            $prefix = strtoupper(substr($role, 0, 1));
            $code = $prefix . strtoupper(Str::random(6));
        }

        // Check if code already exists
        if (User::where('code', $code)->exists()) {
            $this->error("User with code '{$code}' already exists!");
            return Command::FAILURE;
        }

        // Get active status
        $isActive = $this->option('active') ?: $this->confirm('Make user active?', true);

        // Set rate limits based on role
        $rateLimitPerMinute = $role === 'admin' ? 1000 : 60;
        $rateLimitPerHour = $role === 'admin' ? 100000 : 1000;

        // Create user
        try {
            $user = User::create([
                'name' => $name,
                'email' => $email,
                'password' => Hash::make($password),
                'code' => $code,
                'role' => $role,
                'is_active' => $isActive,
                'rate_limit_per_minute' => $rateLimitPerMinute,
                'rate_limit_per_hour' => $rateLimitPerHour,
                'sms_enabled' => true,
            ]);

            $this->info("✅ User created successfully!");
            $this->newLine();
            $this->table(
                ['Field', 'Value'],
                [
                    ['ID', $user->id],
                    ['Name', $user->name],
                    ['Email', $user->email],
                    ['Code', $user->code],
                    ['Role', strtoupper($user->role)],
                    ['Status', $user->is_active ? 'Active' : 'Inactive'],
                    ['Rate Limit (per minute)', $user->rate_limit_per_minute],
                    ['Rate Limit (per hour)', $user->rate_limit_per_hour],
                ]
            );

            $this->newLine();
            $this->warn("⚠️  Password: {$password}");
            $this->warn("⚠️  Please save these credentials securely!");

            return Command::SUCCESS;
        } catch (\Exception $e) {
            $this->error("Failed to create user: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
