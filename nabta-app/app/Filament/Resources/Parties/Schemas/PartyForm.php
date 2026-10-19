<?php

namespace App\Filament\Resources\Parties\Schemas;

use App\Models\Party;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PartyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('البيانات'))
                    ->columns(2)
                    ->description(__('العملاء هنا خفيفون عمداً: اسم ورقم تليفون فقط — لا عناوين ولا حسابات دخول.'))
                    ->schema([
                        Select::make('type')
                            ->label(__('النوع'))
                            ->options(collect(Party::TYPE_LABELS)->map(fn ($l) => __($l))->all())
                            ->default(Party::TYPE_CUSTOMER)
                            ->required(),

                        TextInput::make('name')
                            ->label(__('الاسم'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('phone')
                            ->label(__('رقم التليفون'))
                            ->tel()
                            ->maxLength(20)
                            ->helperText(__('يُستخدم للبحث السريع من الكاشير.')),

                        // Only meaningful before any ledger entry exists; after
                        // that the balance is the ledger's, not this field's.
                        TextInput::make('opening_balance')
                            ->label(__('رصيد افتتاحي'))
                            ->numeric()
                            ->default(0)
                            ->suffix(__('ج.م'))
                            ->helperText(__('الرصيد قبل بدء استخدام النظام.')),

                        Toggle::make('is_active')->label(__('نشط'))->default(true),

                        Textarea::make('notes')->label(__('ملاحظات'))->rows(2)->columnSpanFull(),
                    ]),
            ]);
    }
}
