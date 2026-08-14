<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin
        User::create([
            'name' => 'System Admin',
            'email' => 'admin@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Admin',
            'department' => 'IT',
            'years_of_experience' => 10,
            'location' => 'HQ',
            'active_tasks_count' => 0
        ]);

        // Standard Users
        User::create([
            'name' => 'Finance Executive',
            'email' => 'finance@example.com',
            'password' => Hash::make('password123'),
            'role' => 'User',
            'department' => 'Finance',
            'years_of_experience' => 5,
            'location' => 'Branch 1',
            'active_tasks_count' => 0
        ]);

        User::create([
            'name' => 'HR Manager',
            'email' => 'hr@example.com',
            'password' => Hash::make('password123'),
            'role' => 'Manager',
            'department' => 'HR',
            'years_of_experience' => 7,
            'location' => 'HQ',
            'active_tasks_count' => 0
        ]);
    }
}
