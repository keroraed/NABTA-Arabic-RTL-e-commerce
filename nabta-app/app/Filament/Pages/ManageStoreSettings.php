<?php

namespace App\Filament\Pages;

use App\Enums\Permission;
use App\Models\Setting;
use BackedEnum;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use UnitEnum;

/**
 * The handful of storefront values the client needs to change without a deploy.
 *
 * HANDOFF.md 2.4 flags the free-shipping threshold and the support phone as
 * invented placeholders that must be confirmed before launch — this is where
 * they get corrected.
 */
class ManageStoreSettings extends Page implements HasForms
{
    use InteractsWithForms;

    protected string $view = 'filament.pages.manage-store-settings';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCog6Tooth;



    protected static ?string $title = 'إعدادات المتجر';

    protected static ?int $navigationSort = 10;

    public static function canAccess(): bool
    {
        return auth()->user()?->can(Permission::SettingsManage->value) ?? false;
    }

    /** @var array<string, mixed> */
    public array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'free_shipping_threshold' => Setting::get('free_shipping_threshold', 1000),
            'store_phone' => Setting::get('store_phone'),
            'store_address_ar' => Setting::get('store_address_ar'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make(__('الشحن'))
                    ->schema([
                        TextInput::make('free_shipping_threshold')
                            ->label(__('حد الشحن المجاني'))
                            ->helperText(__('الطلبات التي يبلغ مجموعها هذا الرقم أو أكثر تُشحن مجاناً. ضع 0 لإلغاء الشحن المجاني.'))
                            ->numeric()
                            ->minValue(0)
                            ->required()
                            ->suffix(__('ج.م')),
                    ]),

                Section::make(__('بيانات التواصل'))
                    ->description(__('تظهر في تذييل كل صفحة بالمتجر.'))
                    ->columns(2)
                    ->schema([
                        TextInput::make('store_phone')
                            ->label(__('رقم خدمة العملاء'))
                            ->maxLength(255),

                        TextInput::make('store_address_ar')
                            ->label(__('العنوان'))
                            ->maxLength(255),
                    ]),
            ]);
    }

    public function save(): void
    {
        foreach ($this->form->getState() as $key => $value) {
            Setting::put($key, $value);
        }

        Notification::make()
            ->title(__('تم حفظ الإعدادات'))
            ->success()
            ->send();
    }
    public static function getNavigationLabel(): string
    {
        return __('إعدادات المتجر');
    }

    public static function getNavigationGroup(): ?string
    {
        return __('الإعدادات');
    }
}
