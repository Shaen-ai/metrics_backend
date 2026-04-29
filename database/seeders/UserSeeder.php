<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        User::create([
            'id' => 'user-1',
            'email' => 'demo@example.com',
            'password' => 'demo123',
            'name' => 'Demo Admin',
            'company_name' => 'Demo Furniture Co.',
            'slug' => 'demo',
            'site_published_at' => Carbon::now(),
            'language' => 'en',
            'currency' => 'AMD',
            'email_verified_at' => Carbon::now(),
        ]);
    }
}
