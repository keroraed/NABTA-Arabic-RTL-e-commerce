<?php

namespace App\Filament\Resources\ExpenseCategories\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ExpenseCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('بيانات نوع المصروف'))
                    ->description(__('تعريف تصنيف للمصروفات لتسهيل المتابعة والتقارير المالية.'))
                    ->schema([
                        TextInput::make('name')
                            ->label(__('اسم نوع المصروف'))
                            ->placeholder(__('مثال: إيجار، كهرباء ومياه، مرتبات، بوفيه ونظافة، صيانة...'))
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),

                        Toggle::make('is_active')
                            ->label(__('مفعّل'))
                            ->helperText(__('تفعيل هذا النوع ليظهر في قائمة الاختيار عند تسجيل مصروف جديد.'))
                            ->default(true)
                            ->required(),
                    ]),
            ]);
    }
}
