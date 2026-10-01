<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (app()->isProduction() && !config('app.seed_demo_data')) {
            $this->command?->warn('Seed data demo dilewati di production (akun contoh berpassword "password"). Buat admin dengan: php artisan app:create-admin. Set SEED_DEMO_DATA=true kalau memang mau.');

            return;
        }

        User::factory()->admin()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $this->call(QuizSeeder::class);
        $this->call(AssessmentSeeder::class);
    }
}
