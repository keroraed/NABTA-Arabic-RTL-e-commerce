<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'admin@nabta.test'],
            ['name' => 'مدير النظام', 'password' => Hash::make('password')],
        );

        $this->call([
            SettingSeeder::class,
            PosFoundationSeeder::class,
            GovernorateSeeder::class,
            CatalogSeeder::class,
            CategoryContentSeeder::class,
        ]);
    }
}
