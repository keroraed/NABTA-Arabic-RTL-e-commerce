<?php

namespace App\Providers\Filament;

use Filament\Enums\ThemeMode;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use App\Http\Middleware\SetLocale;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('نبته NABTA')
            ->brandLogo(fn () => asset('assets/logo.webp'))
            ->brandLogoHeight('2.4rem')
            ->favicon(asset('assets/logo.webp'))
            // The shop's own palette, so the admin does not look like a different
            // product from the storefront.
            ->colors([
                'primary' => Color::hex('#176B3A'),
                'gray' => Color::Stone,
            ])
            ->defaultThemeMode(ThemeMode::Light)
            ->font('IBM Plex Sans Arabic')
            // Closures, not plain strings: the panel is constructed once at boot,
            // before the locale middleware runs, so anything resolved eagerly here
            // would freeze the language the app happened to start in.
            ->navigationGroups([
                NavigationGroup::make(fn () => __('المتجر')),
                NavigationGroup::make(fn () => __('الطلبات')),
                NavigationGroup::make(fn () => __('الكاشير')),
                NavigationGroup::make(fn () => __('المخزون')),
                NavigationGroup::make(fn () => __('الحسابات')),
                NavigationGroup::make(fn () => __('النظام')),
                NavigationGroup::make(fn () => __('الإعدادات')),
            ])
            // The till and the printable reports are Blade pages, not Filament
            // resources, so they are linked into the panel rather than rebuilt
            // inside it. Same session, same nav, one place to work from.
            ->userMenuItems([
                MenuItem::make()
                    ->label(fn () => SetLocale::current() === 'ar' ? 'English' : 'العربية')
                    ->url(fn () => route('locale.update', SetLocale::current() === 'ar' ? 'en' : 'ar'))
                    ->icon('heroicon-o-language'),
            ])
            ->navigationItems([
                NavigationItem::make(fn () => __('فتح الكاشير'))
                    ->url(fn () => route('pos.index'))
                    ->icon('heroicon-o-calculator')
                    ->group(fn () => __('الكاشير'))
                    ->sort(0),

                NavigationItem::make(fn () => __('حركة الخزينة'))
                    ->url(fn () => route('reports.treasury'))
                    ->icon('heroicon-o-document-chart-bar')
                    ->group(fn () => __('الحسابات'))
                    ->sort(10),

                NavigationItem::make(fn () => __('تصدير PLU للميزان'))
                    ->url(fn () => route('reports.plu-export'))
                    ->icon('heroicon-o-arrow-down-tray')
                    ->group(fn () => __('الكاشير'))
                    ->sort(9),
            ])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([
                Dashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                PreventRequestForgery::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
                SetLocale::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ]);
    }
}
