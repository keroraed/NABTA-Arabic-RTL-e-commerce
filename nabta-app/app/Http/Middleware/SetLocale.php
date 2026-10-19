<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Applies the staff member's chosen interface language.
 *
 * Arabic is the default and the fallback: this is an Egyptian shop, and English
 * exists for whoever is configuring or supporting the system rather than for the
 * people using it every day.
 *
 * The storefront is NOT affected — customers always see Arabic.
 */
class SetLocale
{
    public const SESSION_KEY = 'app_locale';

    public const SUPPORTED = ['ar', 'en'];

    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->session()->get(self::SESSION_KEY);

        if (in_array($locale, self::SUPPORTED, true)) {
            app()->setLocale($locale);
        }

        return $next($request);
    }

    public static function current(): string
    {
        return in_array(app()->getLocale(), self::SUPPORTED, true) ? app()->getLocale() : 'ar';
    }

    /** Arabic is RTL; English is not. Drives dir= on every admin and POS page. */
    public static function direction(): string
    {
        return self::current() === 'ar' ? 'rtl' : 'ltr';
    }
}
