<?php

namespace Database\Seeders;

use App\Models\Governorate;
use Illuminate\Database\Seeder;

/**
 * Carried over verbatim from assets/js/nabta-shop.js so the ported storefront
 * quotes exactly what the static site quoted.
 *
 * WARNING: these are PLACEHOLDER rates. HANDOFF.md 2.4 flags them as invented.
 * Confirm the real table with the courier before launch and edit them in admin.
 */
class GovernorateSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['القاهرة', 50], ['الجيزة', 50], ['القليوبية', 55],
            ['الإسكندرية', 60], ['البحيرة', 65], ['مطروح', 90],
            ['دمياط', 65], ['الدقهلية', 65], ['الشرقية', 65],
            ['كفر الشيخ', 65], ['الغربية', 65], ['المنوفية', 65],
            ['بورسعيد', 70], ['الإسماعيلية', 70], ['السويس', 70],
            ['شمال سيناء', 100], ['جنوب سيناء', 100],
            ['بني سويف', 75], ['الفيوم', 75], ['المنيا', 75],
            ['أسيوط', 80], ['سوهاج', 80], ['قنا', 85],
            ['الأقصر', 85], ['أسوان', 90],
            ['البحر الأحمر', 100], ['الوادي الجديد', 100],
        ];

        foreach ($rows as $i => [$name, $fee]) {
            Governorate::updateOrCreate(
                ['name_ar' => $name],
                ['shipping_fee' => $fee, 'is_active' => true, 'sort_order' => $i],
            );
        }
    }
}
