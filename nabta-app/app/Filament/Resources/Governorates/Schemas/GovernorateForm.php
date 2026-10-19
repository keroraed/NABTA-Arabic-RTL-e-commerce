<?php

namespace App\Filament\Resources\Governorates\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class GovernorateForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('المحافظة'))
                    ->columns(2)
                    ->description(__('سعر الشحن هنا هو المصدر الوحيد المعتمد — يُحسب على الخادم عند إنشاء الطلب ولا يُقرأ أبداً من المتصفح.'))
                    ->schema([
                        TextInput::make('name_ar')
                            ->label(__('اسم المحافظة'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('shipping_fee')
                            ->label(__('سعر الشحن'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix(__('ج.م')),

                        TextInput::make('sort_order')
                            ->label(__('ترتيب العرض'))
                            ->numeric()
                            ->default(0)
                            ->required(),

                        Toggle::make('is_active')
                            ->label(__('متاح للشحن'))
                            ->helperText(__('إيقافه يخفي المحافظة من صفحة إتمام الطلب.'))
                            ->default(true),
                    ]),
            ]);
    }
}
