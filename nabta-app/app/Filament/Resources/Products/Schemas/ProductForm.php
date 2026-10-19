<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\Category;
use App\Models\Product;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('المنتج'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('name_ar')
                            ->label(__('اسم المنتج'))
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, callable $get, callable $set) {
                                // Only fill the slug while it is still untouched: an
                                // existing product's URL must not silently change
                                // under a rename and break inbound links.
                                if (blank($get('slug')) && filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Select::make('brand_id')
                            ->label(__('العلامة التجارية'))
                            ->relationship('brand', 'name_ar', fn (Builder $query) => $query->orderBy('sort_order'))
                            ->searchable()
                            ->preload()
                            ->placeholder(__('بدون علامة تجارية')),

                        Select::make('category_id')
                            ->label(__('القسم الأساسي'))
                            ->helperText(__('يظهر اسمه على بطاقة المنتج وفي الكاشير.'))
                            ->relationship('category', 'name_ar', fn (Builder $query) => $query->with('parent')->orderBy('sort_order'))
                            ->getOptionLabelFromRecordUsing(fn (Category $category) => $category->full_name)
                            ->searchable()
                            ->preload()
                            ->required()
                            // Moving the primary category swaps it in the list
                            // below too, visibly, so the old one is not left
                            // behind by accident.
                            ->live()
                            ->afterStateUpdated(function ($state, $old, callable $get, callable $set) {
                                $ids = array_map('intval', (array) $get('categories'));
                                $ids = array_values(array_diff($ids, [(int) $old]));

                                if ($state) {
                                    $ids[] = (int) $state;
                                }

                                $set('categories', array_values(array_unique($ids)));
                            }),

                        // Every category the product appears under in the shop,
                        // subcategories included. The primary category is always
                        // kept in this list on save, so it cannot be dropped here
                        // while it is still the primary one.
                        Select::make('categories')
                            ->label(__('كل الأقسام والأقسام الفرعية'))
                            ->helperText(__('يظهر المنتج في كل قسم مختار هنا، بالإضافة إلى القسم الأساسي.'))
                            ->relationship('categories', 'name_ar', fn (Builder $query) => $query->with('parent')->orderBy('sort_order'))
                            ->getOptionLabelFromRecordUsing(fn (Category $category) => $category->full_name)
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->saveRelationshipsUsing(function (Product $record, ?array $state) {
                                $ids = array_map('intval', $state ?? []);

                                if ($record->category_id) {
                                    $ids[] = (int) $record->category_id;
                                }

                                $record->categories()->sync(array_values(array_unique($ids)));
                            })
                            ->columnSpanFull(),

                        TextInput::make('slug')
                            ->label(__('الرابط (slug)'))
                            ->helperText(__('يظهر في رابط المنتج. تغييره يكسر أي روابط قديمة.'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        TextInput::make('sku')
                            ->label(__('كود المنتج (SKU)'))
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),

                        Textarea::make('short_desc_ar')
                            ->label(__('وصف مختصر'))
                            ->rows(2)
                            ->maxLength(500)
                            ->helperText(__('يظهر في بطاقة المنتج وصفحة الأقسام.'))
                            ->columnSpanFull(),

                        Textarea::make('description_ar')
                            ->label(__('الوصف الكامل'))
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Section::make(__('السعر والمخزون'))
                    ->columns(3)
                    ->schema([
                        TextInput::make('price')
                            ->label(__('السعر'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix(__('ج.م')),

                        TextInput::make('compare_at_price')
                            ->label(__('السعر قبل الخصم'))
                            ->helperText(__('اتركه فارغاً إذا لم يكن هناك خصم.'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix(__('ج.م')),

                        // Writing this posts a physical-count adjustment to the
                        // stock ledger for the difference; it is not a column.
                        // Routine stock changes should come from goods receipts
                        // and sales, not from retyping this number.
                        TextInput::make('stock')
                            ->label(__('الرصيد الحالي (جرد)'))
                            ->numeric()
                            ->minValue(0)
                            ->default(0)
                            ->helperText(__('تعديل هذا الرقم يسجّل حركة تسوية جرد بالفرق. الرصيد الفعلي يأتي من حركات المخزون.'))
                            ->afterStateHydrated(fn ($component, $state, $record) => $component->state($record?->stock ?? 0)),

                        TextInput::make('weight_label')
                            ->label(__('الوزن / الحجم'))
                            ->placeholder(__('مثال: 250 جم'))
                            ->maxLength(255),

                        Toggle::make('is_active')
                            ->label(__('معروض في المتجر'))
                            ->default(true),

                        Toggle::make('is_featured')
                            ->label(__('منتج مميز')),
                    ]),

                Section::make(__('الميزان والباركود'))
                    ->description(__('للمنتجات التي تُباع بالوزن على ميزان ACLAS. كود PLU هو ما يختاره الموظف على الميزان.'))
                    ->columns(2)
                    ->schema([
                        Toggle::make('is_weighted')
                            ->label(__('يُباع بالوزن'))
                            ->helperText(__('عند التفعيل يُحسب السعر = سعر الكيلو × الوزن المقروء من ملصق الميزان.'))
                            ->live()
                            ->columnSpanFull(),

                        TextInput::make('plu_code')
                            ->label(__('كود PLU'))
                            ->numeric()
                            ->unique(ignoreRecord: true)
                            ->visible(fn (callable $get) => (bool) $get('is_weighted'))
                            ->helperText(__('يجب أن يطابق الكود المسجّل على الميزان.')),

                        TextInput::make('price_per_kg')
                            ->label(__('سعر الكيلو'))
                            ->numeric()
                            ->minValue(0)
                            ->suffix(__('ج.م'))
                            ->required(fn (callable $get) => (bool) $get('is_weighted'))
                            ->visible(fn (callable $get) => (bool) $get('is_weighted')),

                        TextInput::make('barcode')
                            ->label(__('الباركود'))
                            ->helperText(__('للمنتجات المعبّأة التي لها باركود ثابت.'))
                            ->visible(fn (callable $get) => ! $get('is_weighted'))
                            ->maxLength(255),

                        Toggle::make('sync_to_scale')
                            ->label(__('إرساله إلى الميزان'))
                            ->helperText(__('يُدرج في ملف تصدير PLU.'))
                            ->visible(fn (callable $get) => (bool) $get('is_weighted')),
                    ]),

                Section::make(__('الصور'))
                    ->description(__('الصورة الأولى هي صورة المنتج الأساسية. الصورة الثانية (المنتج مفتوحاً) تُستخدم في تأثير الأقسام بالصفحة الرئيسية.'))
                    ->schema([
                        Repeater::make('images')
                            ->label('')
                            ->relationship()
                            ->orderColumn('sort_order')
                            ->reorderable()
                            ->addActionLabel(__('إضافة صورة'))
                            ->schema([
                                FileUpload::make('path')
                                    ->label(__('الصورة'))
                                    ->image()
                                    ->disk('assets')
                                    ->directory('products')
                                    ->visibility('public')
                                    ->imageEditor()
                                    ->maxSize(4096)
                                    ->required(),

                                TextInput::make('alt_ar')
                                    ->label(__('وصف الصورة (لمحركات البحث وقارئات الشاشة)'))
                                    ->maxLength(255),
                            ]),
                    ]),

                Section::make(__('تفاصيل إضافية'))
                    ->collapsed()
                    ->schema([
                        Textarea::make('ingredients_ar')->label(__('المكوّنات'))->rows(3),
                        Textarea::make('usage_ar')->label(__('طريقة الاستخدام والحفظ'))->rows(3),
                        TextInput::make('sort_order')
                            ->label(__('ترتيب العرض'))
                            ->helperText(__('الأصغر يظهر أولاً.'))
                            ->numeric()
                            ->default(0),
                    ]),
            ]);
    }
}
