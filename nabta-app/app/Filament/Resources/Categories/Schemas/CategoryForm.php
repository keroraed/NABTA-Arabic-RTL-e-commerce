<?php

namespace App\Filament\Resources\Categories\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('القسم'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name_ar')
                            ->label(__('اسم القسم'))
                            ->helperText(__('الاسم القصير المستخدم في الفلاتر وبطاقات المنتجات.'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('slug')
                            ->label(__('الرابط (slug)'))
                            ->helperText(__('يظهر في رابط صفحة القسم. تغييره يكسر أي روابط قديمة.'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('title_ar')
                            ->label(__('العنوان في صفحة الأقسام'))
                            ->placeholder(__('مثال: أعشاب طبيعية'))
                            ->maxLength(255),

                        TextInput::make('badge_ar')
                            ->label(__('الشارة'))
                            ->placeholder(__('مثال: منتجات منتقاة'))
                            ->maxLength(255),

                        Textarea::make('description_ar')
                            ->label(__('الوصف'))
                            ->rows(3)
                            ->columnSpanFull(),

                        TextInput::make('sort_order')
                            ->label(__('ترتيب العرض'))
                            ->helperText(__('الأصغر يظهر أولاً.'))
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label(__('معروض في المتجر'))
                            ->default(true),
                    ]),
            ]);
    }
}
