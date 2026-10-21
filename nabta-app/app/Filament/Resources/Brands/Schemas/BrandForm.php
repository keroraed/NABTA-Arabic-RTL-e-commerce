<?php

namespace App\Filament\Resources\Brands\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Str;

class BrandForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('العلامة التجارية'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name_ar')
                            ->label(__('الاسم بالعربية'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                                // Same rule as products: fill the slug only while it is
                                // still empty, so renaming never breaks a shared link.
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($get('name_en') ?: $state));
                                }
                            }),

                        TextInput::make('name_en')
                            ->label(__('الاسم بالإنجليزية'))
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                                // Arabic slugs are valid but read badly in a URL; prefer
                                // the Latin name when there is one.
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        TextInput::make('slug')
                            ->label(__('الرابط (slug)'))
                            ->helperText(__('يظهر في رابط فلتر العلامة في المتجر. تغييره يكسر أي روابط قديمة.'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->maxLength(255),

                        TextInput::make('sort_order')
                            ->label(__('ترتيب العرض'))
                            ->helperText(__('الأصغر يظهر أولاً.'))
                            ->numeric()
                            ->default(0)
                            ->required(),

                        FileUpload::make('logo_path')
                            ->label(__('الشعار'))
                            ->image()
                            ->disk('assets')
                            ->directory('brands')
                            ->visibility('public')
                            ->imageEditor()
                            ->maxSize(2048),

                        Textarea::make('description_ar')
                            ->label(__('الوصف'))
                            ->rows(4),

                        Toggle::make('is_active')
                            ->label(__('معروض في المتجر'))
                            ->default(true),
                    ]),
            ]);
    }
}
