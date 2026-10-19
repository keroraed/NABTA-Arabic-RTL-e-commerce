<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/**
 * Category landing-page copy, lifted from categories.html.
 *
 * Only fills columns that are still empty, so re-running the seeders never
 * overwrites wording the client has since edited in admin.
 */
class CategoryContentSeeder extends Seeder
{
    public function run(): void
    {
        $content = [
            'herbs' => [
                'أعشاب طبيعية',
                'منتجات منتقاة',
                'أوراق وزهور وبذور عشبية نقية من مصادر أصيلة، مجففة بطرق تحفظ خواصها وزيوتها الطبيعية.',
            ],
            'spices' => [
                'توابل وبهارات',
                'نكهة أصلية',
                'قرفة، هيل، فلفل، وخلطات توابل طازجة تعطي لأطباقك عبق المطبخ الشرقي الأصيل.',
            ],
            'nuts' => [
                'مكسرات فاخرة',
                'فاخر ومقرمش',
                'مكاديميا، لوز، فستق، وجوز هند منتقاة باليد ومحفوظة بعناية للحفاظ على القرمشة والقيمة الغذائية.',
            ],
            'seeds' => [
                'بذور وحبوب',
                'طاقة طبيعية',
                'بذور يقطين، شيا، كتان، وسمسم طبيعي من مزارع مختارة لتغذية صحية ومتوازنة.',
            ],
            'honey' => [
                'عسل ودبس طبيعي',
                'نقي 100%',
                'عسل أسود صعيدي خام، دبس قصب السكر، وأعسال زهور طبيعية خالية من أي إضافات صناعية.',
            ],
            'soap' => [
                'صابون وزيوت طبيعية',
                'صناعة يدوية',
                'قطع صابون معتوقة بزيت الزيتون البكر، العسل، وفيتامين C، لتنظيف وترطيب لطيف وصحي للبشرة.',
            ],
            'mixes' => [
                'خلطات العطارة الخاصة',
                'تركيبات متوارثة',
                'خلطات عشبية ومكسرات مجهزة بنسب دقيقة ومجربة لدعم المناعة والنشاط والراحة الجسدية.',
            ],
            'care' => [
                'عناية ونضارة طبيعية',
                'نقاء مغربي',
                'النيلة الزرقاء الأصلية، طين مغربي، وخلاصات طبيعية لنقاء وتفتيح ونضارة البشرة والشعر.',
            ],
        ];

        foreach ($content as $slug => [$title, $badge, $description]) {
            $category = Category::where('slug', $slug)->first();

            if (! $category) {
                continue;
            }

            $category->fill(array_filter([
                'title_ar' => $category->title_ar ?: $title,
                'badge_ar' => $category->badge_ar ?: $badge,
                'description_ar' => $category->description_ar ?: $description,
            ]))->save();
        }
    }
}
