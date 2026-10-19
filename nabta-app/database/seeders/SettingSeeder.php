<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // PLACEHOLDER — HANDOFF.md 2.4: confirm with the client/courier.
            'free_shipping_threshold' => '1000',
            'store_phone' => '+20 100 000 0000',
            'store_address_ar' => 'القاهرة، جمهورية مصر العربية',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(['key' => $key], ['value' => $value]);
        }
    }
}
