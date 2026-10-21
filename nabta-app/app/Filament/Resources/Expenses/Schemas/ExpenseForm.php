<?php

namespace App\Filament\Resources\Expenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class ExpenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('بيانات المصروف الأساسية'))
                    ->columns(2)
                    ->schema([
                        Select::make('expense_category_id')
                            ->label(__('نوع المصروف'))
                            ->relationship('category', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required()
                            ->createOptionForm([
                                TextInput::make('name')
                                    ->label(__('اسم نوع المصروف'))
                                    ->placeholder(__('مثال: إيجار، صيانة، بوفيه...'))
                                    ->required()
                                    ->maxLength(255),
                                Toggle::make('is_active')
                                    ->label(__('مفعّل'))
                                    ->default(true),
                            ])
                            ->createOptionModalHeading(__('إضافة نوع مصروف جديد')),

                        Select::make('cash_account_id')
                            ->label(__('خزينة الصرف / الحساب المالي'))
                            ->relationship('cashAccount', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->required(),

                        TextInput::make('amount')
                            ->label(__('المبلغ'))
                            ->required()
                            ->numeric()
                            ->minValue(0.01)
                            ->suffix(__('ج.م')),

                        DatePicker::make('expense_date')
                            ->label(__('تاريخ المصروف'))
                            ->required()
                            ->default(now())
                            ->native(false)
                            ->displayFormat('d/m/Y'),

                        Select::make('warehouse_id')
                            ->label(__('الفرع / المخزن'))
                            ->relationship('warehouse', 'name', fn (Builder $query) => $query->where('is_active', true))
                            ->searchable()
                            ->preload()
                            ->placeholder(__('عام (غير مرتبط بفرع)'))
                            ->helperText(__('اتركه فارغاً إذا كان المصروف عاماً للنشاط ككل.')),

                        Hidden::make('created_by')
                            ->default(fn () => auth()->id()),
                    ]),

                Section::make(__('التفاصيل والمرفقات'))
                    ->columns(1)
                    ->schema([
                        Textarea::make('description')
                            ->label(__('البيان / تفاصيل المصروف'))
                            ->placeholder(__('اكتب تفاصيل أو ملاحظات عن سبب الصرف...'))
                            ->rows(3)
                            ->maxLength(500)
                            ->columnSpanFull(),

                        FileUpload::make('attachment_path')
                            ->label(__('إيصال أو فاتورة المصروف'))
                            ->disk('public')
                            ->directory('expenses')
                            ->visibility('public')
                            ->openable()
                            ->downloadable()
                            ->acceptedFileTypes(['application/pdf', 'image/*'])
                            ->maxSize(10240)
                            ->helperText(__('يمكن إرفاق صورة الإيصال أو ملف PDF (بحد أقصى 10 ميجابايت).'))
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
